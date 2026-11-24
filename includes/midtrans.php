<?php
/**
 * Integrasi Midtrans Snap tanpa library tambahan (cukup ekstensi cURL bawaan XAMPP).
 * Kunci API diatur di bagian atas config.php.
 */
require_once __DIR__ . '/../config.php';

function midtrans_siap(): bool
{
    return MIDTRANS_SERVER_KEY !== '' && MIDTRANS_CLIENT_KEY !== '';
}

function midtrans_snap_js(): string
{
    return MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
}

function midtrans_snap_api(): string
{
    return MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
}

function midtrans_core_api(): string
{
    return MIDTRANS_IS_PRODUCTION ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
}

/** Panggil API Midtrans. Mengembalikan [kode HTTP, respons JSON (array) atau null, pesan galat jaringan atau null]. */
function midtrans_request(string $method, string $url, ?array $body = null): array
{
    if (!function_exists('curl_init')) {
        return [0, null, 'Ekstensi cURL PHP belum aktif (aktifkan extension=curl di php.ini).'];
    }
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
    ];
    $opsi = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    // XAMPP sering tidak punya CA bundle untuk cURL. Jika file cacert.pem ada di includes/, pakai itu.
    $caBundle = __DIR__ . '/cacert.pem';
    if (is_file($caBundle)) {
        $opsi[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $opsi);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $GLOBALS['midtrans_debug'] = ['raw' => null, 'ssl_fallback' => false, 'url' => $url];
    $raw = curl_exec($ch);
    // Khusus Sandbox: XAMPP sering tanpa CA bundle (galat SSL 60/77). Ulangi tanpa verifikasi agar uji coba tetap jalan.
    if ($raw === false && !MIDTRANS_IS_PRODUCTION && in_array(curl_errno($ch), [60, 77], true)) {
        error_log('[Midtrans] CA bundle tidak ditemukan, mengulang tanpa verifikasi SSL (hanya Sandbox). Pasang includes/cacert.pem untuk menghilangkan peringatan ini.');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        unset($opsi[CURLOPT_CAINFO]);
        $GLOBALS['midtrans_debug']['ssl_fallback'] = true;
        $raw = curl_exec($ch);
    }
    $GLOBALS['midtrans_debug']['raw'] = is_string($raw) ? $raw : null;
    $GLOBALS['midtrans_debug']['ip'] = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $kode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $galat = $raw === false ? curl_error($ch) : null;
    curl_close($ch);

    $json = is_string($raw) ? json_decode($raw, true) : null;
    return [$kode, is_array($json) ? $json : null, $galat];
}

/**
 * Buat transaksi Snap untuk sebuah pesanan dan simpan token-nya.
 * Mengembalikan [token atau null, pesan galat atau null]. Tidak pernah melempar exception.
 */
