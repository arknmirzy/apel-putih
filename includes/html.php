<?php
require_once __DIR__ . '/../config.php';

/**
 * Bagian <head> yang dipakai semua halaman (pelanggan, admin, supplier).
 * $base: '' untuk halaman di root proyek, '../' untuk halaman di admin/ dan supplier/.
 */
function html_head(string $judul, string $base = ''): void
{
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap">
<link rel="stylesheet" href="<?= $base ?>assets/style.css?v=<?= ASSET_VERSION ?>">
</head>
<?php
}

/** Penutup halaman: skrip bersama, </body>, dan </html>. */
function html_scripts(string $base = ''): void
{
    echo '<script src="' . $base . 'assets/app.js?v=' . ASSET_VERSION . "\" defer></script>\n</body>\n</html>\n";
}

/** Navigasi halaman (Sebelumnya, nomor, Berikutnya); parameter GET lain (mis. q) ikut dipertahankan. */
function render_paginasi(int $halaman, int $totalHalaman): void
{
    if ($totalHalaman <= 1) {
        return;
    }
    $url = static function (int $h): string {
        $q = $_GET;
        unset($q['tambah'], $q['edit']);
        $q['hal'] = $h;
        return '?' . http_build_query($q);
    };
    echo '<nav class="pager" aria-label="Navigasi halaman">';
    echo $halaman > 1
        ? '<a href="' . e($url($halaman - 1)) . '" rel="prev">&lsaquo; Sebelumnya</a>'
        : '<span class="off">&lsaquo; Sebelumnya</span>';
    for ($h = max(1, $halaman - 2); $h <= min($totalHalaman, $halaman + 2); $h++) {
        echo $h === $halaman
            ? '<span class="on" aria-current="page">' . $h . '</span>'
            : '<a href="' . e($url($h)) . '">' . $h . '</a>';
    }
    echo $halaman < $totalHalaman
        ? '<a href="' . e($url($halaman + 1)) . '" rel="next">Berikutnya &rsaquo;</a>'
        : '<span class="off">Berikutnya &rsaquo;</span>';
    echo "</nav>\n";
}

/** Ikon SVG satu warna (mengikuti currentColor). Nama ikon mengikuti menu admin/supplier. */
function icon(string $nama): string
{
    static $paths = [
        'index' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/>',
        'produk' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'brand' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L3 13V3h10l7.6 7.6a2 2 0 010 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'kategori' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'pesanan' => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/>',
        'pelanggan' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.9"/><path d="M16 3.1a4 4 0 010 7.8"/>',
        'supplier' => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'store' => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/>',
        'out' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'money' => '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>',
        'alert' => '<path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'stock' => '<path d="M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4a2 2 0 001-1.7z"/><path d="M3.3 7L12 12l8.7-5"/><path d="M12 22V12"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'arrow' => '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'laporan' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 17v-3M12 17v-6M16 17v-4"/>',
        'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    ];
    $nama = ['pengadaan' => 'supplier'][$nama] ?? $nama; // pengadaan memakai ikon yang sama dengan supplier
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$nama] ?? '') . '</svg>';
}

/** Daftar <option> provinsi; $dipilih menandai provinsi yang sedang dipilih. */
function opsi_provinsi(?string $dipilih = ''): string
{
    $html = '<option value="">Pilih provinsi</option>';
    foreach (PROVINSI as $nama) {
        $html .= '<option value="' . e($nama) . '"' . ($nama === $dipilih ? ' selected' : '') . '>' . e($nama) . '</option>';
    }
    return $html;
}
