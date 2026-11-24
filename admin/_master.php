<?php
/**
 * CRUD sederhana untuk tabel dengan satu kolom nama (brand, kategori).
 * Dipanggil dari brand.php dan kategori.php.
 */
require_once __DIR__ . '/_layout.php';
wajib_admin();

function master_crud(string $tabel, string $kolomId, string $kolomNama, string $label, string $kunciMenu): void
{
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $aksi = $_POST['aksi'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');

        if ($aksi === 'simpan') {
            if ($nama === '') {
                flash("Nama $label wajib diisi.", 'err');
            } elseif ($id > 0) {
                $pdo->prepare("UPDATE $tabel SET $kolomNama = ? WHERE $kolomId = ?")->execute([$nama, $id]);
                flash("$label diperbarui.");
            } else {
                $pdo->prepare("INSERT INTO $tabel ($kolomNama) VALUES (?)")->execute([$nama]);
                flash("$label ditambahkan.");
            }
        } elseif ($aksi === 'hapus') {
            hapus_aman("DELETE FROM $tabel WHERE $kolomId = ?", $id, $label);
        }
        redirect(basename($_SERVER['SCRIPT_NAME']));
    }

    $edit = null;
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare("SELECT * FROM $tabel WHERE $kolomId = ?");
        $st->execute([(int)$_GET['edit']]);
        $edit = $st->fetch() ?: null;
    }
    $rows = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM produk p WHERE p.$kolomId = t.$kolomId) AS jml FROM $tabel t ORDER BY t.$kolomNama")->fetchAll();

    admin_header($label, $kunciMenu);
    ?>
<h1><?= e($label) ?></h1>
<p class="sub">Kelola daftar <?= e(strtolower($label)) ?> produk.</p>

<div class="master-layout">
  <div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Nama</th><th class="num">Produk</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r[$kolomNama]) ?></td>
          <td class="num"><?= (int)$r['jml'] ?></td>
          <td class="num">
            <div class="actions actions-end">
              <a class="btn ghost sm" href="?edit=<?= (int)$r[$kolomId] ?>">Ubah</a>
              <form method="post" onsubmit="return confirm('Hapus <?= e(addslashes($r[$kolomNama])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$r[$kolomId] ?>">
                <button class="btn danger sm">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <form method="post" class="master-form-panel">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= (int)($edit[$kolomId] ?? 0) ?>">
    <p class="master-form-title">
      <?= $edit ? 'Ubah ' : 'Tambah ' ?><?= e(strtolower($label)) ?>
    </p>
    <label for="nama">Nama <?= e(strtolower($label)) ?></label>
    <input id="nama" name="nama" value="<?= e($edit[$kolomNama] ?? '') ?>" required autofocus>
    <p class="actions actions-spaced">
      <button class="btn btn-grow">Simpan</button>
      <?php if ($edit): ?><a class="btn ghost" href="<?= e(basename($_SERVER['SCRIPT_NAME'])) ?>">Batal</a><?php endif; ?>
    </p>
  </form>
</div>
<?php
    admin_footer();
}
