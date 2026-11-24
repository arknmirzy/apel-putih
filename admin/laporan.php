<?php
/**
 * Laporan penjualan per periode (filter tanggal), dengan tombol Cetak dan Unduh CSV.
 * Definisi omset dan HPP sama dengan grafik di dashboard: pesanan tidak dibatalkan yang sudah lunas
 * (atau COD yang sudah selesai); HPP = rata-rata harga modal per produk dari pengadaan yang diterima.
 */
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();
pastikan_kolom_pengiriman();
pastikan_tabel_pengadaan();

/** Terima hanya tanggal Y-m-d yang benar-benar ada di kalender. */
function tanggal_valid(mixed $t): ?string
{
    if (!is_string($t) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return null;
    }
    return $t;
}

$hariIni = date('Y-m-d');
$dari = tanggal_valid($_GET['dari'] ?? null) ?? date('Y-m-01');
$sampai = tanggal_valid($_GET['sampai'] ?? null) ?? $hariIni;
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$bulanLalu = strtotime('first day of last month');
$pintas = [
    'Bulan ini'  => [date('Y-m-01'), $hariIni],
    'Bulan lalu' => [date('Y-m-01', $bulanLalu), date('Y-m-t', $bulanLalu)],
    'Tahun ini'  => [date('Y-01-01'), $hariIni],
];

// Satu baris per item pesanan; ringkasan per pesanan dan per produk dihitung di PHP.
$st = $pdo->prepare("SELECT o.id_pesanan, o.tanggal_pesanan, o.metode_pembayaran, c.nama_pelanggan,
        d.id_produk, p.nama_produk, d.jumlah, d.harga_satuan, d.subtotal,
        d.jumlah * COALESCE(h.hpp, 0) AS hpp
    FROM detail_pesanan d
    JOIN pesanan o ON o.id_pesanan = d.id_pesanan
    JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan
    JOIN produk p ON p.id_produk = d.id_produk
    LEFT JOIN (
        SELECT id_produk, SUM(total_harga) / NULLIF(SUM(jumlah), 0) AS hpp
        FROM pengadaan WHERE status = 'Diterima' GROUP BY id_produk
    ) h ON h.id_produk = d.id_produk
    WHERE o.status <> 'Dibatalkan'
      AND (o.status_bayar = 'Lunas' OR o.status = 'Selesai')
      AND o.tanggal_pesanan >= ? AND o.tanggal_pesanan < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY o.tanggal_pesanan, o.id_pesanan, p.nama_produk");
$st->execute([$dari, $sampai]);
$baris = $st->fetchAll();

// ---------- Unduh CSV (pemisah titik koma agar rapi di Excel Indonesia) ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-penjualan-' . $dari . '_sd_' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 agar Excel membaca huruf dengan benar
    $aman = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    fputcsv($out, ['No Pesanan', 'Tanggal', 'Pelanggan', 'Metode Pembayaran', 'Produk', 'Jumlah', 'Harga Satuan', 'Subtotal'], ';', '"', '\\');
    foreach ($baris as $b) {
        fputcsv($out, array_map($aman, [
            $b['id_pesanan'], $b['tanggal_pesanan'], $b['nama_pelanggan'], $b['metode_pembayaran'],
            $b['nama_produk'], (int)$b['jumlah'], (int)$b['harga_satuan'], (int)$b['subtotal'],
        ]), ';', '"', '\\');
    }
    fclose($out);
    exit;
}

$daftarPesanan = [];
$perProduk = [];
$omset = 0.0;
$hpp = 0.0;
$unit = 0;
foreach ($baris as $b) {
    $idp = (int)$b['id_pesanan'];
    $daftarPesanan[$idp] ??= [
        'tanggal' => $b['tanggal_pesanan'], 'pelanggan' => $b['nama_pelanggan'],
        'metode' => $b['metode_pembayaran'], 'item' => 0, 'total' => 0.0,
    ];
    $daftarPesanan[$idp]['item'] += (int)$b['jumlah'];
    $daftarPesanan[$idp]['total'] += (float)$b['subtotal'];

    $perProduk[$b['id_produk']] ??= ['nama' => $b['nama_produk'], 'unit' => 0, 'omset' => 0.0, 'hpp' => 0.0];
    $perProduk[$b['id_produk']]['unit'] += (int)$b['jumlah'];
    $perProduk[$b['id_produk']]['omset'] += (float)$b['subtotal'];
    $perProduk[$b['id_produk']]['hpp'] += (float)$b['hpp'];

    $omset += (float)$b['subtotal'];
    $hpp += (float)$b['hpp'];
    $unit += (int)$b['jumlah'];
}
uasort($perProduk, static fn($a, $b) => $b['omset'] <=> $a['omset']);
$laba = $omset - $hpp;
$periode = tanggal_id($dari) . ' – ' . tanggal_id($sampai);

