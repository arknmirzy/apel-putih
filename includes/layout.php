<?php
require_once __DIR__ . '/html.php';

function page_header(string $title, string $active = ''): void
{
    $pelanggan = pelanggan_login();
    $jumlahKeranjang = jumlah_keranjang();
    $flash = flash();

    html_head($title . ' · ' . STORE_NAME);
    $on = fn(string $key) => $active === $key ? 'on' : '';
    ?>
<body>
<header class="top">
  <div class="wrap bar">
    <a class="brand" href="index.php"><span class="dot"></span><?= e(STORE_NAME) ?></a>
    <form class="search" action="index.php" method="get" role="search">
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari iPhone…" aria-label="Cari produk" autocomplete="off">
    </form>
    <nav>
      <a href="index.php" class="<?= $on('produk') ?>">Produk</a>
      <a href="keranjang.php" class="<?= $on('keranjang') ?>">Keranjang<?= $jumlahKeranjang ? ' <span class="nav-count">' . $jumlahKeranjang . '</span>' : '' ?></a>
      <?php if ($pelanggan): ?>
        <a href="pesanan.php" class="<?= $on('pesanan') ?>">Pesanan</a>
        <a href="profil.php" class="<?= $on('profil') ?>">Profil</a>
        <a href="logout.php" title="Keluar dari <?= e($pelanggan['nama_pelanggan']) ?>">Keluar</a>
      <?php else: ?>
        <a href="login.php" class="<?= $on('login') ?>">Masuk</a>
        <a href="login.php?mode=daftar" class="btn sm">Daftar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="foot">
  <div class="wrap foot-content">
    <div class="foot-left">
      <span class="foot-brand"><?= e(STORE_NAME) ?></span>
      <p class="foot-desc">Toko iPhone bergaransi resmi. Kualitas terpercaya, proses cepat, dan harga transparan.</p>
    </div>
    <div class="foot-right">
      <span>© <?= date('Y') ?> <?= e(STORE_NAME) ?></span>
    </div>
  </div>
</footer>
<?php
    html_scripts();
}
