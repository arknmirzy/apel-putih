<?php
/**
 * Sesi terpisah per peran (pelanggan, admin, supplier). Setiap peran memakai nama cookie sendiri,
 * sehingga beberapa peran bisa login bersamaan di tab yang berbeda tanpa saling menghapus.
 */
const NAMA_SESI = [
    'pelanggan' => 'APELPUTIH_PELANGGAN',
    'admin' => 'APELPUTIH_ADMIN',
    'supplier' => 'APELPUTIH_SUPPLIER',
];

/** Peran yang sesuai dengan lokasi file yang sedang dijalankan (folder admin/ atau supplier/). */
function peran_halaman(): string
{
    $lokasi = str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($lokasi, '/admin/')) {
        return 'admin';
    }
    if (str_contains($lokasi, '/supplier/')) {
        return 'supplier';
    }
    return 'pelanggan';
}

/** Pindah ke sesi milik peran tertentu (dipakai juga saat login sebagai peran lain). */
function mulai_sesi(string $peran): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name(NAMA_SESI[$peran]);
    session_start();
}

mulai_sesi(peran_halaman());

// ---------- Konfigurasi ----------
const STORE_NAME = 'Apel Putih';
const DB_HOST = 'localhost';
const DB_NAME = 'iphone_store';
const DB_USER = 'root';
const DB_PASS = '';

// ---------- Midtrans (Snap) ----------
// Ambil kunci di dashboard Midtrans: Settings > Access Keys.
// Kunci berawalan "Mid-" = Production (MIDTRANS_IS_PRODUCTION = true, akun harus sudah Live).
// Kunci berawalan "SB-Mid-" = Sandbox (MIDTRANS_IS_PRODUCTION = false).
// Key diisi langsung di sini (mode Sandbox). Jangan upload file ini ke repositori publik.
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-9N7kA5j~Vc6m9JttrFvqs3nH');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-YhT6IoKF0_lUB-pb0AjOIkv8');

/** Label metode pembayaran yang disimpan di kolom pesanan.metode_pembayaran. */
const MIDTRANS_LABEL = 'Midtrans';
const COD_LABEL = 'COD (Bayar di Tempat)';

/** Ubah nilainya setiap kali CSS/JS diperbarui agar cache browser ikut segar. */
const ASSET_VERSION = '20261122-keranjang-kartu';

/** Pilihan kurir yang ditampilkan saat checkout. */
const KURIR_OPSI = ['JNE', 'J&T Express', 'SiCepat', 'AnterAja', 'Ninja Xpress', 'POS Indonesia'];

const STATUS_PESANAN = ['Pending', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'];

/** Daftar provinsi di Indonesia untuk isian alamat. */
const PROVINSI = [
    'Aceh',
    'Sumatera Utara',
    'Sumatera Barat',
    'Riau',
    'Kepulauan Riau',
    'Jambi',
    'Sumatera Selatan',
    'Kepulauan Bangka Belitung',
    'Bengkulu',
    'Lampung',
    'DKI Jakarta',
    'Banten',
    'Jawa Barat',
    'Jawa Tengah',
    'DI Yogyakarta',
    'Jawa Timur',
    'Bali',
    'Nusa Tenggara Barat',
    'Nusa Tenggara Timur',
    'Kalimantan Barat',
    'Kalimantan Tengah',
    'Kalimantan Selatan',
    'Kalimantan Timur',
    'Kalimantan Utara',
    'Sulawesi Utara',
    'Gorontalo',
    'Sulawesi Tengah',
    'Sulawesi Barat',
    'Sulawesi Selatan',
    'Sulawesi Tenggara',
    'Maluku',
    'Maluku Utara',
    'Papua',
    'Papua Barat',
    'Papua Barat Daya',
    'Papua Tengah',
    'Papua Pegunungan',
    'Papua Selatan',
];

/** Folder sumber gambar produk, urut dari prioritas tertinggi. */
const FOLDER_GAMBAR = ['uploads', 'assets/product-display'];

// ---------- Database ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<p style="font-family:sans-serif;padding:2rem">Tidak bisa terhubung ke database <b>'
                . DB_NAME . '</b>. Pastikan MySQL di XAMPP menyala dan <b>database.sql</b> sudah diimpor lewat phpMyAdmin.</p>');
        }
    }
    return $pdo;
}

