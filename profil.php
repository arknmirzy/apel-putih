<?php
/**
 * Profil pelanggan: ubah data diri (nama, nomor HP, alamat, provinsi, kode pos) dan ganti kata sandi.
 * Email tidak bisa diubah karena menjadi identitas login. Ganti kata sandi meminta kata sandi saat ini.
 */
require_once __DIR__ . '/includes/layout.php';

pastikan_kolom_alamat();
$user = wajib_pelanggan();
$id = (int)$user['id_pelanggan'];

$errProfil = '';
$errSandi = '';
$v = [
    'nama'     => $user['nama_pelanggan'],
    'hp'       => $user['no_hp'] ?? '',
    'alamat'   => $user['alamat'] ?? '',
    'provinsi' => $user['provinsi'] ?? '',
    'kode_pos' => $user['kode_pos'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'profil') {
        foreach ($v as $k => $_) {
            $v[$k] = trim($_POST[$k] ?? '');
        }
        $hp = normalisasi_hp($v['hp']);
        if ($v['nama'] === '' || mb_strlen($v['nama']) > 100) {
            $errProfil = 'Nama wajib diisi (maksimal 100 karakter).';
        } elseif ($hp !== '' && !preg_match('/^08\d{8,12}$/', $hp)) {
            $errProfil = 'Nomor HP tidak valid. Contoh: 08123456789.';
        } elseif ($galat = validasi_wilayah($v['provinsi'], $v['kode_pos'])) {
            $errProfil = $galat;
        } else {
            db()->prepare('UPDATE pelanggan SET nama_pelanggan = ?, no_hp = ?, alamat = ?, provinsi = ?, kode_pos = ? WHERE id_pelanggan = ?')
                ->execute([$v['nama'], $hp ?: null, $v['alamat'] ?: null, $v['provinsi'] ?: null, $v['kode_pos'] ?: null, $id]);
            flash('Profil berhasil diperbarui.');
            redirect('profil.php');
        }
    } elseif ($aksi === 'sandi') {
        $lama = $_POST['password_lama'] ?? '';
        $baru = $_POST['password'] ?? '';
        $ulang = $_POST['password2'] ?? '';
        $adaSandi = !empty($user['password']);
        if ($adaSandi && !password_verify($lama, $user['password'])) {
            $errSandi = 'Kata sandi saat ini salah.';
        } elseif (strlen($baru) < 6) {
            $errSandi = 'Kata sandi baru minimal 6 karakter.';
        } elseif ($baru !== $ulang) {
            $errSandi = 'Konfirmasi kata sandi baru tidak sama.';
        } elseif ($adaSandi && password_verify($baru, $user['password'])) {
            $errSandi = 'Kata sandi baru tidak boleh sama dengan yang lama.';
        } else {
            db()->prepare('UPDATE pelanggan SET password = ? WHERE id_pelanggan = ?')
                ->execute([password_hash($baru, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            flash('Kata sandi berhasil diganti.');
            redirect('profil.php');
        }
    }
}

$st = db()->prepare("SELECT COUNT(*) AS jml, COALESCE(SUM(CASE WHEN status <> 'Dibatalkan' THEN total_harga END), 0) AS belanja
    FROM pesanan WHERE id_pelanggan = ?");
$st->execute([$id]);
$ringkas = $st->fetch();

page_header('Profil saya', 'profil');
?>
<h1>Profil saya</h1>
<p class="sub">Perbarui data diri dan alamat pengirimanmu. Data ini terisi otomatis saat checkout.</p>

<div class="two">
  <div class="profil-stack">
    <form method="post" class="panel" id="data-diri">
      <h2 class="panel-title">Data diri</h2>
      <?php if ($errProfil): ?><div class="flash err" role="alert"><?= e($errProfil) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="profil">
      <label for="email">Email <span class="muted">— tidak bisa diubah</span></label>
      <input id="email" value="<?= e($user['email']) ?>" disabled>
      <label for="nama">Nama</label>
      <input id="nama" name="nama" value="<?= e($v['nama']) ?>" required maxlength="100" autocomplete="name">
      <label for="hp">No. HP <span class="muted">— dipakai untuk pemulihan kata sandi</span></label>
      <input id="hp" name="hp" value="<?= e($v['hp']) ?>" placeholder="08xxxxxxxxxx" inputmode="tel" autocomplete="tel">
      <label for="alamat">Alamat pengiriman</label>
      <textarea id="alamat" name="alamat" autocomplete="street-address"><?= e($v['alamat']) ?></textarea>
      <div class="row">
        <div>
          <label for="provinsi">Provinsi <span class="muted">— opsional</span></label>
          <select id="provinsi" name="provinsi" autocomplete="address-level1"><?= opsi_provinsi($v['provinsi']) ?></select>
        </div>
        <div>
          <label for="kode_pos">Kode pos <span class="muted">— opsional</span></label>
          <input id="kode_pos" name="kode_pos" value="<?= e($v['kode_pos']) ?>" placeholder="12345" maxlength="5" pattern="[0-9]{5}" inputmode="numeric" autocomplete="postal-code" title="5 digit angka">
        </div>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Simpan perubahan</button></p>
    </form>

    <form method="post" class="panel" id="ganti-sandi" autocomplete="off">
      <h2 class="panel-title">Ganti kata sandi</h2>
      <?php if ($errSandi): ?><div class="flash err" role="alert"><?= e($errSandi) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="sandi">
      <?php if (!empty($user['password'])): ?>
        <label for="password_lama">Kata sandi saat ini</label>
        <input id="password_lama" type="password" name="password_lama" required autocomplete="current-password">
      <?php endif; ?>
      <label for="password">Kata sandi baru</label>
      <input id="password" type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password">
      <label for="password2">Ulangi kata sandi baru</label>
      <input id="password2" type="password" name="password2" required minlength="6" autocomplete="new-password">
      <p class="form-actions"><button class="btn" type="submit">Ganti kata sandi</button></p>
    </form>
  </div>

  <aside class="panel profil-ringkas">
    <h2 class="panel-title">Ringkasan akun</h2>
    <div class="sum">
      <div><span>Nama</span><b><?= e($user['nama_pelanggan']) ?></b></div>
      <div><span>Jumlah pesanan</span><b><?= (int)$ringkas['jml'] ?></b></div>
      <div><span>Total belanja</span><b><?= rupiah($ringkas['belanja']) ?></b></div>
    </div>
    <p class="form-actions"><a class="btn ghost" href="pesanan.php">Lihat riwayat pesanan</a></p>
  </aside>
</div>
<?php page_footer();
