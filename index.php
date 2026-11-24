<?php
require_once __DIR__ . '/includes/layout.php';

$q = trim($_GET['q'] ?? '');
$kat = (int)($_GET['kategori'] ?? 0);

$sql = 'SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori WHERE 1=1';
$args = [];
if ($q !== '') {
    $sql .= ' AND (p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if ($kat > 0) {
    $sql .= ' AND p.id_kategori = ?';
    $args[] = $kat;
}
$sql .= ' ORDER BY (p.harga <= 0) ASC, p.harga ASC, p.id_produk DESC';
$st = db()->prepare($sql);
$st->execute($args);
$produk = $st->fetchAll();
$kategori = db()->query('SELECT * FROM kategori ORDER BY id_kategori')->fetchAll();

// Hero hanya di beranda tanpa pencarian/filter: tampilkan produk unggulan (termahal) dan harga mulai dari.
$tampilHero = ($q === '' && $kat === 0);
if ($tampilHero) {
    $unggulan = db()->query('SELECT * FROM produk WHERE harga > 0 ORDER BY harga DESC, id_produk DESC LIMIT 1')->fetch();
    if (!$unggulan) {
        $unggulan = db()->query('SELECT * FROM produk ORDER BY id_produk DESC LIMIT 1')->fetch();
    }
    $mulai = db()->query('SELECT MIN(harga) FROM produk WHERE harga > 0')->fetchColumn();
}

page_header('Beranda', 'produk');
?>
<?php if ($tampilHero && $unggulan): ?>
<section class="hero">
  <div>
    <h1>Semua iPhone terbaru, disatu toko.</h1>
    <p>Pilih tipe dan kapasitas yang kamu mau, pesan online, lalu kami siapkan pesananmu.</p>
    <div class="go">
      <a class="btn" href="#produk">Lihat semua iPhone</a>
      <span class="from">Mulai dari <b><?= $mulai !== false && (float)$mulai > 0 ? rupiah($mulai) : 'Harga belum ditentukan' ?></b></span>
    </div>
  </div>
  <a class="stage" href="produk.php?id=<?= (int)$unggulan['id_produk'] ?>" aria-label="Lihat <?= e($unggulan['nama_produk']) ?>">
    <?= gambar_produk($unggulan['gambar'], $unggulan['nama_produk']) ?>
    <span class="tag"><small>Tipe teratas</small><b><?= e($unggulan['nama_produk']) ?></b></span>
  </a>
</section>
<?php else: ?>
<h1>Produk</h1>
<p class="sub"><?= $q !== '' ? 'Hasil untuk “' . e($q) . '”: ' . count($produk) . ' produk' : 'Pilih iPhone yang kamu mau.' ?></p>
<?php endif; ?>

