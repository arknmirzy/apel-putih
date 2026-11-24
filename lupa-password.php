<?php
/**
 * Lupa kata sandi (khusus akun pelanggan).
 * Tanpa pengiriman email, identitas diverifikasi lewat email + nomor HP yang tersimpan di akun:
 *   Langkah 1 (verifikasi): email dan nomor HP harus cocok dengan data akun.
 *   Langkah 2 (sandi): buat kata sandi baru; berlaku RESET_BERLAKU_MENIT menit setelah verifikasi.
 * Verifikasi gagal dibatasi per email dan per IP (lihat RESET_MAKS_GAGAL di config.php).
 * Akun admin (@admin.com) dan supplier (@supplier.com) tidak bisa memakai halaman ini.
 */
require_once __DIR__ . '/includes/layout.php';

// Batalkan verifikasi yang sedang berjalan.
if (isset($_GET['ulang'])) {
    unset($_SESSION['reset_pelanggan_id'], $_SESSION['reset_berlaku']);
    redirect('lupa-password.php');
}

$err = '';
$email = '';
$hp = '';

$idReset = (int)($_SESSION['reset_pelanggan_id'] ?? 0);
if ($idReset && (int)($_SESSION['reset_berlaku'] ?? 0) < time()) {
    unset($_SESSION['reset_pelanggan_id'], $_SESSION['reset_berlaku']);
    $idReset = 0;
    $err = 'Waktu untuk membuat kata sandi baru sudah habis. Silakan verifikasi ulang.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($idReset) {
        // ---------- Langkah 2: simpan kata sandi baru ----------
        $pass = $_POST['password'] ?? '';
        $ulang = $_POST['password2'] ?? '';
        if (strlen($pass) < 6) {
            $err = 'Kata sandi minimal 6 karakter.';
        } elseif ($pass !== $ulang) {
            $err = 'Konfirmasi kata sandi tidak sama.';
        } else {
            $st = db()->prepare('UPDATE pelanggan SET password = ? WHERE id_pelanggan = ?');
            $st->execute([password_hash($pass, PASSWORD_DEFAULT), $idReset]);
            unset($_SESSION['reset_pelanggan_id'], $_SESSION['reset_berlaku']);
            session_regenerate_id(true);
            flash('Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.');
            redirect('login.php');
        }
    } else {
        // ---------- Langkah 1: verifikasi email + nomor HP ----------
        $email = strtolower(trim($_POST['email'] ?? ''));
        $hp = trim($_POST['hp'] ?? '');

        if ($email === '' || $hp === '') {
            $err = 'Email dan nomor HP wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Format email tidak valid.';
        } elseif (peran_dari_email($email)[0] !== 'pelanggan') {
            $err = 'Fitur lupa kata sandi hanya untuk akun pelanggan.';
        } elseif (reset_diblokir($email)) {
            $err = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . RESET_JENDELA_MENIT . ' menit.';
        } else {
            $id = reset_verifikasi($email, $hp);
            if ($id !== null) {
                session_regenerate_id(true);
                $_SESSION['reset_pelanggan_id'] = $id;
                $_SESSION['reset_berlaku'] = time() + RESET_BERLAKU_MENIT * 60;
                redirect('lupa-password.php');
            }
            reset_catat_gagal($email);
            // Pesan sama untuk email tak dikenal, nomor HP salah, atau akun tanpa nomor HP.
            $err = 'Email atau nomor HP tidak cocok dengan data akun.';
        }
    }
}

$langkah = $idReset ? 'sandi' : 'verifikasi';

