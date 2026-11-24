<?php
require_once __DIR__ . '/_layout.php';
wajib_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($aksi === 'hapus') {
        hapus_aman('DELETE FROM supplier WHERE id_supplier = ?', $id, 'Supplier');
        redirect('supplier.php');
    }

    if ($aksi === 'simpan') {
        $nama = trim($_POST['nama_supplier'] ?? '');
        $telp = trim($_POST['no_telp'] ?? '') ?: null;
        $alamat = trim($_POST['alamat'] ?? '') ?: null;
        $user = trim($_POST['username'] ?? '') ?: null;
        $pass = $_POST['password'] ?? '';
        $back = $id > 0 ? "supplier.php?edit=$id" : 'supplier.php?tambah=1';

        if ($nama === '' || !$user || ($id === 0 && $pass === '')) {
            flash('Nama, username, dan kata sandi awal supplier wajib diisi.', 'err');
            redirect($back);
        }
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE supplier SET nama_supplier=?, no_telp=?, alamat=?, username=? WHERE id_supplier=?')
                    ->execute([$nama, $telp, $alamat, $user, $id]);
                if ($pass !== '') {
                    $pdo->prepare('UPDATE supplier SET password=? WHERE id_supplier=?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
                flash('Supplier diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO supplier (nama_supplier, no_telp, alamat, username, password) VALUES (?,?,?,?,?)')
                    ->execute([$nama, $telp, $alamat, $user, $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null]);
                flash('Supplier ditambahkan.');
            }
        } catch (PDOException $ex) {
            flash($ex->getCode() === '23000' ? 'Username sudah dipakai supplier lain.' : 'Gagal menyimpan supplier.', 'err');
            redirect($back);
        }
        redirect('supplier.php');
    }
}

$form = isset($_GET['tambah']) || isset($_GET['edit']);
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM supplier WHERE id_supplier = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
    if (!$edit) {
        flash('Supplier tidak ditemukan.', 'err');
        redirect('supplier.php');
    }
}

admin_header('Supplier', 'supplier');

if ($form):
    $v = fn(string $k) => e($edit[$k] ?? '');
    ?>
<p class="muted"><a href="supplier.php">← Daftar supplier</a></p>
<h1><?= $edit ? 'Ubah supplier' : 'Tambah supplier' ?></h1>
<form method="post" class="panel form-panel form-panel--sm">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <input type="hidden" name="id" value="<?= (int)($edit['id_supplier'] ?? 0) ?>">
  <label for="nama_supplier">Nama supplier</label>
  <input id="nama_supplier" name="nama_supplier" value="<?= $v('nama_supplier') ?>" required>
  <label for="no_telp">No. telepon</label>
  <input id="no_telp" name="no_telp" value="<?= $v('no_telp') ?>">
  <label for="alamat">Alamat</label>
  <textarea id="alamat" name="alamat" class="textarea-sm"><?= $v('alamat') ?></textarea>
  <div class="row">
    <div>
      <label for="username">Username portal supplier</label>
      <input id="username" name="username" value="<?= $v('username') ?>" required autocomplete="username">
    </div>
    <div>
      <label for="password"><?= $edit ? 'Kata sandi baru (opsional)' : 'Kata sandi portal supplier' ?></label>
      <input id="password" type="password" name="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password">
    </div>
  </div>
  <p class="actions actions-spaced">
    <button class="btn">Simpan</button>
    <a class="btn ghost" href="supplier.php">Batal</a>
  </p>
</form>

<?php else:
    $rows = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM produk p WHERE p.id_supplier = s.id_supplier) AS jml FROM supplier s ORDER BY s.nama_supplier')->fetchAll();
    ?>
<div class="toolbar">
  <div><h1>Supplier</h1><p class="sub">Pemasok barang dan akun portal supplier</p></div>
  <div class="actions"><a class="btn" href="?tambah=1">+ Tambah supplier</a></div>
</div>
<?php if (!$rows): ?>
  <div class="empty">Belum ada supplier.</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Username</th><th class="num">Produk</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): ?>
    <tr>
      <td><b><?= e($s['nama_supplier']) ?></b></td>
      <td><?= e($s['no_telp'] ?: '—') ?></td>
      <td><?= e($s['alamat'] ?: '—') ?></td>
      <td><?= e($s['username'] ?: '—') ?></td>
      <td class="num"><?= (int)$s['jml'] ?></td>
      <td class="num">
        <div class="actions actions-end">
          <a class="btn ghost sm" href="?edit=<?= (int)$s['id_supplier'] ?>">Ubah</a>
          <form method="post" onsubmit="return confirm('Hapus supplier ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int)$s['id_supplier'] ?>">
            <button class="btn danger sm">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; endif; ?>
<?php admin_footer();
