<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();
pastikan_tabel_pengadaan();

// ---------- Aksi: tandai diterima (menambah stok) atau batalkan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id_pengadaan'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT id_produk, jumlah, status FROM pengadaan WHERE id_pengadaan = ? FOR UPDATE');
        $st->execute([$id]);
        $g = $st->fetch();

        if (!$g) {
            flash('Pengadaan tidak ditemukan.', 'err');
        } elseif (!in_array($g['status'], ['Diajukan', 'Dikirim'], true)) {
            flash('Pengadaan dengan status ' . $g['status'] . ' tidak bisa diubah lagi.', 'err');
        } elseif ($aksi === 'terima') {
            $pdo->prepare("UPDATE pengadaan SET status = 'Diterima', tanggal_diterima = NOW() WHERE id_pengadaan = ?")->execute([$id]);
            // Barang yang diterima menambah stok produk.
            $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id_produk = ?')->execute([(int)$g['jumlah'], (int)$g['id_produk']]);
            flash("Pengadaan #$id ditandai diterima. Stok produk bertambah {$g['jumlah']} unit.");
        } elseif ($aksi === 'batal') {
            $pdo->prepare("UPDATE pengadaan SET status = 'Dibatalkan' WHERE id_pengadaan = ?")->execute([$id]);
            flash("Pengadaan #$id dibatalkan.");
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Gagal memperbarui pengadaan.', 'err');
    }
    redirect('pengadaan.php');
}

$rows = $pdo->query('SELECT g.*, p.nama_produk, s.nama_supplier
    FROM pengadaan g
    JOIN produk p ON p.id_produk = g.id_produk
    JOIN supplier s ON s.id_supplier = g.id_supplier
    ORDER BY (g.status IN (\'Diajukan\', \'Dikirim\')) DESC, g.tanggal_pengadaan DESC, g.id_pengadaan DESC')->fetchAll();

admin_header('Pengadaan', 'pengadaan');
?>
<h1>Pengadaan</h1>
<p class="sub">Pasokan dari supplier. Tandai <b>Diterima</b> saat barang sudah sampai di toko; stok produk akan bertambah otomatis.</p>
<?php if (!$rows): ?>
  <div class="empty">Belum ada data pengadaan.</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>#</th><th>Supplier</th><th>Produk</th><th class="num">Jumlah</th><th class="num">Total</th><th>Status</th><th>Pengiriman</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $g): ?>
    <tr>
      <td>#<?= (int)$g['id_pengadaan'] ?><br><small class="muted"><?= e(tanggal_id($g['tanggal_pengadaan'], 'd M Y, H:i')) ?></small></td>
      <td><?= e($g['nama_supplier']) ?></td>
      <td><?= e($g['nama_produk']) ?></td>
      <td class="num"><?= (int)$g['jumlah'] ?></td>
      <td class="num"><?= rupiah($g['total_harga']) ?></td>
      <td><span class="badge <?= e($g['status']) ?>"><?= e($g['status']) ?></span></td>
      <td><span class="cell-sm"><?= e(trim(($g['kurir'] ?? '') . ' ' . ($g['no_resi'] ?? ''))) ?: '—' ?></span></td>
      <td class="num">
        <?php if (in_array($g['status'], ['Diajukan', 'Dikirim'], true)): ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id_pengadaan" value="<?= (int)$g['id_pengadaan'] ?>">
            <button class="btn sm" name="aksi" value="terima" type="submit">Tandai diterima</button>
            <button class="btn ghost sm" name="aksi" value="batal" type="submit" onclick="return confirm('Batalkan pengadaan #<?= (int)$g['id_pengadaan'] ?>?')">Batalkan</button>
          </form>
        <?php elseif ($g['status'] === 'Diterima'): ?>
          <span class="muted cell-sm"><?= e(tanggal_id($g['tanggal_diterima'] ?? $g['tanggal_pengadaan'], 'd M Y')) ?></span>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