<?php if ($tampilHero): ?>
<section class="tour" id="tur" aria-labelledby="tur-judul">
  <div class="tour-head">
    <h2 id="tur-judul">Lihat tokonya dalam 38 detik</h2>
    <p>Dari beranda sampai panel admin, ini alur lengkap Apel Putih.</p>
  </div>
  <div class="tour-body">
    <div class="tour-player" data-tour>
      <video class="tour-video" preload="metadata" playsinline poster="assets/video/apel-putih-poster.jpg?v=<?= ASSET_VERSION ?>" aria-label="Video tur Apel Putih">
        <source src="assets/video/apel-putih-preview.mp4?v=<?= ASSET_VERSION ?>" type="video/mp4">
        Browser kamu belum mendukung pemutar video.
      </video>
      <button type="button" class="tour-big" aria-label="Putar video">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
      </button>
      <div class="tour-bar">
        <button type="button" class="tour-btn tour-toggle" aria-label="Putar">
          <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
          <svg class="i-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1.2"/><rect x="14" y="5" width="4" height="14" rx="1.2"/></svg>
        </button>
        <span class="tour-time"><span class="tour-now">0:00</span><span class="tour-sep"> / </span><span class="tour-total">0:38</span></span>
        <input class="tour-seek" type="range" min="0" max="1000" value="0" step="1" aria-label="Posisi video">
        <button type="button" class="tour-btn tour-mute" aria-label="Matikan suara">
          <svg class="i-vol" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.5l4.5 3.8V5.7L7.5 9.5H4z"/><path d="M16 9a4.5 4.5 0 0 1 0 6" fill="none" stroke-width="1.8" stroke-linecap="round"/></svg>
          <svg class="i-muted" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.5l4.5 3.8V5.7L7.5 9.5H4z"/><path d="M16 9.5l5 5M21 9.5l-5 5" fill="none" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
        <button type="button" class="tour-btn tour-full" aria-label="Layar penuh">
          <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
        </button>
      </div>
    </div>
    <ol class="tour-chapters" aria-label="Bab video">
      <li><button type="button" data-start="0"><span class="t">0:00</span><span class="n">Beranda</span><span class="d">Tampilan depan toko dan tipe teratas.</span></button></li>
      <li><button type="button" data-start="8.2"><span class="t">0:08</span><span class="n">Katalog produk</span><span class="d">Pilih tipe, kapasitas, dan warna.</span></button></li>
      <li><button type="button" data-start="12.8"><span class="t">0:13</span><span class="n">Keranjang dan checkout</span><span class="d">Pilih kurir dan bayar lewat Midtrans atau COD.</span></button></li>
      <li><button type="button" data-start="17.7"><span class="t">0:18</span><span class="n">Panel admin</span><span class="d">Dashboard, grafik untung, dan detail pesanan.</span></button></li>
      <li><button type="button" data-start="30"><span class="t">0:30</span><span class="n">Riwayat pesanan</span><span class="d">Lihat resi lalu konfirmasi pesanan diterima.</span></button></li>
    </ol>
  </div>
</section>
<?php endif; ?>

<nav class="chips" id="produk" aria-label="Kategori">
  <a href="index.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>#produk" class="<?= $kat === 0 ? 'on' : '' ?>" data-id="0">Semua</a>
  <?php foreach ($kategori as $k): ?>
    <a href="index.php?kategori=<?= (int)$k['id_kategori'] ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>#produk"
       class="<?= $kat === (int)$k['id_kategori'] ? 'on' : '' ?>" data-id="<?= (int)$k['id_kategori'] ?>"><?= e($k['nama_kategori']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$produk): ?>
  <div class="empty">Produk tidak ditemukan. Coba kata kunci lain atau <a href="index.php">lihat semua iPhone</a>.</div>
<?php else: ?>
  <div class="catalog-bar">
    <span class="catalog-count" id="jumlah-produk" aria-live="polite"><?= count($produk) ?> produk</span>
  </div>
  <div class="grid">
    <?php foreach ($produk as $p): ?>
      <a class="card" href="produk.php?id=<?= (int)$p['id_produk'] ?>" data-kategori="<?= (int)$p['id_kategori'] ?>"
         data-id="<?= (int)$p['id_produk'] ?>" data-harga="<?= (float)$p['harga'] ?>" data-stok="<?= (int)$p['stok'] ?>">
        <div class="thumb"><?= gambar_produk($p['gambar'], $p['nama_produk']) ?></div>
        <div class="info">
          <div class="nm"><?= e($p['nama_produk']) ?></div>
          <?php if ($sp = spek_ringkas($p['deskripsi'])): ?><div class="sp"><?= e($sp) ?></div><?php endif; ?>
          <div class="pr"><?= (float)$p['harga'] > 0 ? rupiah($p['harga']) : 'Harga belum ditentukan' ?></div>
          <?php if ($p['stok'] <= 0): ?>
            <span class="stok out">Stok habis</span>
          <?php elseif ($p['stok'] <= 5): ?>
            <span class="stok low">Sisa <?= (int)$p['stok'] ?> unit</span>
          <?php else: ?>
            <span class="stok">Tersedia</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php page_footer();
