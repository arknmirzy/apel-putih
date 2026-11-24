<?php
/**
 * Halaman login & daftar tunggal.
 * Peran ditentukan dari akhiran email:
 *   username@admin.com    -> admin    -> admin/index.php
 *   username@supplier.com -> supplier -> supplier/index.php
 *   email lain            -> pelanggan -> landing page (index.php)
 * "username" adalah kolom username di tabel admin / supplier.
 */
require_once __DIR__ . '/includes/layout.php';

$mode = ($_POST['mode'] ?? $_GET['mode'] ?? 'masuk') === 'daftar' ? 'daftar' : 'masuk';

// Hanya izinkan redirect ke halaman lokal yang dikenal (untuk pelanggan).
$boleh = ['checkout.php', 'pesanan.php', 'keranjang.php', 'profil.php', 'index.php'];
$next = $_GET['next'] ?? $_POST['next'] ?? 'index.php';
if (!in_array($next, $boleh, true)) {
    $next = 'index.php';
}

// Halaman ini selalu menampilkan form. Setiap peran punya sesi sendiri (lihat mulai_sesi di config.php),
// jadi login sebagai admin atau supplier tidak mengeluarkan pelanggan yang sedang login di tab lain.

$err = '';
$email = '';
// Setelah daftar, isi kolom email di form masuk (hanya email, kata sandi tetap diketik sendiri).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['email_baru'])) {
    $email = (string)$_SESSION['email_baru'];
}
unset($_SESSION['email_baru']);
$v = ['nama' => '', 'email' => '', 'hp' => '', 'alamat' => '', 'provinsi' => '', 'kode_pos' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass = $_POST['password'] ?? '';

    if ($mode === 'daftar') {
        // ---------- Daftar akun pelanggan ----------
        $v = [
            'nama'   => trim($_POST['nama'] ?? ''),
            'email'  => strtolower(trim($_POST['email'] ?? '')),
            'hp'     => trim($_POST['hp'] ?? ''),
            'alamat' => trim($_POST['alamat'] ?? ''),
            'provinsi' => trim($_POST['provinsi'] ?? ''),
            'kode_pos' => trim($_POST['kode_pos'] ?? ''),
        ];
        if ($v['nama'] === '' || $v['email'] === '' || $pass === '') {
            $err = 'Nama, email, dan kata sandi wajib diisi.';
        } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $err = 'Format email tidak valid.';
        } elseif (peran_dari_email($v['email'])[0] !== 'pelanggan') {
            $err = 'Email dengan akhiran @' . DOMAIN_ADMIN . ' atau @' . DOMAIN_SUPPLIER . ' khusus akun internal dan tidak bisa didaftarkan.';
        } elseif (strlen($pass) < 6) {
            $err = 'Kata sandi minimal 6 karakter.';
        } elseif ($galat = validasi_wilayah($v['provinsi'], $v['kode_pos'])) {
            $err = $galat;
        } else {
            pastikan_kolom_alamat();
            $cek = db()->prepare('SELECT 1 FROM pelanggan WHERE email = ?');
            $cek->execute([$v['email']]);
            if ($cek->fetch()) {
                $err = 'Email sudah terdaftar. Silakan masuk.';
            } else {
                db()->prepare('INSERT INTO pelanggan (nama_pelanggan, email, password, no_hp, alamat, provinsi, kode_pos) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$v['nama'], $v['email'], password_hash($pass, PASSWORD_DEFAULT), $v['hp'] ?: null, $v['alamat'] ?: null, $v['provinsi'] ?: null, $v['kode_pos'] ?: null]);
                // Akun baru TIDAK langsung login: arahkan ke form masuk agar pengguna
                // memasukkan email & kata sandi yang baru dibuat.
                $_SESSION['email_baru'] = $v['email']; // isi otomatis kolom email (sekali pakai)
                flash('Akun berhasil dibuat. Silakan masuk dengan email dan kata sandi yang baru kamu buat.');
                redirect('login.php' . ($next !== 'index.php' ? '?next=' . urlencode($next) : ''));
            }
        }
    } else {
        // ---------- Masuk (semua peran lewat form yang sama) ----------
        $email = strtolower(trim($_POST['email'] ?? ''));
        [$peran, $uname] = peran_dari_email($email);

        if ($peran === 'admin') {
            $st = db()->prepare('SELECT * FROM admin WHERE username = ?');
            $st->execute([$uname]);
            $a = $st->fetch();
            if ($a && password_verify($pass, $a['password'])) {
                mulai_sesi('admin');
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$a['id_admin'];
                $_SESSION['admin_nama'] = $a['nama_admin'];
                redirect('admin/index.php');
            }
        } elseif ($peran === 'supplier') {
            $st = db()->prepare('SELECT id_supplier, nama_supplier, password FROM supplier WHERE username = ? LIMIT 1');
            $st->execute([$uname]);
            $s = $st->fetch();
            if ($s && !empty($s['password']) && password_verify($pass, $s['password'])) {
                mulai_sesi('supplier');
                session_regenerate_id(true);
                $_SESSION['supplier_id'] = (int)$s['id_supplier'];
                $_SESSION['supplier_nama'] = $s['nama_supplier'];
                redirect('supplier/index.php');
            }
        } else {
            $st = db()->prepare('SELECT * FROM pelanggan WHERE email = ?');
            $st->execute([$email]);
            $u = $st->fetch();
            if ($u && $u['password'] && password_verify($pass, $u['password'])) {
                mulai_sesi('pelanggan');
                session_regenerate_id(true);
                $_SESSION['pelanggan_id'] = (int)$u['id_pelanggan'];
                redirect($next); // kembali ke landing page / halaman tujuan
            }
        }
        $err = 'Email atau kata sandi salah.';
    }
}

