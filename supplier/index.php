<?php
require_once __DIR__ . '/_layout.php';
$supplier = wajib_supplier();
$pdo = db();
pastikan_tabel_pengadaan();
$idSupplier = (int)$supplier['id_supplier'];

// ---------- Aksi pengadaan (tambah / kirim / batalkan) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah_pengadaan') {
        $idProduk = (int)($_POST['id_produk'] ?? 0);
        $jumlah   = (int)($_POST['jumlah'] ?? 0);
        $harga    = (float)($_POST['harga_satuan'] ?? 0);
        $catatan  = trim($_POST['catatan'] ?? '');

        $cek = $pdo->prepare('SELECT 1 FROM produk WHERE id_produk = ? AND id_supplier = ?');
        $cek->execute([$idProduk, $idSupplier]);
        if (!$cek->fetchColumn()) {
            flash('Produk tidak valid untuk akun supplier ini.', 'err');
        } elseif ($jumlah < 1 || $jumlah > 100000) {
            flash('Jumlah harus antara 1 dan 100.000 unit.', 'err');
        } elseif ($harga <= 0) {
            flash('Harga satuan harus lebih dari 0.', 'err');
        } else {
            $pdo->prepare('INSERT INTO pengadaan (id_supplier, id_produk, jumlah, harga_satuan, total_harga, status, catatan) VALUES (?,?,?,?,?,?,?)')
                ->execute([$idSupplier, $idProduk, $jumlah, $harga, $jumlah * $harga, 'Diajukan', mb_substr($catatan, 0, 255) ?: null]);
            flash('Pengadaan berhasil diajukan.');
        }
    } elseif (in_array($aksi, ['kirim_pengadaan', 'batal_pengadaan'], true)) {
        $id = (int)($_POST['id_pengadaan'] ?? 0);
        if ($aksi === 'kirim_pengadaan') {
            $resi = mb_substr(trim((string)($_POST['no_resi'] ?? '')), 0, 60);
            $kurir = mb_substr(trim((string)($_POST['kurir'] ?? '')), 0, 60);
            $up = $pdo->prepare("UPDATE pengadaan SET status = 'Dikirim', no_resi = ?, kurir = ? WHERE id_pengadaan = ? AND id_supplier = ? AND status = 'Diajukan'");
            $up->execute([$resi ?: null, $kurir ?: null, $id, $idSupplier]);
            flash($up->rowCount() ? 'Status pengadaan diubah menjadi Dikirim.' : 'Pengadaan tidak dapat diubah.', $up->rowCount() ? 'ok' : 'err');
        } else {
            $up = $pdo->prepare("UPDATE pengadaan SET status = 'Dibatalkan' WHERE id_pengadaan = ? AND id_supplier = ? AND status IN ('Diajukan','Dikirim')");
            $up->execute([$id, $idSupplier]);
            flash($up->rowCount() ? 'Pengadaan dibatalkan.' : 'Pengadaan tidak dapat dibatalkan.', $up->rowCount() ? 'ok' : 'err');
        }
    }
    redirect('index.php#pengadaan');
}