function midtrans_buat_token(int $idPesanan): array
{
    if (!midtrans_siap()) {
        return [null, 'Midtrans belum dikonfigurasi (isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di config.php).'];
    }


    try {
        pastikan_tabel_pembayaran();
        $pdo = db();
        $st = $pdo->prepare('SELECT o.*, c.nama_pelanggan, c.email, c.no_hp, c.alamat, c.kode_pos
            FROM pesanan o JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan WHERE o.id_pesanan = ?');
        $st->execute([$idPesanan]);
        $o = $st->fetch();
        if (!$o) {
            return [null, 'Pesanan tidak ditemukan.'];
        }

        $d = $pdo->prepare('SELECT d.id_produk, d.jumlah, d.harga_satuan, p.nama_produk
            FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk WHERE d.id_pesanan = ?');
        $d->execute([$idPesanan]);

        // Midtrans mewajibkan jumlah harga item = gross_amount, dalam rupiah bulat.
        $items = [];
        $gross = 0;
        foreach ($d->fetchAll() as $r) {
            $harga = (int)round((float)$r['harga_satuan']);
            $items[] = [
                'id' => 'P' . $r['id_produk'],
                'price' => $harga,
                'quantity' => (int)$r['jumlah'],
                'name' => mb_substr((string)$r['nama_produk'], 0, 50),
            ];
            $gross += $harga * (int)$r['jumlah'];
        }
        if (!$items || $gross <= 0) {
            return [null, 'Rincian pesanan kosong.'];
        }

        $orderId = $o['midtrans_order_id'] ?: 'AP-' . $idPesanan . '-' . time();

        $alamat = [
            'address' => mb_substr((string)$o['alamat'], 0, 200),
            'postal_code' => (string)$o['kode_pos'],
            'country_code' => 'IDN',
        ];
        $pelanggan = array_filter([
            'first_name' => mb_substr((string)$o['nama_pelanggan'], 0, 255),
            'email' => (string)$o['email'],
            'phone' => (string)$o['no_hp'],
            'shipping_address' => array_filter($alamat),
        ]);

        [$kode, $res, $galatJaringan] = midtrans_request('POST', midtrans_snap_api(), [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $gross],
            'item_details' => $items,
            'customer_details' => $pelanggan,
            'expiry' => ['unit' => 'hours', 'duration' => 24],
        ]);

        if ($galatJaringan !== null) {
            error_log('[Midtrans] galat jaringan: ' . $galatJaringan);
            return [null, 'Tidak bisa menghubungi Midtrans (' . $galatJaringan . '). Jika tertulis SSL/certificate, lihat petunjuk curl.cacert di php.ini.'];
        }
        if ($kode !== 201) {
            error_log('[Midtrans] HTTP ' . $kode . ': ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        if ($kode === 401) {
            $k = MIDTRANS_SERVER_KEY;
            $samar = substr($k, 0, 14) . '...' . substr($k, -4) . ' (panjang ' . strlen($k) . ')';
            $dariMidtrans = isset($res['error_messages']) && is_array($res['error_messages']) ? implode(' ', $res['error_messages']) : '';
            return [null, 'Midtrans menolak Server Key (401). Key yang terbaca server: ' . $samar
                . '; mode: ' . (MIDTRANS_IS_PRODUCTION ? 'Production' : 'Sandbox')
                . '; endpoint: ' . midtrans_snap_api()
                . ($dariMidtrans !== '' ? '; pesan Midtrans: ' . $dariMidtrans : '')
                . '; IP server tujuan: ' . ($GLOBALS['midtrans_debug']['ip'] ?? '?')
                . '; SSL verifikasi dilewati: ' . (!empty($GLOBALS['midtrans_debug']['ssl_fallback']) ? 'ya' : 'tidak')
                . '; isi balasan: ' . mb_substr(trim((string)preg_replace('/\\s+/', ' ', strip_tags((string)($GLOBALS['midtrans_debug']['raw'] ?? '')))), 0, 300) . '.'];
        }
        if ($kode !== 201 || empty($res['token'])) {
            $pesan = isset($res['error_messages']) && is_array($res['error_messages'])
                ? implode(' ', $res['error_messages'])
                : 'Midtrans menolak permintaan (HTTP ' . $kode . ').';
            return [null, $pesan];
        }

        $pdo->prepare('UPDATE pesanan SET midtrans_order_id = ?, snap_token = ? WHERE id_pesanan = ?')
            ->execute([$orderId, $res['token'], $idPesanan]);
        simpan_pembayaran($idPesanan, ['midtrans_order_id' => $orderId]);
        return [$res['token'], null];
    } catch (Throwable $ex) {
        error_log('[Midtrans] gagal membuat token: ' . $ex->getMessage());
        return [null, 'Gagal menyiapkan pembayaran Midtrans.'];
    }
}

/** Ambil status transaksi terbaru dari Midtrans (null bila transaksi belum ada). */
function midtrans_cek_status(string $orderId): ?array
{
    [, $res] = midtrans_request('GET', midtrans_core_api() . '/v2/' . rawurlencode($orderId) . '/status');
    if ($res === null || ($res['status_code'] ?? '') === '404') {
        return null;
    }
    return $res;
}

/**
 * Terapkan status transaksi Midtrans ke pesanan (dipakai webhook dan pengecekan manual).
 *  - settlement / capture(accept) → status bayar "Lunas"; pesanan Pending menjadi "Diproses".
 *  - cancel / expire              → pesanan "Dibatalkan" dan stok dikembalikan (jika belum lunas).
 *  - deny / failure               → status bayar "Gagal" (pelanggan masih bisa mencoba lagi).
 * Aman dipanggil berulang. Mengembalikan keterangan singkat hasilnya.
 */
function midtrans_terapkan_status(array $data): string
{
    $orderId = (string)($data['order_id'] ?? '');
    $ts = (string)($data['transaction_status'] ?? '');
    $fraud = (string)($data['fraud_status'] ?? '');
    if ($orderId === '') {
        return 'order_id kosong';
    }

    pastikan_tabel_pembayaran();
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT id_pesanan, total_harga, status, status_bayar FROM pesanan WHERE midtrans_order_id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o) {
            $pdo->commit();
            return 'pesanan tidak ditemukan';
        }
        $id = (int)$o['id_pesanan'];

        $lunas = $ts === 'settlement' || ($ts === 'capture' && in_array($fraud, ['', 'accept'], true));

        if ($lunas) {
            if (abs((float)($data['gross_amount'] ?? 0) - (float)$o['total_harga']) > 1) {
                $pdo->commit();
                return 'nominal tidak cocok';
            }
            if ($o['status_bayar'] !== 'Lunas') {
                $pdo->prepare('UPDATE pesanan SET status_bayar = ? WHERE id_pesanan = ?')->execute(['Lunas', $id]);
            }
            if ($o['status'] === 'Pending') {
                $pdo->prepare('UPDATE pesanan SET status = ? WHERE id_pesanan = ?')->execute(['Diproses', $id]);
            }
            $hasil = 'lunas';
        } elseif (in_array($ts, ['cancel', 'expire'], true) && $o['status_bayar'] !== 'Lunas') {
            if ($o['status'] !== 'Dibatalkan') {
                $pdo->prepare('UPDATE pesanan SET status = ?, status_bayar = ? WHERE id_pesanan = ?')
                    ->execute(['Dibatalkan', $ts === 'expire' ? 'Kedaluwarsa' : 'Gagal', $id]);
                kembalikan_stok($id);
            }
            $hasil = 'dibatalkan';
        } elseif (in_array($ts, ['deny', 'failure'], true) && $o['status_bayar'] !== 'Lunas') {
            $pdo->prepare('UPDATE pesanan SET status_bayar = ? WHERE id_pesanan = ?')->execute(['Gagal', $id]);
            $hasil = 'gagal';
        } else {
            $hasil = 'tidak ada perubahan';
        }

        // Catat detail transaksi Midtrans di tabel pembayaran.
        $sb = $pdo->prepare('SELECT status_bayar FROM pesanan WHERE id_pesanan = ?');
        $sb->execute([$id]);
        $statusBayarBaru = (string)$sb->fetchColumn();
        $bank = $data['va_numbers'][0]['bank'] ?? ($data['bank'] ?? (isset($data['permata_va_number']) ? 'permata' : null));
        $kolom = array_filter([
            'midtrans_transaction_id' => $data['transaction_id'] ?? null,
            'tipe_pembayaran' => $data['payment_type'] ?? null,
            'bank' => $bank,
            'status_transaksi' => $ts,
            'fraud_status' => $fraud,
            'respons_midtrans' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ], static fn($v) => $v !== null && $v !== '');
        $kolom['status'] = $statusBayarBaru;
        if ($lunas) {
            $kolom['waktu_bayar'] = (string)($data['settlement_time'] ?? $data['transaction_time'] ?? date('Y-m-d H:i:s'));
        }
        simpan_pembayaran($id, $kolom);

        $pdo->commit();
        return $hasil;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Midtrans] gagal menerapkan status ' . $orderId . ': ' . $ex->getMessage());
        return 'galat';
    }
}
