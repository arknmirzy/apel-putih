<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();

/**
 * Ambil gambar yang tersedia di proyek. Database hanya menyimpan basename,
 * sehingga sumber gambar dibatasi ke folder di FOLDER_GAMBAR (config.php).
 * Nama yang sama diprioritaskan dari folder paling awal (urutan sama dengan url_gambar()).
 */
function daftar_gambar_pustaka(): array
{
    $hasil = [];

    foreach (FOLDER_GAMBAR as $folder) {
        $path = __DIR__ . '/../' . $folder . '/';
        if (!is_dir($path)) {
            continue;
        }
        foreach (scandir($path) ?: [] as $nama) {
            $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)
                || !is_file($path . $nama)
                || @getimagesize($path . $nama) === false) {
                continue;
            }
            $hasil[$nama] ??= [
                'nama' => $nama,
                'folder' => $folder,
                'url' => '../' . url_gambar($nama),
            ];
        }
    }

    uasort($hasil, static fn($a, $b) => strnatcasecmp($a['nama'], $b['nama']));
    return array_values($hasil);
}

$imageLibrary = daftar_gambar_pustaka();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($aksi === 'hapus') {
        hapus_aman('DELETE FROM produk WHERE id_produk = ?', $id, 'Produk');
        redirect('produk.php');
    }

    if ($aksi === 'simpan') {
        $hargaInput = trim((string)($_POST['harga'] ?? ''));
        $hargaValid = $hargaInput === '' || (is_numeric($hargaInput) && (float)$hargaInput >= 0);
        $harga = 0.0;
        if ($hargaInput === '' && $id > 0) {
            // Saat mengedit, harga kosong berarti pertahankan harga sebelumnya.
            $stHarga = $pdo->prepare('SELECT harga FROM produk WHERE id_produk = ?');
            $stHarga->execute([$id]);
            $hargaLama = $stHarga->fetchColumn();
            $harga = $hargaLama !== false ? (float)$hargaLama : 0.0;
        } elseif ($hargaInput !== '' && $hargaValid) {
            $harga = (float)$hargaInput;
        }

        $d = [
            'id_brand' => (int)($_POST['id_brand'] ?? 0),
            'id_kategori' => (int)($_POST['id_kategori'] ?? 0),
            'id_supplier' => (int)($_POST['id_supplier'] ?? 0) ?: null,
            'nama_produk' => trim($_POST['nama_produk'] ?? ''),
            // Database lama mendefinisikan harga sebagai NOT NULL; 0 dipakai
            // sebagai penanda sementara sampai admin mengisi harga sebenarnya.
            'harga' => $harga,
            'stok' => (int)($_POST['stok'] ?? 0),
            'deskripsi' => trim($_POST['deskripsi'] ?? ''),
        ];
        $back = $id > 0 ? "produk.php?edit=$id" : 'produk.php?tambah=1';

        if ($d['nama_produk'] === '' || !$d['id_brand'] || !$d['id_kategori'] || !$hargaValid || $d['stok'] < 0) {
            flash('Nama, brand, dan kategori wajib diisi. Harga boleh dikosongkan atau diisi 0; stok minimal 0.', 'err');
            redirect($back);
        }
        // Gambar dipilih dari galeri aset proyek (folder di FOLDER_GAMBAR).
        $gambarDipilih = trim((string)($_POST['gambar_pustaka'] ?? ''));
        if ($gambarDipilih !== '') {
            $namaValid = $gambarDipilih === basename($gambarDipilih)
                && in_array($gambarDipilih, array_column($imageLibrary, 'nama'), true);
            if (!$namaValid) {
                flash('Pilihan gambar tidak valid. Muat ulang halaman lalu pilih gambar dari galeri.', 'err');
                redirect($back);
            }
            $gambar = $gambarDipilih;
        } else {
            $gambar = null; // Saat edit, null berarti pertahankan gambar sebelumnya.
        }

        if ($id > 0) {
            $sql = 'UPDATE produk SET id_brand=?, id_kategori=?, id_supplier=?, nama_produk=?, harga=?, stok=?, deskripsi=?';
            $args = array_values($d);
            if ($gambar) {
                $sql .= ', gambar=?';
                $args[] = $gambar;
            }
            $sql .= ' WHERE id_produk=?';
            $args[] = $id;
            $pdo->prepare($sql)->execute($args);
            flash('Produk diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO produk (id_brand, id_kategori, id_supplier, nama_produk, harga, stok, deskripsi, gambar) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([...array_values($d), $gambar]);
            flash('Produk ditambahkan.');
        }
        redirect('produk.php');
    }
}