$st = $pdo->prepare('SELECT COUNT(*) AS total_produk,
    COALESCE(SUM(stok), 0) AS total_stok,
    COALESCE(SUM(CASE WHEN stok <= 5 THEN 1 ELSE 0 END), 0) AS stok_menipis
    FROM produk WHERE id_supplier = ?');
$st->execute([$idSupplier]);
$produkStats = $st->fetch();

$st = $pdo->prepare("SELECT COUNT(DISTINCT CASE WHEN o.status = 'Pending' THEN o.id_pesanan END) AS pesanan_pending
    FROM detail_pesanan d
    JOIN produk p ON p.id_produk = d.id_produk
    JOIN pesanan o ON o.id_pesanan = d.id_pesanan
    WHERE p.id_supplier = ?");
$st->execute([$idSupplier]);
$orderStats = $st->fetch();

$st = $pdo->prepare('SELECT id_produk, nama_produk, harga, stok, gambar
    FROM produk WHERE id_supplier = ? ORDER BY (stok <= 5) DESC, nama_produk ASC LIMIT 12');
$st->execute([$idSupplier]);
$produk = $st->fetchAll();

$st = $pdo->prepare('SELECT o.id_pesanan, o.tanggal_pesanan, o.status,
    c.nama_pelanggan, p.nama_produk, d.jumlah, d.subtotal
    FROM detail_pesanan d
    JOIN produk p ON p.id_produk = d.id_produk
    JOIN pesanan o ON o.id_pesanan = d.id_pesanan
    JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan
    WHERE p.id_supplier = ?
    ORDER BY o.tanggal_pesanan DESC, o.id_pesanan DESC
    LIMIT 10');
$st->execute([$idSupplier]);
$pesanan = $st->fetchAll();

$st = $pdo->prepare('SELECT id_produk, nama_produk, harga FROM produk WHERE id_supplier = ? ORDER BY nama_produk ASC');
$st->execute([$idSupplier]);
$opsiProduk = $st->fetchAll();

$st = $pdo->prepare('SELECT g.*, p.nama_produk
    FROM pengadaan g JOIN produk p ON p.id_produk = g.id_produk
    WHERE g.id_supplier = ?
    ORDER BY g.tanggal_pengadaan DESC, g.id_pengadaan DESC LIMIT 15');
$st->execute([$idSupplier]);
$pengadaan = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN status IN ('Diajukan','Dikirim') THEN 1 ELSE 0 END),0) AS berjalan,
    COALESCE(SUM(CASE WHEN status <> 'Dibatalkan' THEN total_harga ELSE 0 END),0) AS nilai
    FROM pengadaan WHERE id_supplier = ?");
$st->execute([$idSupplier]);
$pgStats = $st->fetch();

supplier_header('Dashboard Supplier', 'dashboard', $supplier);
$totalProduk = (int)$produkStats['total_produk'];
$aman = max(0, $totalProduk - (int)$produkStats['stok_menipis']);
$pctAman = $totalProduk > 0 ? (int)round($aman / $totalProduk * 100) : 0;
?>
<section class="sp-hero">
  <div class="sp-hero-text">
    <span class="supplier-eyebrow">PORTAL SUPPLIER</span>
    <h1>Halo, <?= e($supplier['nama_supplier']) ?></h1>
    <p>Berikut ringkasan produk dan pesanan milikmu.</p>
  </div>
  <div class="sp-hero-side">
    <span class="supplier-date"><?= icon('cal') ?><?= e(tanggal_id('now')) ?></span>
    <div class="sp-health">
      <div class="sp-health-top"><span>Kesehatan stok</span><b><?= $pctAman ?>%</b></div>
      <div class="sp-health-bar"><i style="width:<?= $pctAman ?>%"></i></div>
      <small><?= $aman ?> dari <?= $totalProduk ?> produk stoknya aman</small>
    </div>
  </div>
</section>

<section class="supplier-stats" aria-label="Ringkasan supplier">
  <article class="supplier-stat s-a"><i class="sp-ic"><?= icon('produk') ?></i><div><span>Total produk</span><b><?= (int)$produkStats['total_produk'] ?></b><small>Produk terdaftar atas akunmu</small></div></article>
  <article class="supplier-stat s-b"><i class="sp-ic"><?= icon('stock') ?></i><div><span>Total stok</span><b><?= number_format((int)$produkStats['total_stok'], 0, ',', '.') ?></b><small>Unit tersedia di katalog</small></div></article>
  <article class="supplier-stat s-c"><i class="sp-ic"><?= icon('clock') ?></i><div><span>Pesanan menunggu</span><b><?= (int)$orderStats['pesanan_pending'] ?></b><small>Pesanan dengan status pending</small></div></article>
  <article class="supplier-stat s-d"><i class="sp-ic"><?= icon('alert') ?></i><div><span>Stok menipis</span><b><?= (int)$produkStats['stok_menipis'] ?></b><small>Produk dengan stok 5 unit atau kurang</small></div></article>
</section>

<section class="supplier-section" id="produk">
  <div class="supplier-section-head"><div><h2>Produk saya</h2><p class="muted">Daftar produk yang terhubung dengan akun supplier ini.</p></div><span class="supplier-count"><?= count($produk) ?> ditampilkan</span></div>
  <?php if (!$produk): ?>
    <div class="empty">Belum ada produk yang ditautkan ke akun supplier ini. Hubungi admin untuk menghubungkan produk.</div>
  <?php else: ?>
    <div class="sp-products">
      <?php foreach ($produk as $p): $cls = $p['stok'] <= 0 ? 'empty-stock' : ($p['stok'] <= 5 ? 'low-stock' : 'ready-stock'); ?>
        <article class="sp-product <?= $cls ?>">
          <div class="sp-product-top">
            <span class="sp-product-id">#<?= (int)$p['id_produk'] ?></span>
            <span class="supplier-stock <?= $cls ?>"><?= $p['stok'] <= 0 ? 'Habis' : ($p['stok'] <= 5 ? 'Menipis' : 'Tersedia') ?></span>
          </div>
          <h3><?= e($p['nama_produk']) ?></h3>
          <div class="sp-product-price"><?= (float)$p['harga'] > 0 ? rupiah($p['harga']) : '<span class="muted">Belum ditentukan</span>' ?></div>
          <div class="sp-product-stock"><span>Stok</span><b><?= (int)$p['stok'] ?></b></div>
          <div class="sp-meter"><i style="width:<?= max(3, min(100, (int)$p['stok'] * 4)) ?>%"></i></div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ((int)$produkStats['total_produk'] > count($produk)): ?><p class="muted supplier-note">Menampilkan 12 produk prioritas. Total produk milikmu: <?= (int)$produkStats['total_produk'] ?>.</p><?php endif; ?>
  <?php endif; ?>
</section>

<section class="supplier-section" id="pengadaan">
  <div class="supplier-section-head"><div><h2>Tabel pengadaan</h2><p class="muted">Pasokan barang yang kamu ajukan ke <?= e(STORE_NAME) ?>.</p></div><span class="supplier-count"><?= (int)$pgStats['berjalan'] ?> berjalan · <?= rupiah($pgStats['nilai']) ?></span></div>

  <?php if ($opsiProduk): ?>
  <form class="sp-form" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah_pengadaan">
    <div class="sp-form-field sp-form-wide"><label for="pg-produk">Produk</label>
      <select id="pg-produk" name="id_produk" required>
        <?php foreach ($opsiProduk as $o): ?><option value="<?= (int)$o['id_produk'] ?>"><?= e($o['nama_produk']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="sp-form-field"><label for="pg-jumlah">Jumlah (unit)</label><input id="pg-jumlah" type="number" name="jumlah" min="1" max="100000" value="1" required></div>
    <div class="sp-form-field"><label for="pg-harga">Harga satuan (Rp)</label><input id="pg-harga" type="number" name="harga_satuan" min="1" step="1000" placeholder="10000000" required></div>
    <div class="sp-form-field sp-form-wide"><label for="pg-catatan">Catatan <span class="muted">(opsional)</span></label><input id="pg-catatan" name="catatan" maxlength="255" placeholder="Mis. estimasi tiba, ekspedisi"></div>
    <div class="sp-form-field sp-form-submit"><button class="btn" type="submit">+ Ajukan pengadaan</button></div>
  </form>
  <?php else: ?>
    <div class="empty">Belum ada produk yang ditautkan ke akunmu, jadi pengadaan belum bisa diajukan.</div>
  <?php endif; ?>

  <?php if (!$pengadaan): ?>
    <div class="empty">Belum ada data pengadaan.</div>
  <?php else: ?>
    <div class="supplier-table-wrap"><table class="tbl supplier-table">
      <thead><tr><th>Pengadaan</th><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga satuan</th><th class="num">Total</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($pengadaan as $g): ?>
        <tr>
          <td><b>#<?= (int)$g['id_pengadaan'] ?></b><small class="supplier-row-meta"><?= e(tanggal_id($g['tanggal_pengadaan'], 'd M Y, H:i')) ?></small></td>
          <td><?= e($g['nama_produk']) ?><?php if ($g['catatan']): ?><small class="supplier-row-meta"><?= e($g['catatan']) ?></small><?php endif; ?></td>
          <td class="num"><?= (int)$g['jumlah'] ?></td>
          <td class="num"><?= rupiah($g['harga_satuan']) ?></td>
          <td class="num"><?= rupiah($g['total_harga']) ?></td>
          <td><span class="badge <?= e($g['status']) ?>"><?= e($g['status']) ?></span><?php if (!empty($g['no_resi'])): ?><small class="supplier-row-meta"><?= e(trim(($g['kurir'] ?? '') . ' · ' . $g['no_resi'], ' ·')) ?></small><?php endif; ?><?php if (!empty($g['tanggal_diterima'])): ?><small class="supplier-row-meta">Diterima <?= e(tanggal_id($g['tanggal_diterima'], 'd M Y, H:i')) ?></small><?php endif; ?></td>
          <td>
            <?php if (in_array($g['status'], ['Diajukan', 'Dikirim'], true)): ?>
            <div class="sp-actions">
              <?php if ($g['status'] === 'Diajukan'): ?>
              <form method="post" class="sp-kirim-form"><?= csrf_field() ?><input type="hidden" name="aksi" value="kirim_pengadaan"><input type="hidden" name="id_pengadaan" value="<?= (int)$g['id_pengadaan'] ?>"><input type="text" name="kurir" maxlength="60" placeholder="Kurir" aria-label="Kurir"><input type="text" name="no_resi" maxlength="60" placeholder="No. resi" aria-label="Nomor resi"><button class="btn sm" type="submit">Tandai dikirim</button></form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Batalkan pengadaan #<?= (int)$g['id_pengadaan'] ?>?')"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal_pengadaan"><input type="hidden" name="id_pengadaan" value="<?= (int)$g['id_pengadaan'] ?>"><button class="btn sm ghost" type="submit">Batalkan</button></form>
            </div>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ((int)$pgStats['total'] > count($pengadaan)): ?><p class="muted supplier-note">Menampilkan 15 pengadaan terbaru dari total <?= (int)$pgStats['total'] ?>.</p><?php endif; ?>
  <?php endif; ?>
</section>

<section class="supplier-section" id="pesanan">
  <div class="supplier-section-head"><div><h2>Pesanan produk</h2><p class="muted">Rincian pesanan yang berisi produk dari supplier ini.</p></div><span class="supplier-count"><?= count($pesanan) ?> terbaru</span></div>
  <?php if (!$pesanan): ?>
    <div class="empty">Belum ada pesanan untuk produkmu.</div>
  <?php else: ?>
    <div class="supplier-table-wrap"><table class="tbl supplier-table">
      <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Produk</th><th class="num">Jumlah</th><th class="num">Subtotal</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($pesanan as $o): ?>
        <tr>
          <td><b>#<?= (int)$o['id_pesanan'] ?></b><small class="supplier-row-meta"><?= e(tanggal_id($o['tanggal_pesanan'], 'd M Y, H:i')) ?></small></td>
          <td><?= e($o['nama_pelanggan']) ?></td>
          <td><?= e($o['nama_produk']) ?></td>
          <td class="num"><?= (int)$o['jumlah'] ?></td>
          <td class="num"><?= rupiah($o['subtotal']) ?></td>
          <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php supplier_footer();
