<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();
pastikan_kolom_alamat();

// ---------- Hapus pelanggan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $id = (int)($_POST['id_pelanggan'] ?? 0);
    $pdo = db();
    $st = $pdo->prepare('SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([$id]);
    $nama = $st->fetchColumn();
    if ($nama === false) {
        flash('Pelanggan tidak ditemukan.', 'err');
    } else {
        try {
            // Pesanan milik pelanggan dihapus dulu (detail_pesanan ikut terhapus via ON DELETE CASCADE),
            // karena relasi pesanan -> pelanggan bersifat RESTRICT.
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM pesanan WHERE id_pelanggan = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM pelanggan WHERE id_pelanggan = ?')->execute([$id]);
            $pdo->commit();
            flash('Pelanggan "' . $nama . '" beserta riwayat pesanannya dihapus.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Gagal menghapus pelanggan.', 'err');
        }
    }
    redirect('pelanggan.php');
}

$cari = trim($_GET['q'] ?? '');
$where = '';
$args = [];
if ($cari !== '') {
    $where = ' WHERE (c.nama_pelanggan LIKE ? OR c.email LIKE ? OR c.no_hp LIKE ?)';
    $args = array_fill(0, 3, pola_cari($cari));
}
$st = db()->prepare('SELECT COUNT(*) FROM pelanggan c' . $where);
$st->execute($args);
$total = (int)$st->fetchColumn();
[$halaman, $totalHalaman, $offset] = paginasi($total, 10);
$st = db()->prepare('SELECT c.*, COUNT(o.id_pesanan) AS jml_pesanan
    FROM pelanggan c LEFT JOIN pesanan o ON o.id_pelanggan = c.id_pelanggan' . $where . '
    GROUP BY c.id_pelanggan ORDER BY c.id_pelanggan DESC LIMIT 10 OFFSET ' . $offset);
$st->execute($args);
$rows = $st->fetchAll();

admin_header('Pelanggan', 'pelanggan');
?>
<h1>Pelanggan</h1>
<p class="sub"><?= $total ?> pelanggan <?= $cari !== '' ? 'cocok dengan “' . e($cari) . '”' : 'terdaftar' ?>. Data pelanggan yang sudah mendaftar dan pengelolaannya.</p>
<?php admin_cari($cari, 'Cari nama, email, atau nomor HP…'); ?>
<?php if (!$rows): ?>
  <div class="empty"><?= $cari !== '' ? 'Tidak ada pelanggan yang cocok dengan pencarian.' : 'Belum ada pelanggan terdaftar.' ?></div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Nama</th><th>Email</th><th>No. HP</th><th>Alamat</th><th class="num">Pesanan</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><b><?= e($c['nama_pelanggan']) ?></b></td>
      <td><?= e($c['email']) ?></td>
      <td><?= e($c['no_hp'] ?: '—') ?></td>
      <td>
        <?= e($c['alamat'] ?: '—') ?>
        <?php if ($c['provinsi'] || $c['kode_pos']): ?>
          <div class="muted cell-sub"><?= e(implode(' · ', array_filter([$c['provinsi'], $c['kode_pos']]))) ?></div>
        <?php endif; ?>
      </td>
      <td class="num"><?= (int)$c['jml_pesanan'] ?></td>
      <td>
        <form method="post" class="inline-form" onsubmit="return confirm('Hapus pelanggan <?= e(addslashes($c['nama_pelanggan'])) ?>?<?= (int)$c['jml_pesanan'] > 0 ? ' ' . (int)$c['jml_pesanan'] . ' pesanan miliknya juga akan ikut terhapus.' : '' ?> Tindakan ini tidak bisa dibatalkan.')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id_pelanggan" value="<?= (int)$c['id_pelanggan'] ?>">
          <button class="btn sm danger" type="submit">Hapus</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php render_paginasi($halaman, $totalHalaman); ?>
<?php endif; ?>
<?php admin_footer();