$brand = $pdo->query('SELECT * FROM brand ORDER BY nama_brand')->fetchAll();
$kategori = $pdo->query('SELECT * FROM kategori ORDER BY nama_kategori')->fetchAll();
$supplier = $pdo->query('SELECT id_supplier, nama_supplier FROM supplier ORDER BY nama_supplier')->fetchAll();

$form = isset($_GET['tambah']) || isset($_GET['edit']);
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM produk WHERE id_produk = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
    if (!$edit) {
        flash('Produk tidak ditemukan.', 'err');
        redirect('produk.php');
    }
}

admin_header('Produk', 'produk');

if ($form):
    $v = fn(string $k, $def = '') => e($edit[$k] ?? $def);
    ?>
<p class="muted"><a href="produk.php">← Daftar produk</a></p>
<h1><?= $edit ? 'Ubah produk' : 'Tambah produk' ?></h1>
<form method="post" class="panel form-panel">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <input type="hidden" name="id" value="<?= (int)($edit['id_produk'] ?? 0) ?>">

  <label for="nama_produk">Nama produk</label>
  <input id="nama_produk" name="nama_produk" value="<?= $v('nama_produk') ?>" required>

  <div class="row">
    <div>
      <label for="id_brand">Brand</label>
      <select id="id_brand" name="id_brand" required>
        <?php foreach ($brand as $b): ?>
          <option value="<?= (int)$b['id_brand'] ?>" <?= (int)($edit['id_brand'] ?? 0) === (int)$b['id_brand'] ? 'selected' : '' ?>><?= e($b['nama_brand']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="id_kategori">Kategori</label>
      <select id="id_kategori" name="id_kategori" required>
        <?php foreach ($kategori as $k): ?>
          <option value="<?= (int)$k['id_kategori'] ?>" <?= (int)($edit['id_kategori'] ?? 0) === (int)$k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <label for="id_supplier">Supplier</label>
  <select id="id_supplier" name="id_supplier">
    <option value="">— Tanpa supplier —</option>
    <?php foreach ($supplier as $s): ?>
      <option value="<?= (int)$s['id_supplier'] ?>" <?= (int)($edit['id_supplier'] ?? 0) === (int)$s['id_supplier'] ? 'selected' : '' ?>><?= e($s['nama_supplier']) ?></option>
    <?php endforeach; ?>
  </select>

  <div class="row">
    <div>
      <label for="harga">Harga (Rp) <span class="muted">— opsional</span></label>
      <input id="harga" type="number" name="harga" min="0" step="1" value="<?= $edit && (float)$edit['harga'] > 0 ? (int)$edit['harga'] : '' ?>" placeholder="Kosongkan jika belum ditentukan">
      <small class="muted">Produk baru tanpa harga akan disimpan sementara sebagai Rp0. Saat mengedit, harga lama tetap dipertahankan jika kolom dikosongkan.</small>
    </div>
    <div>
      <label for="stok">Stok</label>
      <input id="stok" type="number" name="stok" min="0" value="<?= $v('stok', 0) ?>" required>
    </div>
  </div>

  <label for="deskripsi">Deskripsi / spesifikasi</label>
  <textarea id="deskripsi" name="deskripsi" placeholder="Satu baris satu spesifikasi, contoh:&#10;Storage: 128GB&#10;Chip: A18"><?= $v('deskripsi') ?></textarea>

  <section class="product-image-picker" aria-labelledby="gallery-title">
    <div class="product-image-picker-head">
      <div>
        <label id="gallery-title">Pilih gambar dari aset proyek</label>
        <p class="muted">Opsional. Pilih foto dari galeri di bawah, atau biarkan kosong.</p>
      </div>
      <span class="image-count"><?= count($imageLibrary) ?> gambar</span>
    </div>
    <?php if ($imageLibrary): ?>
      <div class="product-image-library">
        <?php foreach ($imageLibrary as $img): ?>
          <label class="product-image-option">
            <input type="radio" name="gambar_pustaka" value="<?= e($img['nama']) ?>" <?= ($edit['gambar'] ?? '') === $img['nama'] ? 'checked' : '' ?>>
            <span class="product-image-preview"><img src="<?= e($img['url']) ?>" alt="Pratinjau <?= e($img['nama']) ?>" loading="lazy"></span>
            <span class="product-image-filename" title="<?= e($img['nama']) ?>"><?= e($img['nama']) ?></span>
            <span class="product-image-source"><?= e($img['folder']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">Belum ada gambar di folder uploads/ atau assets/product-display/.</div>
    <?php endif; ?>
  </section>

  <p class="actions actions-spaced">
    <button class="btn">Simpan</button>
    <a class="btn ghost" href="produk.php">Batal</a>
  </p>
</form>

<?php else:
    $cari = trim($_GET['q'] ?? '');
    $dari = 'FROM produk p JOIN brand b ON b.id_brand = p.id_brand JOIN kategori k ON k.id_kategori = p.id_kategori';
    $where = '';
    $args = [];
    if ($cari !== '') {
        $where = ' WHERE (p.nama_produk LIKE ? OR b.nama_brand LIKE ? OR k.nama_kategori LIKE ?)';
        $args = array_fill(0, 3, pola_cari($cari));
    }
    $st = $pdo->prepare('SELECT COUNT(*) ' . $dari . $where);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    [$halaman, $totalHalaman, $offset] = paginasi($total, 10);
    $st = $pdo->prepare('SELECT p.*, b.nama_brand, k.nama_kategori ' . $dari . $where
        . ' ORDER BY p.id_produk DESC LIMIT 10 OFFSET ' . $offset);
    $st->execute($args);
    $rows = $st->fetchAll();
    ?>
<div class="toolbar">
  <div><h1>Produk</h1><p class="sub"><?= $total ?> produk <?= $cari !== '' ? 'cocok dengan “' . e($cari) . '”' : 'terdaftar' ?></p></div>
  <a class="btn" href="?tambah=1">+ Tambah produk</a>
</div>
<?php admin_cari($cari, 'Cari nama produk, brand, atau kategori…'); ?>
<?php if (!$rows): ?>
  <div class="empty"><?= $cari !== '' ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk.' ?></div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th></th><th>Nama</th><th>Kategori</th><th class="num">Harga</th><th class="num">Stok</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr>
      <td><div class="mini"><?= gambar_produk($p['gambar'], $p['nama_produk'], '../') ?></div></td>
      <td><b><?= e($p['nama_produk']) ?></b><div class="muted cell-sub"><?= e($p['nama_brand']) ?></div></td>
      <td><?= e($p['nama_kategori']) ?></td>
      <td class="num"><?= (float)$p['harga'] > 0 ? rupiah($p['harga']) : '<span class="muted">Belum ditentukan</span>' ?></td>
      <td class="num"><span class="stok <?= $p['stok'] <= 0 ? 'out' : ($p['stok'] <= 5 ? 'low' : '') ?>"><?= (int)$p['stok'] ?></span></td>
      <td class="num">
        <div class="actions actions-end">
          <a class="btn ghost sm" href="?edit=<?= (int)$p['id_produk'] ?>">Ubah</a>
          <form method="post" onsubmit="return confirm('Hapus produk ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int)$p['id_produk'] ?>">
            <button class="btn danger sm">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php render_paginasi($halaman, $totalHalaman); ?>
<?php endif; endif; ?>
<?php admin_footer();
