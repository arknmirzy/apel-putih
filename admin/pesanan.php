<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();
pastikan_kolom_pembayaran();
pastikan_kolom_pengiriman();
pastikan_kolom_alamat();

/** Daftar pesanan (terbaru dulu), dapat disaring menurut status. */
function daftar_pesanan(PDO $pdo, string $status): array
{
    $sql = 'SELECT o.*, c.nama_pelanggan, c.email, c.no_hp, c.provinsi
        FROM pesanan o JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan';
    $args = [];
    if ($status !== '') {
        $sql .= ' WHERE o.status = ?';
        $args[] = $status;
    }
    $st = $pdo->prepare($sql . ' ORDER BY o.tanggal_pesanan DESC, o.id_pesanan DESC');
    $st->execute($args);
    return $st->fetchAll();
}

/** Unduh daftar pesanan sebagai CSV (pemisah titik koma agar langsung rapi di Excel Indonesia). */
function ekspor_pesanan_csv(array $rows, string $status): void
{
    $nama = 'pesanan' . ($status !== '' ? '-' . strtolower($status) : '') . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 agar Excel membaca huruf dengan benar
    // Awalan tanda kutip mencegah sel berawalan = + - @ dijalankan sebagai rumus di Excel.
    $aman = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    fputcsv($out, ['No Pesanan', 'Tanggal', 'Pelanggan', 'Email', 'No HP', 'Provinsi', 'Metode Pembayaran',
        'Status Bayar', 'Status', 'Kurir', 'No Resi', 'Total'], ';', '"', '\\');
    foreach ($rows as $o) {
        fputcsv($out, array_map($aman, [
            $o['id_pesanan'],
            $o['tanggal_pesanan'],
            $o['nama_pelanggan'],
            $o['email'],
            $o['no_hp'],
            $o['provinsi'],
            $o['metode_pembayaran'],
            $o['status_bayar'],
            $o['status'],
            $o['kurir'],
            $o['no_resi'],
            (int)$o['total_harga'],
        ]), ';', '"', '\\');
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $baru = $_POST['status'] ?? '';
    $resiInput = mb_substr(trim((string)($_POST['no_resi'] ?? '')), 0, 60);
    $kurirInput = mb_substr(trim((string)($_POST['kurir'] ?? '')), 0, 60);

    if (in_array($baru, STATUS_PESANAN, true)) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT status, no_resi, kurir FROM pesanan WHERE id_pesanan = ? FOR UPDATE');
            $st->execute([$id]);
            $lama = $st->fetch();

            if (!$lama) {
                flash('Pesanan tidak ditemukan.', 'err');
            } elseif ($lama['status'] === 'Dibatalkan') {
                flash('Pesanan yang sudah dibatalkan tidak bisa diubah lagi.', 'err');
            } elseif ($baru === 'Dikirim' && $resiInput === '' && trim((string)$lama['no_resi']) === '') {
                flash('Isi nomor resi sebelum mengubah status menjadi Dikirim.', 'err');
            } else {
                // Nomor resi & kurir disimpan bila diisi; jika kosong, pertahankan nilai lama.
                $resi = $resiInput !== '' ? $resiInput : $lama['no_resi'];
                $kurir = $kurirInput !== '' ? $kurirInput : $lama['kurir'];
                $pdo->prepare('UPDATE pesanan SET status = ?, no_resi = ?, kurir = ?,
                        tanggal_kirim = IF(? = \'Dikirim\' AND tanggal_kirim IS NULL, NOW(), tanggal_kirim),
                        tanggal_diterima = IF(? = \'Selesai\' AND tanggal_diterima IS NULL, NOW(), tanggal_diterima)
                    WHERE id_pesanan = ?')
                    ->execute([$baru, $resi ?: null, $kurir ?: null, $baru, $baru, $id]);

                if ($baru === 'Dibatalkan') {
                    // Kembalikan stok barang yang sudah dikurangi saat checkout.
                    kembalikan_stok($id);
                    $pdo->prepare("UPDATE pembayaran SET status = 'Dibatalkan' WHERE id_pesanan = ? AND status <> 'Lunas'")->execute([$id]);
                    flash("Pesanan #$id dibatalkan dan stok dikembalikan.");
                } else {
                    flash("Status pesanan #$id diubah menjadi $baru.");
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Gagal mengubah status pesanan.', 'err');
        }
    }
    redirect('pesanan.php' . (isset($_POST['kembali']) ? '?id=' . $id : ''));
}

$idDetail = (int)($_GET['id'] ?? 0);
$filterStatus = (string)($_GET['status'] ?? '');
if (!in_array($filterStatus, STATUS_PESANAN, true)) {
    $filterStatus = '';
}
if (!$idDetail && ($_GET['export'] ?? '') === 'csv') {
    ekspor_pesanan_csv(daftar_pesanan($pdo, $filterStatus), $filterStatus);
}
admin_header('Pesanan', 'pesanan');

if ($idDetail):
    $st = $pdo->prepare('SELECT o.*, c.nama_pelanggan, c.email, c.no_hp, c.alamat, c.provinsi, c.kode_pos
        FROM pesanan o JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan WHERE o.id_pesanan = ?');
    $st->execute([$idDetail]);
    $o = $st->fetch();
    if (!$o) {
        echo '<div class="empty">Pesanan tidak ditemukan. <a href="pesanan.php">Kembali</a></div>';
        admin_footer();
        exit;
    }
    $d = $pdo->prepare('SELECT d.*, p.nama_produk, p.gambar FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk WHERE d.id_pesanan = ?');
    $d->execute([$idDetail]);
    $items = $d->fetchAll();
    $pb = $pdo->prepare('SELECT * FROM pembayaran WHERE id_pesanan = ?');
    $pb->execute([$idDetail]);
    $bayar = $pb->fetch() ?: null;
    ?>
<div class="admin-order-back"><a href="pesanan.php">← Kembali ke semua pesanan</a><span>DETAIL TRANSAKSI</span></div>
<section class="admin-order-heading">
  <div>
    <span class="admin-order-kicker">RINGKASAN PESANAN</span>
    <h1>Pesanan #<?= (int)$o['id_pesanan'] ?></h1>
    <p class="sub">Dibuat pada <?= e(tanggal_id($o['tanggal_pesanan'], 'd M Y, H:i')) ?></p>
  </div>
  <span class="badge <?= e($o['status']) ?> admin-order-heading-status"><?= e($o['status']) ?></span>
</section>

<div class="admin-order-summary">
  <div><span>Nomor pesanan</span><strong>#<?= (int)$o['id_pesanan'] ?></strong></div>
  <div><span>Metode pembayaran</span><strong><?= e(label_pembayaran($o)) ?></strong></div>
  <div><span>Total transaksi</span><strong class="admin-order-summary-total"><?= rupiah($o['total_harga']) ?></strong></div>
</div>

<div class="admin-order-grid">
  <section class="admin-order-panel admin-order-products">
    <div class="admin-order-panel-head">
      <div><span class="admin-order-kicker">ITEM PESANAN</span><h2>Produk dipesan</h2></div>
      <span class="admin-order-item-count"><?= count($items) ?> produk</span>
    </div>
    <?php if ($items): ?>
      <div class="admin-order-table">
        <div class="admin-order-item-list">
        <?php foreach ($items as $i): ?>
          <div class="admin-order-item">
            <div class="admin-order-item-visual"><?= gambar_produk($i['gambar'] ?? null, $i['nama_produk'], '../') ?></div>
            <div class="admin-order-item-info">
              <b><?= e($i['nama_produk']) ?></b>
              <span>Jumlah: <?= (int)$i['jumlah'] ?> unit</span>
            </div>
            <div class="admin-order-item-money">
              <div class="admin-order-item-price"><span>Harga / unit</span><strong><?= rupiah($i['harga_satuan']) ?></strong></div>
              <div class="admin-order-item-subtotal"><span>Subtotal</span><strong><?= rupiah($i['subtotal']) ?></strong></div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty">Tidak ada rincian produk untuk pesanan ini.</div>
    <?php endif; ?>
    <div class="admin-order-total-row"><span>Total pesanan</span><strong><?= rupiah($o['total_harga']) ?></strong></div>
  </section>

  <aside class="admin-order-side">
    <section class="admin-order-panel admin-order-customer admin-order-customer-card">
      <div class="admin-order-panel-head">
        <div><span class="admin-order-kicker">INFORMASI</span><h2>Data pelanggan</h2></div>
        <span class="admin-order-customer-avatar" aria-hidden="true"><?= e(strtoupper(substr((string)$o['nama_pelanggan'], 0, 1))) ?></span>
      </div>
      <div class="admin-order-customer-name"><?= e($o['nama_pelanggan']) ?></div>
      <div class="admin-order-contact-list admin-order-customer-list">
        <div><span>Email</span><b><?= e($o['email'] ?: '—') ?></b></div>
        <div><span>No. HP</span><b><?= e($o['no_hp'] ?: '—') ?></b></div>
        <div><span>Alamat</span><b><?= nl2br(e($o['alamat'] ?: '—')) ?></b></div>
        <div><span>Provinsi</span><b><?= e($o['provinsi'] ?: '—') ?></b></div>
        <div><span>Kode pos</span><b><?= e($o['kode_pos'] ?: '—') ?></b></div>
      </div>
    </section>

    <section class="admin-order-panel admin-order-customer admin-order-payment-card">
      <div class="admin-order-panel-head">
        <div><span class="admin-order-kicker">PEMBAYARAN</span><h2>Detail pembayaran</h2></div>
      </div>
      <?php if ($bayar): ?>
      <div class="admin-order-contact-list admin-order-payment-list">
        <div><span>Metode</span><b><?= e($bayar['metode']) ?></b></div>
        <div><span>Status</span><b><?= e($bayar['status']) ?></b></div>
        <div><span>Jumlah</span><b><?= rupiah($bayar['jumlah']) ?></b></div>
        <?php if ($bayar['tipe_pembayaran']): ?><div><span>Channel</span><b><?= e($bayar['tipe_pembayaran'] . ($bayar['bank'] ? ' · ' . strtoupper($bayar['bank']) : '')) ?></b></div><?php endif; ?>
        <?php if ($bayar['midtrans_order_id']): ?><div><span>Order ID Midtrans</span><b><?= e($bayar['midtrans_order_id']) ?></b></div><?php endif; ?>
        <?php if ($bayar['midtrans_transaction_id']): ?><div><span>ID transaksi</span><b><?= e($bayar['midtrans_transaction_id']) ?></b></div><?php endif; ?>
        <div><span>Dibayar pada</span><b><?= $bayar['waktu_bayar'] ? e(tanggal_id($bayar['waktu_bayar'], 'd M Y, H:i')) : '—' ?></b></div>
      </div>
      <?php else: ?>
        <p class="muted">Belum ada catatan pembayaran.</p>
      <?php endif; ?>
    </section>

    <form method="post" class="admin-order-panel admin-order-status-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$o['id_pesanan'] ?>">
      <input type="hidden" name="kembali" value="1">
      <span class="admin-order-kicker">TINDAKAN ADMIN</span>
      <h2>Perbarui status</h2>
      <label for="status">Status pesanan</label>
      <select id="status" name="status" <?= $o['status'] === 'Dibatalkan' ? 'disabled' : '' ?>>
        <?php foreach (STATUS_PESANAN as $s): ?>
          <option value="<?= e($s) ?>" <?= $s === $o['status'] ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="kurir">Kurir</label>
      <input id="kurir" type="text" name="kurir" maxlength="60" placeholder="mis. JNE, J&amp;T, SiCepat" value="<?= e($o['kurir'] ?? '') ?>" <?= $o['status'] === 'Dibatalkan' ? 'disabled' : '' ?>>
      <label for="no_resi">Nomor resi</label>
      <input id="no_resi" type="text" name="no_resi" maxlength="60" placeholder="Wajib saat status Dikirim" value="<?= e($o['no_resi'] ?? '') ?>" <?= $o['status'] === 'Dibatalkan' ? 'disabled' : '' ?>>
      <?php if (!empty($o['tanggal_kirim'])): ?><p class="admin-order-status-hint">Dikirim: <?= e(tanggal_id($o['tanggal_kirim'], 'd M Y, H:i')) ?></p><?php endif; ?>
      <?php if (!empty($o['tanggal_diterima'])): ?><p class="admin-order-status-hint">Diterima: <?= e(tanggal_id($o['tanggal_diterima'], 'd M Y, H:i')) ?></p><?php endif; ?>
      <button class="btn" type="submit" <?= $o['status'] === 'Dibatalkan' ? 'disabled' : '' ?>>Simpan perubahan</button>
      <p class="admin-order-status-hint">Status Dikirim wajib disertai nomor resi. Saat pesanan dibatalkan, stok produk akan dikembalikan otomatis.</p>
    </form>
  </aside>
</div>

<?php else:
    $rows = daftar_pesanan($pdo, $filterStatus);
    $hitung = $pdo->query('SELECT status, COUNT(*) FROM pesanan GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    ?>
<h1>Pesanan</h1>
<p class="sub">Pesanan yang masuk dari pelanggan.</p>
<nav class="chips admin-chips" aria-label="Saring status pesanan">
  <a href="pesanan.php" class="<?= $filterStatus === '' ? 'on' : '' ?>">Semua (<?= (int)array_sum($hitung) ?>)</a>
  <?php foreach (STATUS_PESANAN as $s): ?>
    <a href="pesanan.php?status=<?= urlencode($s) ?>" class="<?= $filterStatus === $s ? 'on' : '' ?>"><?= e($s) ?> (<?= (int)($hitung[$s] ?? 0) ?>)</a>
  <?php endforeach; ?>
  <a class="chips-export" href="pesanan.php?export=csv<?= $filterStatus !== '' ? '&amp;status=' . urlencode($filterStatus) : '' ?>">Unduh CSV</a>
</nav>
<?php if (!$rows): ?>
  <div class="empty"><?= $filterStatus !== '' ? 'Tidak ada pesanan dengan status ' . e($filterStatus) . '.' : 'Belum ada pesanan masuk.' ?></div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>#</th><th>Pelanggan</th><th>Tanggal</th><th>Pembayaran</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td>#<?= (int)$o['id_pesanan'] ?></td>
      <td><?= e($o['nama_pelanggan']) ?></td>
      <td><?= e(tanggal_id($o['tanggal_pesanan'], 'd M Y, H:i')) ?></td>
      <td><span class="cell-sm"><?= e(label_pembayaran($o)) ?></span></td>
      <td class="num"><?= rupiah($o['total_harga']) ?></td>
      <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
      <td class="num"><a class="btn ghost sm" href="?id=<?= (int)$o['id_pesanan'] ?>">Detail</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; endif; ?>
<?php admin_footer();