page_header('Lupa kata sandi', 'login');
?>
<section class="auth-stage auth-login" aria-labelledby="auth-title">
  <aside class="auth-visual">
    <div class="auth-visual-top">
      <span class="auth-brand-mark" aria-hidden="true"><span></span></span>
      <span class="auth-visual-brand"><?= e(STORE_NAME) ?></span>
      <span class="auth-member-tag">ACCOUNT RECOVERY</span>
    </div>

    <div class="auth-visual-copy">
      <span class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> PEMULIHAN AKUN</span>
      <h2>Lupa kata sandi? Akunmu bisa dipulihkan.</h2>
      <p>Verifikasi dengan email dan nomor HP terdaftar, lalu buat kata sandi baru.</p>
    </div>

    <div class="auth-product-stage" aria-label="Ilustrasi produk iPhone">
      <span class="auth-orbit auth-orbit-one"></span>
      <span class="auth-orbit auth-orbit-two"></span>
      <div class="auth-product-glow"></div>
      <?= gambar_produk('17 Pro.png', 'iPhone pilihan Apel Putih') ?>
      <div class="auth-floating-note auth-note-top"><span class="auth-note-icon">✦</span><span><b>Dua langkah singkat</b><small>Verifikasi lalu sandi baru</small></span></div>
      <div class="auth-floating-note auth-note-bottom"><span class="auth-note-check">✓</span><span><b>Tetap aman</b><small>Percobaan gagal dibatasi</small></span></div>
    </div>

    <div class="auth-visual-bottom">
      <span>YOUR NEXT IPHONE STARTS HERE</span>
      <span class="auth-visual-line"></span>
      <span><?= $langkah === 'verifikasi' ? '01 / 02' : '02 / 02' ?></span>
    </div>
  </aside>

  <div class="auth-content">
    <div class="auth-content-inner">
      <div class="auth-mobile-brand"><span class="auth-brand-mark" aria-hidden="true"><span></span></span><span><?= e(STORE_NAME) ?></span></div>

      <?php if ($langkah === 'verifikasi'): ?>
      <span class="auth-eyebrow auth-eyebrow-light">LANGKAH 1 DARI 2</span>
      <h1 id="auth-title">Pulihkan akunmu.</h1>
      <p class="auth-intro">Masukkan email akun dan nomor HP yang kamu isi saat mendaftar.</p>

      <?php if ($err): ?><div class="flash err auth-error" role="alert"><?= e($err) ?></div><?php endif; ?>

      <form class="auth-form" method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="auth-field">
          <label for="email">Alamat email</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon" aria-hidden="true">@</span>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" placeholder="nama@email.com" required autocomplete="email" inputmode="email">
          </div>
        </div>
        <div class="auth-field">
          <label for="hp">Nomor HP terdaftar</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon" aria-hidden="true">⌕</span>
            <input id="hp" name="hp" value="<?= e($hp) ?>" placeholder="08xxxxxxxxxx" required autocomplete="tel" inputmode="tel">
          </div>
        </div>
        <button class="btn auth-submit" type="submit"><span>Verifikasi akun</span><span class="auth-submit-arrow" aria-hidden="true">→</span></button>
      </form>

      <div class="auth-switch"><span>Sudah ingat kata sandi?</span> <a href="login.php">Masuk di sini <span aria-hidden="true">↗</span></a></div>
      <div class="auth-assurance"><span class="auth-assurance-icon">✓</span><span>Fitur ini membutuhkan nomor HP yang tersimpan di akunmu. Hanya untuk akun pelanggan.</span></div>

      <?php else: ?>
      <span class="auth-eyebrow auth-eyebrow-light">LANGKAH 2 DARI 2</span>
      <h1 id="auth-title">Buat kata sandi baru.</h1>
      <p class="auth-intro">Verifikasi berhasil. Buat kata sandi baru dalam <?= (int)RESET_BERLAKU_MENIT ?> menit.</p>

      <?php if ($err): ?><div class="flash err auth-error" role="alert"><?= e($err) ?></div><?php endif; ?>

      <form class="auth-form" method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="auth-field">
          <label for="password">Kata sandi baru</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon auth-lock-icon" aria-hidden="true">▣</span>
            <input id="password" type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6" autocomplete="new-password" data-password-strength>
            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
          </div>
          <div class="auth-strength" aria-live="polite"><span class="auth-strength-track"><i></i></span><small data-strength-label>Gunakan minimal 6 karakter.</small></div>
        </div>
        <div class="auth-field">
          <label for="password2">Ulangi kata sandi baru</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon auth-lock-icon" aria-hidden="true">▣</span>
            <input id="password2" type="password" name="password2" placeholder="Ketik ulang kata sandi" required minlength="6" autocomplete="new-password">
            <button class="auth-password-toggle" type="button" data-password-toggle="password2" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
          </div>
        </div>
        <button class="btn auth-submit" type="submit"><span>Simpan kata sandi</span><span class="auth-submit-arrow" aria-hidden="true">→</span></button>
      </form>

      <div class="auth-switch"><span>Bukan akunmu?</span> <a href="lupa-password.php?ulang=1">Mulai ulang verifikasi <span aria-hidden="true">↗</span></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
