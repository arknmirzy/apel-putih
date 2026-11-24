<?php
require_once __DIR__ . '/../includes/html.php';

function current_supplier(): ?array
{
    $id = (int)($_SESSION['supplier_id'] ?? 0);
    if ($id < 1) {
        return null;
    }

    $st = db()->prepare('SELECT id_supplier, nama_supplier, no_telp, alamat, username FROM supplier WHERE id_supplier = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function wajib_supplier(): array
{
    $supplier = current_supplier();
    if (!$supplier) {
        unset($_SESSION['supplier_id'], $_SESSION['supplier_nama']);
        redirect('../login.php');
    }
    return $supplier;
}

function supplier_header(string $title, string $active, array $supplier): void
{
    $f = flash();
    html_head($title . ' · Supplier ' . STORE_NAME, '../');
    ?>
<body class="supplier-body">
<div class="supplier-shell">
  <aside class="supplier-side">
    <div class="supplier-side-top">
      <a class="brand" href="index.php"><span class="dot"></span><?= e(STORE_NAME) ?></a>
      <span class="supplier-badge">Supplier</span>
    </div>
    <div class="supplier-profile">
      <span class="supplier-avatar"><?= e(strtoupper(substr($supplier['nama_supplier'], 0, 1))) ?></span>
      <div><b><?= e($supplier['nama_supplier']) ?></b><small>Portal pemasok</small></div>
    </div>
    <nav class="supplier-nav">
      <a class="<?= $active === 'dashboard' ? 'on' : '' ?>" href="index.php"><?= icon('home') ?><span>Ringkasan</span></a>
      <a href="index.php#produk"><?= icon('produk') ?><span>Produk saya</span></a>
      <a href="index.php#pengadaan"><?= icon('supplier') ?><span>Pengadaan</span></a>
      <a href="index.php#pesanan"><?= icon('pesanan') ?><span>Pesanan produk</span></a>
    </nav>
    <div class="supplier-side-foot">
      <a href="../index.php" target="_blank" rel="noopener" title="Lihat toko"><?= icon('store') ?><span>Lihat toko</span></a>
      <a class="out" href="logout.php" title="Keluar"><?= icon('out') ?><span>Keluar</span></a>
    </div>
  </aside>
  <main class="supplier-main">
    <?php if ($f): ?><div class="flash <?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div><?php endif; ?>
<?php
}

function supplier_footer(): void
{
    echo "  </main>\n</div>\n";
    html_scripts('../');
}
