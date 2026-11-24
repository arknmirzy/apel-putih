<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/midtrans.php';

pastikan_kolom_pembayaran();
$user = wajib_pelanggan();
$id = (int)($_GET['id'] ?? 0);

function muat_pesanan_bayar(int $id, int $idPelanggan): ?array
{
    $st = db()->prepare('SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?');
    $st->execute([$id, $idPelanggan]);
    return $st->fetch() ?: null;
}

$o = muat_pesanan_bayar($id, (int)$user['id_pelanggan']);
if (!$o || $o['metode_pembayaran'] !== MIDTRANS_LABEL) {
    flash('Pesanan Midtrans tidak ditemukan.', 'err');
    redirect('pesanan.php');
}

// Setelah popup ditutup / selesai: sinkronkan status langsung dari Midtrans
// (berguna saat webhook belum bisa menjangkau server, mis. di localhost).
if (isset($_GET['cek']) && $o['midtrans_order_id']) {
    $hasil = midtrans_cek_status($o['midtrans_order_id']);
    if ($hasil) {
        midtrans_terapkan_status($hasil);
        $o = muat_pesanan_bayar($id, (int)$user['id_pelanggan']);
    }
}

if ($o['status_bayar'] === 'Lunas') {
    flash('Pembayaran pesanan #' . $id . ' berhasil. Terima kasih!');
    redirect('pesanan.php');
}
if ($o['status'] !== 'Pending') {
    flash('Pesanan #' . $id . ' tidak lagi menunggu pembayaran.', 'err');
    redirect('pesanan.php');
}

$token = $o['snap_token'];
if (!$token) {
    [$token, $galat] = midtrans_buat_token($id);
    if (!$token) {
        flash('Pembayaran belum bisa dibuka: ' . $galat, 'err');
        redirect('pesanan.php');
    }
}

page_header('Pembayaran', 'pesanan');
?>
<h1>Pembayaran</h1>
<p class="sub">Selesaikan pembayaran pesanan #<?= (int)$id ?> lewat Midtrans (QRIS, Virtual Account, e-wallet, kartu).</p>
<div class="panel pay-page">
  <div class="pay-page-total"><span class="muted">Total pembayaran</span><strong><?= rupiah($o['total_harga']) ?></strong></div>
  <p class="pay-page-actions">
    <button type="button" class="btn" id="pay-button">Bayar sekarang</button>
    <a class="btn ghost" href="pesanan.php">Bayar nanti</a>
  </p>
  <p class="muted pay-page-note">Pesanan akan dibatalkan otomatis bila tidak dibayar dalam 24 jam.</p>
</div>

<script src="<?= e(midtrans_snap_js()) ?>" data-client-key="<?= e(MIDTRANS_CLIENT_KEY) ?>"></script>
<script>
(function () {
  var token = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var cek = 'bayar.php?id=<?= (int)$id ?>&cek=1';
  function bayar() {
    window.snap.pay(token, {
      onSuccess: function () { window.location.href = cek; },
      onPending: function () { window.location.href = 'pesanan.php'; },
      onError: function () { window.location.href = cek; },
      onClose: function () {}
    });
  }
  document.getElementById('pay-button').addEventListener('click', bayar);
  bayar();
})();
</script>
<?php page_footer();
