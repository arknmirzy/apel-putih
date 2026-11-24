<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();
pastikan_kolom_pengiriman();
pastikan_tabel_pengadaan();

/**
 * Untung per bulan = omset - HPP (12 bulan terakhir, bulan berjalan di ujung kanan).
 * Omset: pesanan tidak dibatalkan yang sudah lunas, atau COD yang sudah selesai.
 * HPP: rata-rata harga modal per produk dari pengadaan yang sudah diterima.
 * Item tanpa data pengadaan dihitung HPP 0.
 */
function omset_bulanan(PDO $pdo): array
{
    $awal = date('Y-m-01', strtotime('first day of -11 months'));
    $akhir = date('Y-m-01', strtotime('first day of next month'));
    $st = $pdo->prepare("SELECT YEAR(o.tanggal_pesanan) AS th, MONTH(o.tanggal_pesanan) AS bl,
            COALESCE(SUM(d.subtotal), 0) AS omset,
            COALESCE(SUM(d.jumlah * COALESCE(h.hpp, 0)), 0) AS hpp
        FROM detail_pesanan d
        JOIN pesanan o ON o.id_pesanan = d.id_pesanan
        LEFT JOIN (
            SELECT id_produk, SUM(total_harga) / NULLIF(SUM(jumlah), 0) AS hpp
            FROM pengadaan WHERE status = 'Diterima' GROUP BY id_produk
        ) h ON h.id_produk = d.id_produk
        WHERE o.status <> 'Dibatalkan'
          AND (o.status_bayar = 'Lunas' OR o.status = 'Selesai')
          AND o.tanggal_pesanan >= ? AND o.tanggal_pesanan < ?
        GROUP BY YEAR(o.tanggal_pesanan), MONTH(o.tanggal_pesanan)");
    $st->execute([$awal, $akhir]);
    $data = [];
    foreach ($st->fetchAll() as $r) {
        $data[(int)$r['th'] . '-' . (int)$r['bl']] = $r;
    }
    $hasil = [];
    for ($i = 11; $i >= 0; $i--) {
        $t = strtotime("first day of -$i months");
        $th = (int)date('Y', $t);
        $bl = (int)date('n', $t);
        $r = $data["$th-$bl"] ?? null;
        $omset = (float)($r['omset'] ?? 0);
        $hpp = (float)($r['hpp'] ?? 0);
        $hasil[] = ['th' => $th, 'bl' => $bl, 'untung' => $omset - $hpp];
    }
    return $hasil;
}

$jml = fn(string $t) => (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
$pendapatan = (float)$pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status <> 'Dibatalkan'")->fetchColumn();
$baru = (int)$pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Pending'")->fetchColumn();
$grafikBulanan = omset_bulanan($pdo);

$terbaru = $pdo->query('SELECT o.*, c.nama_pelanggan FROM pesanan o JOIN pelanggan c ON c.id_pelanggan = o.id_pelanggan ORDER BY o.tanggal_pesanan DESC, o.id_pesanan DESC LIMIT 6')->fetchAll();
$menipis = $pdo->query('SELECT nama_produk, stok FROM produk WHERE stok <= 5 ORDER BY stok ASC, nama_produk LIMIT 6')->fetchAll();

admin_header('Dashboard', 'index');
$namaAdmin = $_SESSION['admin_nama'] ?? 'Admin';
?>
<section class="dash-hero">
  <div class="dash-hero-text">
    <span class="dash-eyebrow"><?= icon('index') ?> Ringkasan toko</span>
    <h1>Halo, <?= e($namaAdmin) ?></h1>
    <p>Berikut ringkasan <?= e(STORE_NAME) ?> hari ini. <?= $baru > 0 ? '<b>' . $baru . ' pesanan</b> menunggu diproses.' : 'Tidak ada pesanan yang menunggu.' ?></p>
    <div class="dash-hero-actions">
      <a class="dash-cta" href="pesanan.php"><span>Lihat pesanan</span><?= icon('arrow') ?></a>
      <a class="dash-cta ghost" href="produk.php"><?= icon('plus') ?><span>Kelola produk</span></a>
    </div>
  </div>
  <div class="dash-hero-side">
    <span>Pesanan pending</span>
    <b><?= $baru ?></b>
    <small><?= e(tanggal_id('now')) ?></small>
  </div>
</section>

<div class="stats stats-admin">
  <a class="stat stat-a" href="produk.php"><i class="stat-ic"><?= icon('produk') ?></i><b><?= $jml('produk') ?></b><span>Produk</span></a>
  <a class="stat stat-b" href="pelanggan.php"><i class="stat-ic"><?= icon('pelanggan') ?></i><b><?= $jml('pelanggan') ?></b><span>Pelanggan</span></a>
  <a class="stat stat-c" href="pesanan.php"><i class="stat-ic"><?= icon('pesanan') ?></i><b><?= $jml('pesanan') ?></b><span>Pesanan (<?= $baru ?> pending)</span></a>
  <div class="stat stat-d"><i class="stat-ic"><?= icon('money') ?></i><b><?= rupiah($pendapatan) ?></b><span>Total penjualan</span></div>
</div>

<section class="keu-section">
  <div class="dash-panel chart-panel">
    <div class="chart-head">
      <div class="chart-read">
        <span class="chart-read-label"></span>
        <b class="chart-read-val"></b>
      </div>
      <span class="chart-title"><?= icon('money') ?> Untung bersih bulanan</span>
    </div>
    <div class="chart-box" id="chart-untung" data-chart="<?= e(json_encode($grafikBulanan)) ?>">
      <svg class="chart-svg" role="img" aria-label="Grafik untung bersih 12 bulan terakhir"></svg>
    </div>
    <p class="chart-hint">12 bulan terakhir · geser grafik untuk melihat tiap bulan</p>
  </div>
</section>

<script>
(function () {
  var box = document.getElementById('chart-untung');
  if (!box) return;
  var data = JSON.parse(box.getAttribute('data-chart'));
  var svg = box.querySelector('svg');
  var panel = box.closest('.chart-panel');
  var rLabel = panel.querySelector('.chart-read-label');
  var rVal = panel.querySelector('.chart-read-val');
  var NS = 'http://www.w3.org/2000/svg';
  var BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var idx = data.length - 1;
  var geo = null, valEl = null, bars = [], xlabs = [];
  var shown = 0, tween = null, lastW = 0, intro = true;

  function rp(n) { return (n < 0 ? '-' : '') + 'Rp ' + Math.abs(Math.round(n)).toLocaleString('id-ID'); }
  function singkat(n) {
    var a = Math.abs(n), t = n < 0 ? '-' : '';
    if (a >= 1e9) return t + (a / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
    if (a >= 1e6) return t + (a / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (a >= 1e3) return t + Math.round(a / 1e3) + ' rb';
    return String(Math.round(n));
  }
  function nice(max) {
    if (max <= 0) return 1e6;
    var p = Math.pow(10, Math.floor(Math.log10(max))), f = max / p;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
  }
  function el(name, attrs) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    svg.appendChild(n);
    return n;
  }

  /* Angka besar dihitung naik/turun dengan mulus */
  function animasiAngka(ke) {
    if (tween) cancelAnimationFrame(tween);
    if (reduce) { shown = ke; rVal.textContent = rp(ke); return; }
    var dari = shown, mulai = null, dur = intro ? 1000 : 450;
    function langkah(t) {
      if (mulai === null) mulai = t;
      var p = Math.min((t - mulai) / dur, 1), e = 1 - Math.pow(1 - p, 4);
      shown = dari + (ke - dari) * e;
      rVal.textContent = rp(shown);
      if (p < 1) tween = requestAnimationFrame(langkah);
      else { shown = ke; tween = null; }
    }
    tween = requestAnimationFrame(langkah);
  }

  /* Posisi label nilai di atas/bawah batang aktif */
  function taruhLabel() {
    var d = data[idx], g = geo;
    var x = Math.max(26, Math.min(g.W - 26, g.cx[idx]));
    var y = d.untung >= 0 ? g.top[idx] - 9 : g.top[idx] + g.h[idx] + 17;
    valEl.textContent = singkat(d.untung);
    valEl.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
  }

  function pilih(i, langsung) {
    for (var k = 0; k < data.length; k++) {
      bars[k].classList.toggle('on', k === i);
      xlabs[k].classList.toggle('on', k === i);
    }
    idx = i;
    var d = data[i];
    rLabel.textContent = BULAN[d.bl] + ' ' + d.th;
    taruhLabel();
    if (langsung) { shown = d.untung; rVal.textContent = rp(d.untung); }
    else animasiAngka(d.untung);
  }

  function bangun() {
    var W = Math.max(Math.round(box.clientWidth), 260);
    var sempit = W < 480;
    var H = sempit ? 220 : W < 720 ? 250 : 270;
    var PL = sempit ? 40 : 50, PR = sempit ? 4 : 8, PT = 28, PB = 28;
    lastW = box.clientWidth;
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.classList.toggle('intro', intro && !reduce);
    svg.innerHTML = '';
    bars = []; xlabs = [];

    var vals = data.map(function (d) { return d.untung; });
    var mx = Math.max.apply(null, vals.concat([0])), mn = Math.min.apply(null, vals.concat([0]));
    var step = nice(Math.max(mx, -mn, 1) / 3);
    var hi = Math.max(Math.ceil(mx / step), 1) * step;
    var lo = -Math.ceil(-mn / step) * step;
    var jml = Math.round((hi - lo) / step);
    var iw = W - PL - PR, ih = H - PT - PB, n = data.length, slot = iw / n;
    var bw = Math.min(slot * 0.6, 34);
    function Y(v) { return PT + ih * (1 - (v - lo) / (hi - lo)); }

    for (var gI = 0; gI <= jml; gI++) {
      var v = lo + step * gI, y = Y(v);
      el('line', { x1: PL, x2: W - PR, y1: y, y2: y, class: 'c-grid' });
      el('text', { x: PL - 8, y: y + 4, class: 'c-ylab', 'text-anchor': 'end' }).textContent = singkat(v);
    }

    var y0 = Y(0);
    geo = { W: W, cx: [], top: [], h: [] };
    data.forEach(function (d, i) {
      var cx = PL + slot * (i + 0.5);
      var h = Math.max(Math.abs(Y(d.untung) - y0), 4);
      var top = d.untung >= 0 ? y0 - h : y0;
      geo.cx.push(cx); geo.top.push(top); geo.h.push(h);
      var r = el('rect', { x: cx - bw / 2, y: top, width: bw, height: h, rx: Math.min(7, bw / 2, h / 2), class: 'c-bar' + (d.untung < 0 ? ' neg' : '') });
      r.style.setProperty('--i', i);
      bars.push(r);
      var t = el('text', { x: cx, y: H - 8, class: 'c-xlab', 'text-anchor': 'middle' });
      t.textContent = sempit ? BULAN[d.bl].charAt(0) : BULAN[d.bl].slice(0, 3);
      xlabs.push(t);
    });
    valEl = el('text', { x: 0, y: 0, class: 'c-val', 'text-anchor': 'middle' });
    pilih(idx, !intro);
    if (intro) { shown = 0; rVal.textContent = rp(0); animasiAngka(data[idx].untung); }
    intro = false;
  }

  function dariPointer(e) {
    var r = svg.getBoundingClientRect();
    var x = (e.clientX - r.left) / r.width * geo.W;
    var d0 = geo.cx[0], d1 = geo.cx[data.length - 1];
    var i = Math.round((x - d0) / (d1 - d0) * (data.length - 1));
    i = Math.max(0, Math.min(data.length - 1, i));
    if (i !== idx) pilih(i);
  }
  svg.addEventListener('pointerdown', function (e) {
    if (e.pointerType !== 'mouse') svg.setPointerCapture(e.pointerId);
    dariPointer(e);
  });
  svg.addEventListener('pointermove', dariPointer);
  svg.setAttribute('tabindex', '0');
  svg.addEventListener('keydown', function (e) {
    var i = idx;
    if (e.key === 'ArrowLeft') i--;
    else if (e.key === 'ArrowRight') i++;
    else if (e.key === 'Home') i = 0;
    else if (e.key === 'End') i = data.length - 1;
    else return;
    e.preventDefault();
    pilih(Math.max(0, Math.min(data.length - 1, i)));
  });

  var rafUkuran = 0;
  function cekUkuran() {
    cancelAnimationFrame(rafUkuran);
    rafUkuran = requestAnimationFrame(function () {
      if (Math.abs(box.clientWidth - lastW) > 1) bangun();
    });
  }
  if ('ResizeObserver' in window) new ResizeObserver(cekUkuran).observe(box);
  else window.addEventListener('resize', cekUkuran);
  bangun();
})();
</script>

<div class="dash-grid">
  <section class="dash-panel dash-orders">
    <div class="dash-panel-head">
      <h2><?= icon('clock') ?> Pesanan terbaru</h2>
      <a class="dash-more" href="pesanan.php">Semua <?= icon('arrow') ?></a>
    </div>
    <?php if (!$terbaru): ?>
      <div class="empty">Belum ada pesanan masuk.</div>
    <?php else: ?>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>#</th><th>Pelanggan</th><th>Tanggal</th><th class="num">Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($terbaru as $o): ?>
          <tr>
            <td><a href="pesanan.php?id=<?= (int)$o['id_pesanan'] ?>">#<?= (int)$o['id_pesanan'] ?></a></td>
            <td><span class="avatar-sm"><?= e(strtoupper(substr($o['nama_pelanggan'], 0, 1))) ?></span><?= e($o['nama_pelanggan']) ?></td>
            <td><?= e(tanggal_id($o['tanggal_pesanan'], 'd M Y, H:i')) ?></td>
            <td class="num"><?= rupiah($o['total_harga']) ?></td>
            <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="dash-panel dash-stock">
    <div class="dash-panel-head">
      <h2><?= icon('alert') ?> Stok menipis</h2>
      <a class="dash-more" href="produk.php">Produk <?= icon('arrow') ?></a>
    </div>
    <?php if (!$menipis): ?>
      <div class="dash-ok"><?= icon('stock') ?><p>Semua stok aman.</p></div>
    <?php else: ?>
      <ul class="stock-list">
      <?php foreach ($menipis as $p): ?>
        <li>
          <div class="stock-row"><span class="stock-name"><?= e($p['nama_produk']) ?></span><span class="stok <?= $p['stok'] <= 0 ? 'out' : 'low' ?>"><?= $p['stok'] <= 0 ? 'Habis' : 'Sisa ' . (int)$p['stok'] ?></span></div>
          <div class="stock-bar <?= $p['stok'] <= 0 ? 'out' : 'low' ?>"><i style="width:<?= max(4, min(100, (int)$p['stok'] * 20)) ?>%"></i></div>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<?php admin_footer();
