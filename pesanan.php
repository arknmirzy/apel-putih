<?php
require_once __DIR__ . '/includes/layout.php';

pastikan_kolom_pembayaran();
pastikan_kolom_pengiriman();
$user = wajib_pelanggan();

// Aksi pelanggan pada pesanannya: konfirmasi terima (Dikirim -> Selesai) atau batalkan pesanan COD (Pending).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $idAksi = (int)($_POST['id_pesanan'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'konfirmasi_terima' && $idAksi > 0) {
        $up = db()->prepare("UPDATE pesanan SET status = 'Selesai', tanggal_diterima = COALESCE(tanggal_diterima, NOW())
            WHERE id_pesanan = ? AND id_pelanggan = ? AND status = 'Dikirim'");
        $up->execute([$idAksi, (int)$user['id_pelanggan']]);
        flash($up->rowCount()
            ? 'Terima kasih! Pesanan #' . $idAksi . ' ditandai sudah diterima.'
            : 'Pesanan ini belum bisa dikonfirmasi diterima.', $up->rowCount() ? 'ok' : 'err');
    } elseif ($aksi === 'batalkan' && $idAksi > 0) {
        $dibatalkan = batalkan_pesanan_pelanggan($idAksi, (int)$user['id_pelanggan']);
        flash($dibatalkan
            ? 'Pesanan #' . $idAksi . ' dibatalkan dan stok dikembalikan.'
            : 'Pesanan ini sudah tidak bisa dibatalkan. Hubungi admin bila perlu bantuan.', $dibatalkan ? 'ok' : 'err');
    }
    redirect('pesanan.php');
}

$st = db()->prepare('SELECT * FROM pesanan WHERE id_pelanggan = ? ORDER BY tanggal_pesanan DESC, id_pesanan DESC');
$st->execute([$user['id_pelanggan']]);
$pesanan = $st->fetchAll();

$detail = db()->prepare('SELECT d.*, p.nama_produk, p.gambar FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk WHERE d.id_pesanan = ?');
$statusSteps = [
    ['key' => 'Pending', 'label' => 'Diterima'],
    ['key' => 'Diproses', 'label' => 'Disiapkan'],
    ['key' => 'Dikirim', 'label' => 'Dikirim'],
    ['key' => 'Selesai', 'label' => 'Selesai'],
];

page_header('Riwayat pesanan', 'pesanan');
?>
<section class="orders-page-head">
  <div>
    <span class="orders-eyebrow">AKUN PELANGGAN</span>
    <h1>Riwayat pesanan</h1>
    <p class="sub">Halo, <?= e($user['nama_pelanggan']) ?>. Semua pesanan dan perkembangannya tersusun di satu tempat.</p>
  </div>
  <a class="orders-back-shop" href="index.php"><span aria-hidden="true">＋</span> Lanjut belanja</a>
</section>

<?php if (!$pesanan): ?>
  <section class="orders-empty-state">
    <div class="orders-empty-icon" aria-hidden="true">▱</div>
    <h2>Belum ada pesanan</h2>
    <p>Produk yang kamu pesan akan muncul di halaman ini. Yuk, temukan iPhone yang kamu suka.</p>
    <a href="index.php" class="btn">Jelajahi produk</a>
  </section>