/** Buat tabel pengadaan bila belum ada, sehingga database lama tidak perlu diimpor ulang. */
function pastikan_tabel_pengadaan(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS pengadaan (
        id_pengadaan INT AUTO_INCREMENT PRIMARY KEY,
        id_supplier INT NOT NULL,
        id_produk INT NOT NULL,
        tanggal_pengadaan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        jumlah INT NOT NULL,
        harga_satuan DECIMAL(12,2) NOT NULL,
        total_harga DECIMAL(14,2) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'Diajukan\',
        catatan VARCHAR(255) NULL,
        FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB');
    pastikan_kolom_pengadaan();
}

/** Tambahkan kolom resi, kurir, dan tanggal terima ke tabel pengadaan bila belum ada. */
function pastikan_kolom_pengadaan(): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    try {
        db()->query('SELECT no_resi, kurir, tanggal_diterima FROM pengadaan LIMIT 0');
    } catch (PDOException $ex) {
        db()->exec('ALTER TABLE pengadaan
            ADD COLUMN no_resi VARCHAR(60) NULL,
            ADD COLUMN kurir VARCHAR(60) NULL,
            ADD COLUMN tanggal_diterima DATETIME NULL');
    }
    $sudah = true;
}

/** Tambahkan kolom provinsi & kode pos ke tabel pelanggan bila belum ada (database lama). */
function pastikan_kolom_alamat(): void
{
    try {
        db()->query('SELECT provinsi, kode_pos FROM pelanggan LIMIT 0');
    } catch (PDOException $ex) {
        db()->exec('ALTER TABLE pelanggan
            ADD COLUMN provinsi VARCHAR(60) NULL AFTER alamat,
            ADD COLUMN kode_pos VARCHAR(10) NULL AFTER provinsi');
    }
}

/** Tambahkan kolom pengiriman (nomor resi, kurir, tanggal kirim & terima) ke tabel pesanan bila belum ada. */
function pastikan_kolom_pengiriman(): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    try {
        db()->query('SELECT no_resi, kurir, tanggal_kirim, tanggal_diterima FROM pesanan LIMIT 0');
    } catch (PDOException $ex) {
        db()->exec('ALTER TABLE pesanan
            ADD COLUMN no_resi VARCHAR(60) NULL,
            ADD COLUMN kurir VARCHAR(60) NULL,
            ADD COLUMN tanggal_kirim DATETIME NULL,
            ADD COLUMN tanggal_diterima DATETIME NULL');
    }
    $sudah = true;
}

/** Tambahkan kolom pembayaran Midtrans ke tabel pesanan bila belum ada (database lama). */
function pastikan_kolom_pembayaran(): void
{
    try {
        db()->query('SELECT midtrans_order_id, snap_token, status_bayar FROM pesanan LIMIT 0');
    } catch (PDOException $ex) {
        db()->exec("ALTER TABLE pesanan
            ADD COLUMN midtrans_order_id VARCHAR(64) NULL,
            ADD COLUMN snap_token VARCHAR(100) NULL,
            ADD COLUMN status_bayar VARCHAR(30) NOT NULL DEFAULT 'Belum dibayar',
            ADD UNIQUE KEY uq_midtrans_order (midtrans_order_id)");
    }
    pastikan_tabel_pembayaran();
}

/**
 * Buat tabel pembayaran bila belum ada (database lama tidak perlu diimpor ulang).
 * Satu pesanan = satu catatan pembayaran. Pesanan lama ikut dicatat saat tabel pertama kali dibuat.
 * Harus dipanggil di luar transaksi (CREATE TABLE menutup transaksi yang sedang berjalan).
 */
function pastikan_tabel_pembayaran(): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    $pdo = db();
    $ada = $pdo->query("SHOW TABLES LIKE 'pembayaran'")->fetchColumn() !== false;
    if (!$ada) {
        $pdo->exec('CREATE TABLE pembayaran (
            id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
            id_pesanan INT NOT NULL,
            metode VARCHAR(50) NOT NULL,
            midtrans_order_id VARCHAR(64) NULL,
            midtrans_transaction_id VARCHAR(64) NULL,
            tipe_pembayaran VARCHAR(40) NULL,
            bank VARCHAR(40) NULL,
            jumlah DECIMAL(12,2) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'Belum dibayar\',
            status_transaksi VARCHAR(30) NULL,
            fraud_status VARCHAR(30) NULL,
            waktu_bayar DATETIME NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            respons_midtrans TEXT NULL,
            UNIQUE KEY uq_pembayaran_pesanan (id_pesanan),
            UNIQUE KEY uq_pembayaran_order (midtrans_order_id),
            FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $pdo->exec('INSERT IGNORE INTO pembayaran (id_pesanan, metode, midtrans_order_id, jumlah, status)
            SELECT id_pesanan, metode_pembayaran, midtrans_order_id, total_harga, status_bayar FROM pesanan');
    }
    $sudah = true;
}

/**
 * Buat/perbarui catatan pembayaran sebuah pesanan. Kolom yang diizinkan saja yang dipakai;
 * waktu_bayar hanya diisi sekali (nilai pertama bertahan).
 */
function simpan_pembayaran(int $idPesanan, array $data): void
{
    $pdo = db();
    // Pastikan catatan ada (INSERT IGNORE: aman bila sudah ada karena id_pesanan unik).
    $pdo->prepare('INSERT IGNORE INTO pembayaran (id_pesanan, metode, jumlah, status)
        SELECT o.id_pesanan, o.metode_pembayaran, o.total_harga, o.status_bayar FROM pesanan o WHERE o.id_pesanan = ?')
        ->execute([$idPesanan]);

    $boleh = ['metode', 'midtrans_order_id', 'midtrans_transaction_id', 'tipe_pembayaran', 'bank',
        'jumlah', 'status', 'status_transaksi', 'fraud_status', 'waktu_bayar', 'respons_midtrans'];
    $set = [];
    $nilai = [];
    foreach ($data as $kolom => $v) {
        if (!in_array($kolom, $boleh, true)) {
            continue;
        }
        $set[] = $kolom === 'waktu_bayar' ? 'waktu_bayar = COALESCE(waktu_bayar, ?)' : $kolom . ' = ?';
        $nilai[] = $v;
    }
    if ($set) {
        $nilai[] = $idPesanan;
        $pdo->prepare('UPDATE pembayaran SET ' . implode(', ', $set) . ' WHERE id_pesanan = ?')->execute($nilai);
    }
}

/** Kembalikan stok semua item sebuah pesanan (dipakai setiap kali pesanan dibatalkan). */
function kembalikan_stok(int $idPesanan): void
{
    db()->prepare('UPDATE produk p JOIN detail_pesanan d ON d.id_produk = p.id_produk
        SET p.stok = p.stok + d.jumlah WHERE d.id_pesanan = ?')->execute([$idPesanan]);
}

/**
 * Pelanggan membatalkan pesanannya sendiri. Hanya pesanan COD berstatus Pending yang boleh,
 * karena pesanan Midtrans bisa saja dibayar bersamaan (batalnya lewat kedaluwarsa atau admin).
 * Mengembalikan true bila pesanan benar-benar dibatalkan dan stok dikembalikan.
 */
function batalkan_pesanan_pelanggan(int $idPesanan, int $idPelanggan): bool
{
    pastikan_kolom_pembayaran();
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT status, metode_pembayaran FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ? FOR UPDATE');
        $st->execute([$idPesanan, $idPelanggan]);
        $o = $st->fetch();
        if (!$o || $o['status'] !== 'Pending' || $o['metode_pembayaran'] !== COD_LABEL) {
            $pdo->commit();
            return false;
        }
        $pdo->prepare('UPDATE pesanan SET status = ? WHERE id_pesanan = ?')->execute(['Dibatalkan', $idPesanan]);
        kembalikan_stok($idPesanan);
        $pdo->prepare('UPDATE pembayaran SET status = ? WHERE id_pesanan = ? AND status <> ?')->execute(['Dibatalkan', $idPesanan, 'Lunas']);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

// ---------- Peran dari akhiran email ----------
const DOMAIN_ADMIN = 'admin.com';
const DOMAIN_SUPPLIER = 'supplier.com';

/** Pecah email menjadi [peran, username]. Peran: admin | supplier | pelanggan. */
function peran_dari_email(string $email): array
{
    if (preg_match('/^([^@\s]+)@(' . preg_quote(DOMAIN_ADMIN, '/') . '|' . preg_quote(DOMAIN_SUPPLIER, '/') . ')$/i', $email, $m)) {
        return [strtolower($m[2]) === DOMAIN_ADMIN ? 'admin' : 'supplier', $m[1]];
    }
    return ['pelanggan', $email];
}

// ---------- Lupa kata sandi (khusus pelanggan) ----------
/** Maksimal verifikasi gagal per email atau per alamat IP dalam jendela waktu di bawah. */
const RESET_MAKS_GAGAL = 5;
const RESET_JENDELA_MENIT = 15;
/** Lama pelanggan boleh membuat kata sandi baru setelah verifikasi berhasil. */
const RESET_BERLAKU_MENIT = 10;

/** Buat tabel pencatat verifikasi gagal bila belum ada (database lama tidak perlu diimpor ulang). */
function pastikan_tabel_percobaan_reset(): void
{
    static $sudah = false;
    if ($sudah) {
        return;
    }
    db()->exec('CREATE TABLE IF NOT EXISTS percobaan_reset (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reset_email (email, waktu),
        KEY idx_reset_ip (ip, waktu)
    ) ENGINE=InnoDB');
    $sudah = true;
}

/** Samakan format nomor HP (08xx, 628xx, +62 8xx, 8xx) menjadi 08xx agar bisa dibandingkan. */
function normalisasi_hp(string $hp): string
{
    $d = preg_replace('/\D+/', '', $hp);
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '62')) {
        return '0' . substr($d, 2);
    }
    return $d[0] === '0' ? $d : '0' . $d;
}

function reset_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** True bila email atau IP ini sudah terlalu sering gagal verifikasi dalam beberapa menit terakhir. */
function reset_diblokir(string $email): bool
{
    pastikan_tabel_percobaan_reset();
    db()->exec('DELETE FROM percobaan_reset WHERE waktu < NOW() - INTERVAL 1 DAY');
    $st = db()->prepare('SELECT COUNT(*) FROM percobaan_reset
        WHERE waktu > NOW() - INTERVAL ' . RESET_JENDELA_MENIT . ' MINUTE AND (email = ? OR ip = ?)');
    $st->execute([substr($email, 0, 100), reset_ip()]);
    return (int)$st->fetchColumn() >= RESET_MAKS_GAGAL;
}

function reset_catat_gagal(string $email): void
{
    pastikan_tabel_percobaan_reset();
    db()->prepare('INSERT INTO percobaan_reset (email, ip) VALUES (?, ?)')->execute([substr($email, 0, 100), reset_ip()]);
}

/**
 * Cari pelanggan yang email dan nomor HP-nya cocok. Mengembalikan id_pelanggan, atau null bila tidak cocok.
 * Akun tanpa nomor HP tidak bisa diverifikasi.
 */
function reset_verifikasi(string $email, string $hp): ?int
{
    $st = db()->prepare('SELECT id_pelanggan, no_hp FROM pelanggan WHERE email = ?');
    $st->execute([$email]);
    $p = $st->fetch();
    $tersimpan = $p ? normalisasi_hp((string)$p['no_hp']) : '';
    $input = normalisasi_hp($hp);
    if ($p && strlen($tersimpan) >= 9 && hash_equals($tersimpan, $input)) {
        return (int)$p['id_pelanggan'];
    }
    return null;
}

// ---------- Helper umum ----------
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

/**
 * Format tanggal dengan nama bulan Indonesia, mis. "17 Agu 2026, 13:45".
 * $waktu: teks tanggal dari database (atau 'now'); kosong menghasilkan "—".
 * $format memakai kode date() PHP; huruf M diganti singkatan bulan Indonesia.
 */
function tanggal_id(?string $waktu, string $format = 'd M Y'): string
{
    $ts = trim((string)$waktu) === '' ? false : strtotime((string)$waktu);
    if ($ts === false) {
        return '—';
    }
    static $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $hasil = '';
    foreach (mb_str_split($format) as $kode) {
        $hasil .= $kode === 'M' ? $bulan[(int)date('n', $ts)] : date($kode, $ts);
    }
    return $hasil;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Simpan pesan sekali-tampil (dengan $msg) atau ambil pesan yang tersimpan (tanpa argumen). */
function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CSRF ----------
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
        if (!$ok) {
            http_response_code(400);
            exit('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}

// ---------- Tampilan produk & pesanan ----------
/** URL relatif gambar (dari root proyek) sesuai urutan FOLDER_GAMBAR, atau null bila tidak ada. */
function url_gambar(string $file): ?string
{
    $file = basename($file);
    if ($file === '') {
        return null;
    }
    foreach (FOLDER_GAMBAR as $folder) {
        if (is_file(__DIR__ . '/' . $folder . '/' . $file)) {
            return str_replace(' ', '%20', $folder) . '/' . rawurlencode($file);
        }
    }
    return null;
}

/** Gambar produk dengan kanvas display seragam, atau placeholder bila aset tidak ditemukan. */
function gambar_produk(?string $file, string $nama, string $base = ''): string
{
    $url = $file ? url_gambar($file) : null;
    if ($url !== null) {
        return '<img src="' . e($base . $url) . '" alt="' . e($nama) . '" loading="lazy">';
    }
    // Placeholder: siluet iPhone konsisten per produk.
    $hue = [215, 150, 335, 48, 265][crc32($nama) % 5];
    return '<div class="ph" style="--h:' . $hue . '" role="img" aria-label="' . e($nama) . '"></div>';
}

/** Ringkasan spesifikasi untuk kartu produk, mis. "128GB · A18". */
function spek_ringkas(?string $deskripsi): string
{
    $out = [];
    foreach (preg_split('/\R/', (string)$deskripsi) as $baris) {
        if (preg_match('/^\s*(Storage|Chip)\s*:\s*(.+)$/i', $baris, $m)) {
            $out[] = trim($m[2]);
        }
    }
    return implode(' · ', $out);
}

/** Label metode pembayaran untuk ditampilkan. */
function label_metode(?string $metode): string
{
    $metode = trim((string)$metode);
    return $metode !== '' ? $metode : 'Belum tercatat';
}

/** Metode pembayaran + status bayar untuk pesanan Midtrans, mis. "Midtrans · Lunas". */
function label_pembayaran(array $pesanan): string
{
    $label = label_metode($pesanan['metode_pembayaran'] ?? '');
    $statusBayar = trim((string)($pesanan['status_bayar'] ?? ''));
    return ($label === MIDTRANS_LABEL && $statusBayar !== '') ? $label . ' · ' . $statusBayar : $label;
}

/** Validasi isian provinsi & kode pos (keduanya opsional). Mengembalikan pesan galat atau null. */
function validasi_wilayah(string $provinsi, string $kodePos): ?string
{
    if ($provinsi !== '' && !in_array($provinsi, PROVINSI, true)) {
        return 'Provinsi tidak valid. Pilih dari daftar yang tersedia.';
    }
    if ($kodePos !== '' && !preg_match('/^\d{5}$/', $kodePos)) {
        return 'Kode pos harus 5 digit angka.';
    }
    return null;
}

/**
 * Hitung halaman aktif dari parameter ?hal= untuk daftar berpaginasi.
 * Mengembalikan [halaman, totalHalaman, offset]; halaman di luar rentang dirapikan otomatis.
 */
function paginasi(int $total, int $perHalaman = 10): array
{
    $totalHalaman = max(1, (int)ceil($total / $perHalaman));
    $halaman = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHalaman);
    return [$halaman, $totalHalaman, ($halaman - 1) * $perHalaman];
}

/** Pola LIKE aman: karakter % _ \ dari pengguna diperlakukan sebagai teks biasa. */
function pola_cari(string $kata): string
{
    return '%' . addcslashes($kata, '%_\\') . '%';
}

// ---------- Pelanggan & keranjang ----------
function pelanggan_login(): ?array
{
    if (empty($_SESSION['pelanggan_id'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([$_SESSION['pelanggan_id']]);
    return $st->fetch() ?: null;
}

function wajib_pelanggan(): array
{
    $p = pelanggan_login();
    if (!$p) {
        flash('Silakan masuk dulu untuk melanjutkan.', 'err');
        redirect('login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME'])));
    }
    return $p;
}

function jumlah_keranjang(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

/**
 * Rincian isi keranjang.
 * Mengembalikan [daftar produk (+ jumlah, subtotal, belum_dihargai), total, ada produk tanpa harga].
 */
function ringkasan_keranjang(array $cart): array
{
    $items = [];
    $total = 0;
    $adaHargaKosong = false;

    if ($cart) {
        $in = implode(',', array_fill(0, count($cart), '?'));
        $st = db()->prepare("SELECT * FROM produk WHERE id_produk IN ($in)");
        $st->execute(array_keys($cart));
        foreach ($st->fetchAll() as $p) {
            $p['jumlah'] = $cart[$p['id_produk']];
            $p['belum_dihargai'] = (float)$p['harga'] <= 0;
            if ($p['belum_dihargai']) {
                $adaHargaKosong = true;
                $p['subtotal'] = 0;
            } else {
                $p['subtotal'] = $p['jumlah'] * $p['harga'];
                $total += $p['subtotal'];
            }
            $items[] = $p;
        }
    }

    return [$items, $total, $adaHargaKosong];
}
