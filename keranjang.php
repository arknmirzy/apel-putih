<?php
require_once __DIR__ . '/includes/layout.php';

$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($aksi === 'checkout') {
        // Hanya produk yang dicentang (dan memang ada di keranjang) yang dibawa ke checkout.
        $pilih = array_map('intval', (array)($_POST['pilih'] ?? []));
        $pilih = array_values(array_unique(array_filter($pilih, fn($i) => isset($cart[$i]))));
        if (!$pilih) {
            flash('Centang minimal satu produk yang ingin di-checkout.', 'err');
            redirect('keranjang.php');
        }
        $_SESSION['checkout_ids'] = $pilih;
        redirect('checkout.php');
    }

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $st = db()->prepare('SELECT stok, harga FROM produk WHERE id_produk = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        $jml = max(0, (int)($_POST['jumlah'] ?? 1));
        if (!$row) {
            flash('Produk tidak ditemukan.', 'err');
        } elseif ((float)$row['harga'] <= 0) {
            unset($cart[$id]);
            flash('Produk belum dapat dimasukkan ke keranjang karena harganya belum ditentukan.', 'err');
        } else {
            $baru = $aksi === 'tambah' ? ($cart[$id] ?? 0) + $jml : $jml;
            if ($baru <= 0) {
                unset($cart[$id]);
            } elseif ($baru > $row['stok']) {
                $cart[$id] = (int)$row['stok'];
                flash('Jumlah disesuaikan dengan stok yang tersedia (' . (int)$row['stok'] . ').', 'err');
                if ($row['stok'] <= 0) {
                    unset($cart[$id]);
                }
            } else {
                $cart[$id] = $baru;
                if ($aksi === 'tambah') {
                    flash('Ditambahkan ke keranjang.');
                }
            }
        }
    } elseif ($aksi === 'hapus') {
        unset($cart[$id]);
    }
    $_SESSION['cart'] = $cart;
    redirect('keranjang.php');
}

[$items, $total, $adaHargaBelumDitentukan] = ringkasan_keranjang($cart);

page_header('Keranjang', 'keranjang');
?>
<h1>Keranjang</h1>
<p class="sub">Periksa pesananmu sebelum checkout.</p>

<?php if (!$items): ?>
  <div class="empty">Keranjang masih kosong. <a href="index.php">Lihat produk</a></div>
<?php else: ?>
  <?php $jmlItem = count($items); ?>
  <div class="two">
    <div class="cart-main">
      <div class="cart-bar">
        <label class="cart-pick" for="cart-pilih-semua">
          <input class="cart-check" type="checkbox" id="cart-pilih-semua" checked>
          <span>Pilih semua</span>
        </label>
        <span class="muted cart-bar-count"><?= $jmlItem ?> produk</span>
      </div>
      <div class="cart-list">
      <?php foreach ($items as $p): $idp = (int)$p['id_produk']; ?>
        <article class="cart-item is-selected">
          <label class="cart-pick" for="pilih-<?= $idp ?>">
            <input class="cart-check" type="checkbox" id="pilih-<?= $idp ?>" name="pilih[]" value="<?= $idp ?>" form="form-checkout" checked
                   data-subtotal="<?= (float)$p['subtotal'] ?>" data-belum="<?= $p['belum_dihargai'] ? 1 : 0 ?>"
                   aria-label="Pilih <?= e($p['nama_produk']) ?> untuk di-checkout">
          </label>
          <a class="cart-img" href="produk.php?id=<?= $idp ?>" tabindex="-1" aria-hidden="true"><?= gambar_produk($p['gambar'] ?? null, $p['nama_produk']) ?></a>
          <div class="cart-body">
            <div class="cart-top">
              <div class="cart-title">
                <a class="cart-name" href="produk.php?id=<?= $idp ?>"><?= e($p['nama_produk']) ?></a>
                <div class="cart-unit">
                  <?php if ($p['belum_dihargai']): ?>
                    <span class="cart-tag">Harga belum ditentukan</span>
                  <?php else: ?>
                    <?= rupiah($p['harga']) ?> <span class="muted">/ unit</span>
                  <?php endif; ?>
                </div>
                <?php if ((int)$p['stok'] < (int)$p['jumlah']): ?>
                  <div class="cart-tag">Stok tersisa <?= max(0, (int)$p['stok']) ?></div>
                <?php endif; ?>
              </div>
              <form method="post" class="cart-del">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $idp ?>">
                <button class="cart-del-btn" aria-label="Hapus <?= e($p['nama_produk']) ?> dari keranjang" title="Hapus">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
              </form>
            </div>
            <div class="cart-ctl">
              <form method="post" class="cart-qty-form">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id" value="<?= $idp ?>">
                <input class="qty" type="number" name="jumlah" value="<?= (int)$p['jumlah'] ?>" min="1" max="<?= max(1, (int)$p['stok']) ?>" aria-label="Jumlah <?= e($p['nama_produk']) ?>">
                <button class="btn ghost sm cart-ubah">Ubah</button>
              </form>
              <div class="cart-sub"><?= $p['belum_dihargai'] ? '—' : rupiah($p['subtotal']) ?></div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </div>
    <aside class="panel sum cart-summary">
      <form id="form-checkout" method="post" action="keranjang.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="checkout">
        <h2 class="sum-title">Ringkasan belanja</h2>
        <div><span>Produk dipilih</span><b id="cart-dipilih"><?= $jmlItem ?> dari <?= $jmlItem ?></b></div>
        <div class="tot"><span>Total</span><span id="cart-total"><?= rupiah($total) ?></span></div>
        <p class="cart-submit-wrap"><button class="btn btn-block" id="cart-submit" <?= $adaHargaBelumDitentukan ? 'disabled' : '' ?>><span id="cart-submit-txt"><?= $adaHargaBelumDitentukan ? 'Ada produk tanpa harga' : 'Lanjut ke checkout' ?></span></button></p>
      </form>
    </aside>
  </div>
<?php endif; ?>
<?php page_footer();
