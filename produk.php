<?php
require_once __DIR__ . '/includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT p.*, k.nama_kategori, b.nama_brand
    FROM produk p
    JOIN kategori k ON k.id_kategori = p.id_kategori
    JOIN brand b ON b.id_brand = p.id_brand
    WHERE p.id_produk = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    page_header('Produk tidak ditemukan', 'produk');
    echo '<div class="empty">Produk tidak ditemukan. <a href="index.php">Kembali ke beranda</a></div>';
    page_footer();
    exit;
}

$spek = [];
$catatan = [];
foreach (preg_split('/\R/', (string)$p['deskripsi']) as $baris) {
    $baris = trim($baris);
    if ($baris === '') {
        continue;
    }
    if (strpos($baris, ':') !== false) {
        [$k, $v] = array_map('trim', explode(':', $baris, 2));
        $spek[$k] = $v;
    } else {
        $catatan[] = $baris;
    }
}

page_header($p['nama_produk'], 'produk');
?>
<a class="back" href="index.php">‹ Semua iPhone</a>
<div class="detail">
  <div class="thumb"><?= gambar_produk($p['gambar'], $p['nama_produk']) ?></div>
  <div>
    <div class="muted"><?= e($p['nama_brand']) ?> · <?= e($p['nama_kategori']) ?></div>
    <h1><?= e($p['nama_produk']) ?></h1>
    <div class="price"><?= (float)$p['harga'] > 0 ? rupiah($p['harga']) : 'Harga belum ditentukan' ?></div>
    <?php if ($p['stok'] <= 0): ?>
      <span class="stok out">Stok habis</span>
    <?php elseif ($p['stok'] <= 5): ?>
      <span class="stok low">Tersisa <?= (int)$p['stok'] ?> unit</span>
    <?php else: ?>
      <span class="stok">Stok tersedia (<?= (int)$p['stok'] ?> unit)</span>
    <?php endif; ?>

    <?php if ($spek): ?>
      <table class="spec">
        <?php foreach ($spek as $k => $v): ?>
          <tr><td><?= e($k) ?></td><td><?= e($v) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <?php foreach ($catatan as $c): ?><p><?= e($c) ?></p><?php endforeach; ?>

    <?php if ((float)$p['harga'] <= 0): ?>
      <p class="muted price-note">Harga produk ini belum ditentukan. Silakan hubungi admin atau cek kembali nanti.</p>
      <button class="btn" type="button" disabled>Harga belum ditentukan</button>
    <?php else: ?>
      <form class="buy" action="keranjang.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="id" value="<?= (int)$p['id_produk'] ?>">
        <input type="number" name="jumlah" value="1" min="1" max="<?= max(1, (int)$p['stok']) ?>" aria-label="Jumlah" <?= $p['stok'] <= 0 ? 'disabled' : '' ?>>
        <button class="btn" <?= $p['stok'] <= 0 ? 'disabled' : '' ?>>Tambah ke keranjang</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