$suffix = $next !== 'index.php' ? '&next=' . urlencode($next) : '';
$gambarVisual = $mode === 'daftar' ? 'Firefly (3).png' : '17 Pro.png';

page_header($mode === 'daftar' ? 'Daftar' : 'Masuk', 'login');
?>
<section class="auth-stage auth-login <?= $mode === 'daftar' ? 'auth-register' : '' ?>" aria-labelledby="auth-title">
  <aside class="auth-visual">
    <div class="auth-visual-top">
      <span class="auth-brand-mark" aria-hidden="true"><span></span></span>
      <span class="auth-visual-brand"><?= e(STORE_NAME) ?></span>
      <span class="auth-member-tag"><?= $mode === 'daftar' ? 'JOIN THE COMMUNITY' : 'MEMBER ACCESS' ?></span>
    </div>

    <div class="auth-visual-copy">
      <span class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> <?= $mode === 'daftar' ? 'WELCOME TO APEL PUTIH' : 'THE APPLE EXPERIENCE' ?></span>
      <h2><?= $mode === 'daftar' ? 'Upgrade caramu menikmati teknologi.' : 'Teknologi terbaik, dimulai dari sini.' ?></h2>
      <p><?= $mode === 'daftar'
          ? 'Buat akun dalam beberapa langkah. Simpan detailmu, pesan lebih praktis, dan ikuti perjalanan pesananmu.'
          : 'Temukan iPhone pilihanmu dengan pengalaman belanja yang lebih mudah, aman, dan nyaman.' ?></p>
    </div>

    <div class="auth-product-stage <?= $mode === 'daftar' ? 'auth-register-product' : '' ?>" aria-label="Ilustrasi produk iPhone">
      <span class="auth-orbit auth-orbit-one"></span>
      <span class="auth-orbit auth-orbit-two"></span>
      <div class="auth-product-glow"></div>
      <?= gambar_produk($gambarVisual, 'iPhone pilihan Apel Putih') ?>
      <div class="auth-floating-note auth-note-top"><span class="auth-note-icon">✦</span><span><b>Curated for you</b><small>Pilihan premium</small></span></div>
      <div class="auth-floating-note auth-note-bottom"><span class="auth-note-check">✓</span><span><b>Belanja lebih tenang</b><small>Proses mudah & transparan</small></span></div>
    </div>

    <div class="auth-visual-bottom">
      <span>YOUR NEXT IPHONE STARTS HERE</span>
      <span class="auth-visual-line"></span>
      <span><?= $mode === 'daftar' ? '02 / 02' : '01 / 02' ?></span>
    </div>
  </aside>

  <div class="auth-content">
    <div class="auth-content-inner <?= $mode === 'daftar' ? 'auth-register-inner' : '' ?>">
      <div class="auth-mobile-brand"><span class="auth-brand-mark" aria-hidden="true"><span></span></span><span><?= e(STORE_NAME) ?></span></div>

      <?php if ($mode === 'daftar'): ?>
      <span class="auth-eyebrow auth-eyebrow-light">MULAI PERJALANANMU</span>
      <h1 id="auth-title">Buat akun Apel Putih.</h1>
      <p class="auth-intro">Daftar sekarang agar pengalaman belanjamu semakin praktis.</p>

      <?php if ($err): ?><div class="flash err auth-error" role="alert"><?= e($err) ?></div><?php endif; ?>

      <form class="auth-form auth-register-form" method="post" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="daftar">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="auth-form-grid">
          <div class="auth-field">
            <label for="nama">Nama lengkap <span class="auth-required">*</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon" aria-hidden="true">◎</span>
              <input id="nama" name="nama" value="<?= e($v['nama']) ?>" placeholder="Nama sesuai identitas" required autocomplete="name">
            </div>
          </div>
          <div class="auth-field">
            <label for="hp">Nomor HP <span class="auth-optional">Opsional</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon" aria-hidden="true">⌕</span>
              <input id="hp" name="hp" value="<?= e($v['hp']) ?>" placeholder="08xxxxxxxxxx" autocomplete="tel" inputmode="tel">
            </div>
          </div>
          <div class="auth-field auth-field-full">
            <label for="email">Alamat email <span class="auth-required">*</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon" aria-hidden="true">@</span>
              <input id="email" type="email" name="email" value="<?= e($v['email']) ?>" placeholder="nama@email.com" required autocomplete="email" inputmode="email">
            </div>
          </div>
          <div class="auth-field auth-field-full">
            <label for="password">Buat kata sandi <span class="auth-required">*</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon auth-lock-icon" aria-hidden="true">▣</span>
              <input id="password" type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6" autocomplete="new-password" data-password-strength>
              <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
            </div>
            <div class="auth-strength" aria-live="polite"><span class="auth-strength-track"><i></i></span><small data-strength-label>Gunakan minimal 6 karakter.</small></div>
          </div>
          <div class="auth-field auth-field-full">
            <label for="alamat">Alamat <span class="auth-optional">Opsional</span></label>
            <div class="auth-input-wrap auth-textarea-wrap">
              <span class="auth-input-icon auth-address-icon" aria-hidden="true">⌖</span>
              <textarea id="alamat" name="alamat" rows="2" placeholder="Jalan, nomor rumah, kecamatan, kota…" autocomplete="street-address"><?= e($v['alamat']) ?></textarea>
            </div>
          </div>
          <div class="auth-field">
            <label for="provinsi">Provinsi <span class="auth-optional">Opsional</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon" aria-hidden="true">◉</span>
              <select id="provinsi" name="provinsi" autocomplete="address-level1"><?= opsi_provinsi($v['provinsi']) ?></select>
            </div>
          </div>
          <div class="auth-field">
            <label for="kode_pos">Kode pos <span class="auth-optional">Opsional</span></label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon" aria-hidden="true">#</span>
              <input id="kode_pos" name="kode_pos" value="<?= e($v['kode_pos']) ?>" placeholder="12345" maxlength="5" pattern="[0-9]{5}" inputmode="numeric" autocomplete="postal-code" title="5 digit angka">
            </div>
          </div>
        </div>
        <button class="btn auth-submit" type="submit"><span>Buat akun</span><span class="auth-submit-arrow" aria-hidden="true">→</span></button>
        <p class="auth-terms">Dengan membuat akun, kamu dapat melanjutkan proses pemesanan di <?= e(STORE_NAME) ?>.</p>
      </form>

      <div class="auth-switch"><span>Sudah punya akun?</span> <a href="login.php<?= $suffix ? '?' . ltrim($suffix, '&') : '' ?>">Masuk di sini <span aria-hidden="true">↗</span></a></div>
      <div class="auth-assurance"><span class="auth-assurance-icon">✓</span><span>Daftar gratis, tidak perlu berlangganan.</span></div>

      <?php else: ?>
      <span class="auth-eyebrow auth-eyebrow-light">SELAMAT DATANG KEMBALI</span>
      <h1 id="auth-title">Senang melihatmu lagi.</h1>
      <p class="auth-intro">Masuk untuk melanjutkan belanja dan memantau pesananmu.</p>

      <?php if ($err): ?><div class="flash err auth-error" role="alert"><?= e($err) ?></div><?php endif; ?>

      <form class="auth-form" method="post" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="masuk">
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="auth-field">
          <label for="email">Alamat email</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon" aria-hidden="true">@</span>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" placeholder="nama@email.com" required autocomplete="email" inputmode="email">
          </div>
        </div>

        <div class="auth-field">
          <div class="auth-label-row"><label for="password">Kata sandi</label><a class="auth-forgot" href="lupa-password.php">Lupa kata sandi?</a></div>
          <div class="auth-input-wrap">
            <span class="auth-input-icon auth-lock-icon" aria-hidden="true">▣</span>
            <input id="password" type="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
          </div>
        </div>

        <button class="btn auth-submit" type="submit"><span>Masuk ke akun</span><span class="auth-submit-arrow" aria-hidden="true">→</span></button>
      </form>

      <div class="auth-switch"><span>Belum punya akun?</span> <a href="login.php?mode=daftar<?= e($suffix) ?>">Buat akun sekarang <span aria-hidden="true">↗</span></a></div>
      <div class="auth-assurance"><span class="auth-assurance-icon">✓</span><span>Informasi akunmu diproses dengan aman.</span></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
