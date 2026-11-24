<?php
require_once __DIR__ . '/../includes/html.php';

function wajib_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        redirect('../login.php');
    }
}

/** Jalankan aksi hapus; beri pesan ramah bila masih dipakai tabel lain. */
function hapus_aman(string $sql, int $id, string $nama): void
{
    try {
        db()->prepare($sql)->execute([$id]);
        flash($nama . ' dihapus.');
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash($nama . ' tidak bisa dihapus karena masih dipakai data lain.', 'err');
        } else {
            flash('Gagal menghapus data.', 'err');
        }
    }
}

/** Kotak pencarian untuk daftar admin (GET ?q=); tombol Reset muncul saat sedang mencari. */
function admin_cari(string $q, string $placeholder): void
{
    echo '<form class="admin-search" method="get" role="search">'
        . '<input type="search" name="q" value="' . e($q) . '" placeholder="' . e($placeholder) . '" aria-label="Cari" autocomplete="off">'
        . '<button class="btn sm" type="submit">Cari</button>'
        . ($q !== '' ? '<a class="btn ghost sm" href="?">Reset</a>' : '')
        . "</form>\n";
}

function admin_header(string $title, string $active): void
{
    $f = flash();
    $menu = [
        'index' => ['index.php', 'Dashboard'],
        'produk' => ['produk.php', 'Produk'],
        'brand' => ['brand.php', 'Brand'],
        'kategori' => ['kategori.php', 'Kategori'],
        'pesanan' => ['pesanan.php', 'Pesanan'],
        'pelanggan' => ['pelanggan.php', 'Pelanggan'],
        'supplier' => ['supplier.php', 'Supplier'],
        'pengadaan' => ['pengadaan.php', 'Pengadaan'],
        'laporan' => ['laporan.php', 'Laporan'],
    ];
    html_head($title . ' · Admin ' . STORE_NAME, '../');
    ?>
<body class="admin-body">
<div class="admin-shell">
  <aside class="side">
    <div class="side-top">
      <a class="brand" href="index.php"><span class="dot"></span><?= e(STORE_NAME) ?></a>
      <span class="admin-badge">Admin</span>
    </div>
    <div class="side-label">Menu utama</div>
    <nav class="side-nav">
      <?php foreach ($menu as $k => [$href, $label]): ?>
        <a class="l <?= $active === $k ? 'on' : '' ?>" href="<?= $href ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= icon($k) ?><span><?= $label ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a class="l" href="../index.php" target="_blank" rel="noopener" title="Lihat toko"><?= icon('store') ?><span>Lihat toko</span></a>
      <a class="l out" href="logout.php" title="Keluar"><?= icon('out') ?><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">
    <?php if ($f): ?><div class="flash <?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div><?php endif; ?>
<?php
}

function admin_footer(): void
{
    echo "  </main>\n</div>\n";
    html_scripts('../');
}