<?php else: ?>
  <div class="orders-list">
  <?php foreach ($pesanan as $o): ?>
    <?php
      $detail->execute([$o['id_pesanan']]);
      $rows = $detail->fetchAll();
      $status = (string)$o['status'];
      $isCancelled = $status === 'Dibatalkan';
      $progressIndex = array_search($status, array_column($statusSteps, 'key'), true);
      if ($progressIndex === false) {
          $progressIndex = 0;
      }
    ?>
    <article class="customer-order-card">
      <header class="customer-order-header">
        <div class="customer-order-identity">
          <span class="orders-eyebrow">NOMOR PESANAN</span>
          <h2>#<?= (int)$o['id_pesanan'] ?></h2>
          <span class="customer-order-date"><?= e(tanggal_id($o['tanggal_pesanan'], 'd M Y · H:i')) ?></span>
        </div>
        <div class="customer-order-status">
          <span class="muted">Status pesanan</span>
          <span class="badge <?= e($status) ?>"><?= e($status) ?></span>
        </div>
      </header>

      <?php if ($isCancelled): ?>
        <div class="order-cancelled-note">
          <span class="order-state-symbol" aria-hidden="true">×</span>
          <div><b>Pesanan dibatalkan</b><span>Pesanan ini tidak dilanjutkan. Hubungi admin apabila kamu membutuhkan bantuan.</span></div>
        </div>
      <?php else: ?>
        <div class="order-progress" aria-label="Tahapan pesanan">
          <?php foreach ($statusSteps as $stepIndex => $step): ?>
            <div class="order-progress-step <?= $stepIndex < $progressIndex ? 'is-done' : '' ?> <?= $stepIndex === $progressIndex ? 'is-current' : '' ?>">
              <span class="order-progress-dot"><?= $stepIndex < $progressIndex ? '✓' : (string)($stepIndex + 1) ?></span>
              <span class="order-progress-label"><?= e($step['label']) ?></span>
            </div>
            <?php if ($stepIndex < count($statusSteps) - 1): ?>
              <span class="order-progress-line <?= $stepIndex < $progressIndex ? 'is-done' : '' ?>" aria-hidden="true"></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="customer-order-content">
        <div class="customer-order-section-title">
          <div><span class="orders-eyebrow">RINCIAN</span><h3>Produk dipesan</h3></div>
          <span class="customer-order-item-count"><?= count($rows) ?> produk</span>
        </div>

        <?php if ($rows): ?>
          <div class="customer-order-items">
          <?php foreach ($rows as $r): ?>
            <div class="customer-order-item">
              <div class="order-item-visual"><?= gambar_produk($r['gambar'] ?? null, $r['nama_produk']) ?></div>
              <div class="order-item-info">
                <b><?= e($r['nama_produk']) ?></b>
                <span><?= (int)$r['jumlah'] ?> × <?= rupiah($r['harga_satuan']) ?></span>
              </div>
              <strong class="order-item-subtotal"><?= rupiah($r['subtotal']) ?></strong>
            </div>
          <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">Rincian produk tidak tersedia.</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($o['no_resi']) || (!empty($o['kurir']) && $status !== 'Dibatalkan')): ?>
        <div class="order-tracking">
          <span>Kurir<?= !empty($o['no_resi']) ? ' · nomor resi' : '' ?></span>
          <?php if (!empty($o['no_resi'])): ?>
            <b><?= e(($o['kurir'] ? $o['kurir'] . ' · ' : '') . $o['no_resi']) ?></b>
          <?php else: ?>
            <b>Menunggu nomor resi</b>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($status === 'Pending' && $o['metode_pembayaran'] === COD_LABEL): ?>
        <form method="post" class="order-confirm-form is-neutral" data-konfirmasi="<?= e('Batalkan pesanan #' . (int)$o['id_pesanan'] . '? Stok produk akan dikembalikan.') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="batalkan">
          <input type="hidden" name="id_pesanan" value="<?= (int)$o['id_pesanan'] ?>">
          <p class="muted">Berubah pikiran? Pesanan COD yang belum diproses masih bisa dibatalkan.</p>
          <button class="btn ghost sm" type="submit">Batalkan pesanan</button>
        </form>
      <?php endif; ?>
      <?php if ($status === 'Dikirim'): ?>
        <form method="post" class="order-confirm-form">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="konfirmasi_terima">
          <input type="hidden" name="id_pesanan" value="<?= (int)$o['id_pesanan'] ?>">
          <p class="muted">Sudah menerima paket? Tekan tombol di bawah agar pesanan ditutup.</p>
          <button class="btn sm" type="submit">Pesanan sudah saya terima</button>
        </form>
      <?php endif; ?>

      <footer class="customer-order-footer">
        <div class="order-payment-method"><span>Metode pembayaran</span><b><?= e(label_pembayaran($o)) ?></b></div>
        <?php if ($o['metode_pembayaran'] === MIDTRANS_LABEL && $status === 'Pending' && ($o['status_bayar'] ?? '') !== 'Lunas'): ?>
          <a class="btn sm order-pay-now" href="bayar.php?id=<?= (int)$o['id_pesanan'] ?>">Bayar sekarang</a>
        <?php endif; ?>
        <div class="order-total"><span>Total pembayaran</span><strong><?= rupiah($o['total_harga']) ?></strong></div>
      </footer>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php page_footer();