admin_header('Laporan penjualan', 'laporan');
?>
<div class="toolbar no-print">
  <div><h1>Laporan penjualan</h1><p class="sub">Periode <?= e($periode) ?></p></div>
  <div class="actions">
    <button class="btn ghost" type="button" onclick="window.print()">Cetak</button>
    <a class="btn" href="?export=csv&amp;dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>">Unduh CSV</a>
  </div>
</div>

<div class="print-only print-head">
  <h1><?= e(STORE_NAME) ?> — Laporan penjualan</h1>
  <p>Periode <?= e($periode) ?> · dicetak <?= e(tanggal_id('now', 'd M Y, H:i')) ?></p>
</div>

<form method="get" class="laporan-filter no-print">
  <div>
    <label for="dari">Dari tanggal</label>
    <input id="dari" type="date" name="dari" value="<?= e($dari) ?>" max="<?= e($hariIni) ?>" required>
  </div>
  <div>
    <label for="sampai">Sampai tanggal</label>
    <input id="sampai" type="date" name="sampai" value="<?= e($sampai) ?>" max="<?= e($hariIni) ?>" required>
  </div>
  <button class="btn" type="submit">Terapkan</button>
</form>
<div class="chips no-print">
  <?php foreach ($pintas as $label => [$a, $b]): ?>
    <a href="?dari=<?= e($a) ?>&amp;sampai=<?= e($b) ?>" class="<?= $dari === $a && $sampai === $b ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat"><b><?= count($daftarPesanan) ?></b><span>Pesanan</span></div>
  <div class="stat"><b><?= $unit ?></b><span>Unit terjual</span></div>
  <div class="stat"><b><?= rupiah($omset) ?></b><span>Omset</span></div>
  <div class="stat"><b><?= rupiah($hpp) ?></b><span>Modal (HPP)</span></div>
  <div class="stat"><b><?= rupiah($laba) ?></b><span>Laba kotor</span></div>
</div>

<?php if (!$baris): ?>
  <div class="empty">Tidak ada penjualan pada periode ini.</div>
<?php else: ?>
<h2 class="laporan-judul">Penjualan per produk</h2>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Produk</th><th class="num">Unit</th><th class="num">Omset</th><th class="num">Modal</th><th class="num">Laba</th></tr></thead>
  <tbody>
  <?php foreach ($perProduk as $p): ?>
    <tr>
      <td><b><?= e($p['nama']) ?></b></td>
      <td class="num"><?= (int)$p['unit'] ?></td>
      <td class="num"><?= rupiah($p['omset']) ?></td>
      <td class="num"><?= rupiah($p['hpp']) ?></td>
      <td class="num"><?= rupiah($p['omset'] - $p['hpp']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h2 class="laporan-judul">Daftar transaksi</h2>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>No.</th><th>Tanggal</th><th>Pelanggan</th><th>Pembayaran</th><th class="num">Item</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($daftarPesanan as $idp => $o): ?>
    <tr>
      <td>#<?= (int)$idp ?></td>
      <td><?= e(tanggal_id($o['tanggal'], 'd M Y, H:i')) ?></td>
      <td><?= e($o['pelanggan']) ?></td>
      <td><?= e(label_metode($o['metode'])) ?></td>
      <td class="num"><?= (int)$o['item'] ?></td>
      <td class="num"><?= rupiah($o['total']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<p class="muted laporan-catatan">Omset dihitung dari pesanan tidak dibatalkan yang sudah lunas atau COD yang sudah selesai. Modal (HPP) memakai rata-rata harga pengadaan yang sudah diterima; produk tanpa data pengadaan dihitung modal 0.</p>
<?php admin_footer();
