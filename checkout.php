<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/midtrans.php';

pastikan_kolom_alamat();
pastikan_kolom_pembayaran();
pastikan_kolom_pengiriman();
$user = wajib_pelanggan();
$cartSemua = $_SESSION['cart'] ?? [];
if (!$cartSemua) {
    flash('Keranjang kosong.', 'err');
    redirect('keranjang.php');
}

// Hanya produk yang dicentang di keranjang yang di-checkout. Tanpa pilihan tersimpan,
// seluruh isi keranjang dipakai seperti sebelumnya.
$pilih = array_map('intval', (array)($_SESSION['checkout_ids'] ?? []));
$cart = $pilih ? array_intersect_key($cartSemua, array_flip($pilih)) : $cartSemua;
if (!$cart) {
    unset($_SESSION['checkout_ids']);
    flash('Produk yang dipilih sudah tidak ada di keranjang. Pilih lagi produk yang ingin di-checkout.', 'err');
    redirect('keranjang.php');
}

/**
 * Metode pembayaran yang tersedia. Satu sumber data untuk validasi, tampilan kartu,
 * label yang disimpan di pesanan, dan pesan sukses ('%d' = nomor pesanan).
 */
$metodeBayar = [
    'midtrans' => [
        'label' => MIDTRANS_LABEL,
        'judul' => 'Midtrans',
        'badge' => ['Diproses', 'Online'],
        'desc'  => 'Bayar online: QRIS, Virtual Account, e-wallet, atau kartu.',
        'ikon'  => 'midtrans',
        'svg'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'status_bayar' => 'Belum dibayar',
        'pesan' => 'Pesanan #%d berhasil dibuat. Selesaikan pembayaran lewat Midtrans. Terima kasih!',
    ],
    'cod' => [
        'label' => COD_LABEL,
        'judul' => 'COD (Bayar di Tempat)',
        'badge' => ['Pending', 'Tunai'],
        'desc'  => 'Bayar langsung ke kurir saat paket diterima.',
        'ikon'  => 'cod',
        'svg'   => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'status_bayar' => 'Bayar di tempat',
        'pesan' => 'Pesanan #%d berhasil dibuat (COD). Bayar saat pesanan tiba. Terima kasih!',
    ],
];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $alamat = trim($_POST['alamat'] ?? '');
    $hp = trim($_POST['hp'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kodePos = trim($_POST['kode_pos'] ?? '');
    $metode = $_POST['metode'] ?? 'midtrans';
    $kurir = trim($_POST['kurir'] ?? '');

    if ($alamat === '' || $hp === '') {
        $err = 'Alamat pengiriman dan No. HP wajib diisi.';
    } elseif (!in_array($kurir, KURIR_OPSI, true)) {
        $err = 'Pilih kurir pengiriman terlebih dahulu.';
    } elseif ($galat = validasi_wilayah($provinsi, $kodePos)) {
        $err = $galat;
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $ids = array_keys($cart);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT * FROM produk WHERE id_produk IN ($in) FOR UPDATE");
            $st->execute($ids);
            $produk = [];
            foreach ($st->fetchAll() as $p) {
                $produk[$p['id_produk']] = $p;
            }

            $total = 0;
            foreach ($cart as $id => $jml) {
                if (!isset($produk[$id]) || $produk[$id]['stok'] < $jml) {
                    throw new RuntimeException('Stok "' . ($produk[$id]['nama_produk'] ?? 'produk') . '" tidak mencukupi. Silakan periksa keranjang.');
                }
                if ((float)$produk[$id]['harga'] <= 0) {
                    throw new RuntimeException('Harga produk "' . $produk[$id]['nama_produk'] . '" belum ditentukan. Admin harus mengisi harga sebelum pesanan dibuat.');
                }
                $total += $produk[$id]['harga'] * $jml;
            }

            // Tolak metode pembayaran yang tidak termasuk pilihan aktif.
            if (!isset($metodeBayar[$metode])) {
                throw new RuntimeException('Metode pembayaran tidak valid. Silakan pilih metode yang tersedia.');
            }
            if ($metode === 'midtrans' && !midtrans_siap()) {
                throw new RuntimeException('Pembayaran Midtrans belum aktif di toko ini. Silakan pilih COD atau hubungi admin.');
            }

            $pdo->prepare('UPDATE pelanggan SET alamat = ?, no_hp = ?, provinsi = ?, kode_pos = ? WHERE id_pelanggan = ?')
                ->execute([$alamat, $hp, $provinsi ?: null, $kodePos ?: null, $user['id_pelanggan']]);
            $pdo->prepare('INSERT INTO pesanan (id_pelanggan, total_harga, status, metode_pembayaran, status_bayar, kurir) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$user['id_pelanggan'], $total, 'Pending', $metodeBayar[$metode]['label'], $metodeBayar[$metode]['status_bayar'], $kurir]);
            $idPesanan = (int)$pdo->lastInsertId();
            simpan_pembayaran($idPesanan, [
                'metode' => $metodeBayar[$metode]['label'],
                'jumlah' => $total,
                'status' => $metodeBayar[$metode]['status_bayar'],
            ]);

            $ins = $pdo->prepare('INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan, subtotal) VALUES (?,?,?,?,?)');
            $kurangi = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id_produk = ?');
            foreach ($cart as $id => $jml) {
                $harga = $produk[$id]['harga'];
                $ins->execute([$idPesanan, $id, $jml, $harga, $harga * $jml]);
                $kurangi->execute([$jml, $id]);
            }
            $pdo->commit();

            // Hanya produk yang dibeli yang dikeluarkan; sisanya tetap di keranjang.
            foreach (array_keys($cart) as $idDibeli) {
                unset($_SESSION['cart'][$idDibeli]);
            }
            if (empty($_SESSION['cart'])) {
                unset($_SESSION['cart']);
            }
            unset($_SESSION['checkout_ids']);

            if ($metode === 'midtrans') {
                // Pesanan sudah tersimpan; bila token gagal dibuat, pelanggan bisa membayar lagi dari halaman Pesanan.
                [$token, $galat] = midtrans_buat_token($idPesanan);
                if ($token) {
                    redirect('bayar.php?id=' . $idPesanan);
                }
                flash('Pesanan #' . $idPesanan . ' dibuat, tetapi pembayaran belum bisa dibuka: ' . $galat . ' Coba lagi dari tombol Bayar sekarang.', 'err');
                redirect('pesanan.php');
            }

            flash(sprintf($metodeBayar[$metode]['pesan'], $idPesanan));
            redirect('pesanan.php');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $err = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'Pesanan gagal diproses. Coba lagi sebentar.';
        }
    }
}

[$items, $total, $adaHargaBelumDitentukan] = ringkasan_keranjang($cart);

$postedMetode = $_POST['metode'] ?? 'midtrans';
$postedKurir = $_POST['kurir'] ?? '';
$selectedMetode = isset($metodeBayar[$postedMetode]) ? $postedMetode : 'midtrans';

page_header('Checkout', 'keranjang');
?>
<h1>Checkout</h1>
<p class="sub">Satu langkah lagi, isi data pengiriman dan pilih metode pembayaran.</p>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<div class="two">
  <form method="post" class="panel">
    <?= csrf_field() ?>
    <label for="nama">Nama</label>
    <input id="nama" value="<?= e($user['nama_pelanggan']) ?>" disabled>
    <label for="kurir">Kurir pengiriman</label>
    <select id="kurir" name="kurir" required>
      <option value="" disabled <?= in_array($postedKurir, KURIR_OPSI, true) ? '' : 'selected' ?>>Pilih kurir</option>
      <?php foreach (KURIR_OPSI as $k): ?>
        <option value="<?= e($k) ?>" <?= $postedKurir === $k ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="hp">No. HP</label>
    <input id="hp" name="hp" value="<?= e($_POST['hp'] ?? $user['no_hp']) ?>" required autocomplete="tel">
    <label for="alamat">Alamat pengiriman</label>
    <textarea id="alamat" name="alamat" required autocomplete="street-address"><?= e($_POST['alamat'] ?? $user['alamat']) ?></textarea>
    <div class="row">
      <div>
        <label for="provinsi">Provinsi <span class="muted">— opsional</span></label>
        <select id="provinsi" name="provinsi" autocomplete="address-level1"><?= opsi_provinsi($_POST['provinsi'] ?? $user['provinsi']) ?></select>
      </div>
      <div>
        <label for="kode_pos">Kode pos <span class="muted">— opsional</span></label>
        <input id="kode_pos" name="kode_pos" value="<?= e($_POST['kode_pos'] ?? $user['kode_pos']) ?>" placeholder="12345" maxlength="5" pattern="[0-9]{5}" inputmode="numeric" autocomplete="postal-code" title="5 digit angka">
      </div>
    </div>

    <label class="pay-heading">Metode Pembayaran</label>
    <div class="pay-options">
      <?php foreach ($metodeBayar as $kode => $m): ?>
      <label class="pay-card <?= $selectedMetode === $kode ? 'selected' : '' ?>">
        <input type="radio" name="metode" value="<?= e($kode) ?>" <?= $selectedMetode === $kode ? 'checked' : '' ?>>
        <span class="pay-icon pay-icon--<?= e($m['ikon']) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $m['svg'] ?></svg>
        </span>
        <div class="pay-info">
          <?php if ($m['badge']): ?>
            <div class="pay-title-row">
              <b><?= e($m['judul']) ?></b>
              <span class="badge <?= e($m['badge'][0]) ?>"><?= e($m['badge'][1]) ?></span>
            </div>
          <?php else: ?>
            <b><?= e($m['judul']) ?></b>
          <?php endif; ?>
          <span class="muted"><?= e($m['desc']) ?></span>
        </div>
        <span class="pay-check">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </span>
      </label>
      <?php endforeach; ?>
    </div>

    <?php if ($adaHargaBelumDitentukan): ?>
      <div class="flash err checkout-warning">Checkout belum tersedia karena ada produk yang belum ditentukan harganya. Hubungi admin untuk memperbarui harga.</div>
    <?php endif; ?>
    <p class="checkout-submit"><button class="btn btn-block" <?= $adaHargaBelumDitentukan ? 'disabled' : '' ?>>Konfirmasi &amp; Buat Pesanan</button></p>
  </form>
  <div class="panel sum">
    <h2 class="sum-title">Ringkasan</h2>
    <?php if (count($cart) < count($cartSemua)): ?>
      <p class="muted cart-hint"><?= count($cart) ?> dari <?= count($cartSemua) ?> produk di keranjang. <a href="keranjang.php">Ubah pilihan</a></p>
    <?php endif; ?>
    <?php foreach ($items as $it): ?>
      <div><span><?= e($it['nama_produk']) ?> × <?= (int)$it['jumlah'] ?></span><span><?= $it['subtotal'] > 0 ? rupiah($it['subtotal']) : 'Harga belum ditentukan' ?></span></div>
    <?php endforeach; ?>
    <div class="tot"><span>Total</span><span><?= rupiah($total) ?></span></div>
  </div>
</div>
<?php page_footer();
