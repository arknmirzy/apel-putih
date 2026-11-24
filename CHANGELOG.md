# Riwayat perubahan

## Centang produk di keranjang sebelum checkout
- **Keranjang (`keranjang.php`):** tampilan baru berupa kartu produk (gambar, nama, harga satuan, jumlah, subtotal, tombol hapus berikon).
  Setiap produk punya kotak centang, plus *Pilih semua* di atas daftar. Produk yang tidak dicentang tampak redup. Total dan jumlah produk di panel
  *Ringkasan belanja* dihitung dari produk yang dicentang saja dan diperbarui langsung. Tombol checkout nonaktif (dengan teks singkat) bila tidak ada
  yang dicentang atau ada produk terpilih tanpa harga. Pesan peringatan harga merah dihapus. Jumlah tersimpan otomatis setelah diubah (tombol *Ubah*
  hanya muncul tanpa JavaScript). Pilihan diingat selama tab terbuka (sessionStorage). Kotak centang memakai `form="form-checkout"` karena tiap
  produk sudah punya form Ubah/Hapus sendiri (form tidak boleh bersarang). Aturan `[hidden]` ditambahkan di CSS karena `.flash` menimpanya.
- **Checkout (`checkout.php`):** hanya produk yang dicentang yang diproses (ringkasan, cek stok, total, dan `detail_pesanan`). Setelah pesanan dibuat,
  hanya produk yang dibeli yang dihapus dari keranjang; produk lain tetap tersimpan. Pilihan disimpan di `$_SESSION['checkout_ids']` dan divalidasi
  ulang di server. Bila belum ada pilihan, seluruh isi keranjang dipakai seperti sebelumnya. Ada tautan *Ubah pilihan* bila hanya sebagian yang dibeli.
- `logout.php` ikut menghapus `checkout_ids`. `ASSET_VERSION` dinaikkan.

## Profil pelanggan, laporan penjualan, serta pencarian dan paginasi admin
- **Profil pelanggan (`profil.php`):** tautan *Profil* baru di menu. Pelanggan dapat mengubah nama, nomor HP, alamat, provinsi, dan kode pos
  (email tidak bisa diubah), serta **mengganti kata sandi** (wajib mengisi kata sandi saat ini; minimal 6 karakter; tidak boleh sama dengan yang lama).
  Ada ringkasan akun (jumlah pesanan dan total belanja). Data profil otomatis terisi di checkout.
- **Admin → Laporan (`admin/laporan.php`):** laporan penjualan per periode dengan filter tanggal dan pintasan *Bulan ini / Bulan lalu / Tahun ini*.
  Menampilkan jumlah pesanan, unit terjual, omset, modal (HPP), dan laba kotor, tabel penjualan per produk, dan daftar transaksi.
  Tombol **Cetak** (tampilan cetak khusus tanpa menu samping) dan **Unduh CSV** (satu baris per item pesanan, pemisah titik koma).
  Definisi omset dan HPP sama dengan grafik di dashboard.
- **Pencarian dan paginasi admin:** daftar **Produk** (cari nama, brand, kategori) dan **Pelanggan** (cari nama, email, nomor HP) kini punya kotak pencarian
  dan 10 data per halaman. Helper baru: `paginasi()` dan `pola_cari()` di `config.php`, `render_paginasi()` di `includes/html.php`, `admin_cari()` di `admin/_layout.php`.
- `login.php`: `profil.php` ditambahkan ke daftar tujuan `next` yang diizinkan. Ikon `laporan` ditambahkan. `ASSET_VERSION` dinaikkan.

## Akun baru tidak lagi langsung login
- **Daftar akun (`login.php`):** setelah berhasil mendaftar, pelanggan tidak lagi otomatis masuk dan dibawa ke beranda. Pelanggan diarahkan ke form
  *Masuk* dengan pesan sukses, kolom email terisi otomatis (sekali pakai lewat sesi, tidak muncul di URL), dan harus mengetik kata sandi yang baru dibuat.
- Parameter `next` kini ikut dibawa dari form daftar, sehingga setelah masuk pelanggan kembali ke halaman tujuan semula (mis. checkout).

## Urut harga dan saring stok dihapus dari beranda
- **Beranda:** dropdown *Urutkan* (harga terendah/tertinggi/terbaru) dan kotak *Hanya yang tersedia* dihapus dari `index.php`, beserta kode
  `sortCards()`, filter stok di `assets/app.js`, dan gaya `.catalog-check` / `.catalog-sort` di `assets/style.css`. Jumlah produk tetap tampil.
- `ASSET_VERSION` dinaikkan agar cache CSS dan JS diperbarui.

## Faktur dihapus, lupa kata sandi pelanggan ditambahkan
- **Faktur pesanan dihapus:** `invoice.php`, `admin/invoice.php`, `includes/invoice.php`, dan `assets/invoice.css` dihapus, beserta tombol
  *Lihat faktur* (Riwayat pesanan) dan *Cetak faktur* (detail pesanan admin) dan gaya CSS-nya.
- **Lupa kata sandi (khusus pelanggan):** halaman baru `lupa-password.php` dan tautan *Lupa kata sandi?* di halaman Masuk. Verifikasi memakai
  email + nomor HP terdaftar, lalu pelanggan membuat kata sandi baru (berlaku 10 menit). Verifikasi gagal dibatasi 5 kali per email/IP per 15 menit
  lewat tabel `percobaan_reset` (ada di `database.sql`, dan dibuat otomatis untuk database lama oleh `pastikan_tabel_percobaan_reset()`).
- `DOMAIN_ADMIN`, `DOMAIN_SUPPLIER`, dan `peran_dari_email()` dipindah dari `login.php` ke `config.php` agar dipakai bersama.
- `ASSET_VERSION` dinaikkan agar cache CSS diperbarui.

## Perapian kode dan fitur baru (batal pesanan, ekspor CSV, urut katalog)
**Keamanan**
- `tes-midtrans.php` dihapus. Halaman diagnosis itu bisa dibuka siapa saja tanpa login dan menampilkan Client Key dan Server Key Midtrans
  apa adanya. Bila proyek pernah dipasang di server yang bisa diakses publik, sebaiknya kunci Midtrans diganti (rotasi) dari dashboard.

**Perapian (tampilan tidak berubah)**
- `Firefly.png` dihapus karena isinya identik byte demi byte dengan `14 pro.png`, yang dipakai `database.sql`.
- `kembalikan_stok()` di `config.php` menggantikan dua salinan SQL pengembalian stok (`includes/midtrans.php` dan `admin/pesanan.php`).
- `tanggal_id()` di `config.php` menggantikan 16 pemanggilan `date('d M Y')`. Nama bulan kini berbahasa Indonesia (Agu, Mei, Okt, Des,
  sebelumnya Aug, May, Oct, Dec).
- Ikon `pengadaan` di `includes/html.php` memakai ikon `supplier` (path SVG-nya sebelumnya ditulis dua kali).
- `assets/style.css`: 23 deklarasi yang tertimpa aturan selector sama dan 8 blok kosong dibuang (sebagian besar sisa blok detail pesanan admin).
  Hasil akhir tiap selector sudah dicek identik sebelum dan sesudah.
- Teks kosong pencarian di `app.js` memakai "kamu" seperti halaman lain (sebelumnya "Anda").

**Fitur baru**
- **Batalkan pesanan COD oleh pelanggan** di Riwayat pesanan (dengan konfirmasi). Stok dikembalikan; `batalkan_pesanan_pelanggan()` memakai
  `SELECT ... FOR UPDATE` sehingga aman bila admin mengubah status pada saat yang sama.
- **Admin → Pesanan:** saringan status dengan jumlah per status dan tombol *Unduh CSV*. Sel yang berawalan `=`, `+`, `-`, atau `@` diberi
  tanda kutip agar tidak dijalankan sebagai rumus.
- **Katalog:** urut harga terendah/tertinggi/terbaru dan saring "Hanya yang tersedia", jumlah produk ikut diperbarui.
- `ASSET_VERSION` dinaikkan agar cache CSS dan JS diperbarui.

## Detail pesanan admin: gambar dan harga lebih besar
- Card "Produk dipesan" kini mengisi seluruh tinggi card (sejajar dengan card Data pelanggan): gambar produk 210px mengikuti tinggi card, nama produk, harga satuan, subtotal, dan total pesanan diperbesar. Tampilan HP ikut disesuaikan.
- `ASSET_VERSION` dinaikkan agar cache CSS diperbarui.

## Perapian kode (tanpa mengubah tampilan)
- **Folder gambar tunggal:** folder `poto aipong/` dihapus karena seluruh isinya selalu tertimpa `assets/product-display/` (nama file sama, dan `url_gambar()` memilih `assets/product-display/` lebih dulu). `FOLDER_GAMBAR` di `config.php` kini `['uploads', 'assets/product-display']`, dan galeri gambar di Admin → Produk memakai daftar yang sama (sebelumnya membaca `poto aipong/`, sehingga label sumbernya tidak sesuai dengan gambar yang sebenarnya tampil).
- **CSS yang tertimpa dibuang:** 92 deklarasi di `assets/style.css` yang selalu kalah oleh aturan dengan selector sama di bagian bawah file (mis. `.admin-shell`, `.side`, `.card:active`, `:root`) dihapus, beserta 13 aturan yang menjadi kosong. Hasil akhir tiap selector sudah dicek identik sebelum dan sesudah.
- **CSS tidak terpakai dibuang:** kelas `keu-cards`, `keu-card`, `keu-note`, `keu-rugi`, `keu-table` (sisa panel omset lama) dan variabel `--panel-hover`, `--surface-raised`, `--line-strong`, `--shadow-lg`, `--interaction-move-ease`, `--auth-ink`, `--auth-muted` yang tidak dirujuk di mana pun.
- Pemeriksaan PHP dan JS: tidak ditemukan fungsi, konstanta, atau ikon yang tidak terpakai, maupun deklarasi ganda.
- `ASSET_VERSION` dinaikkan agar cache CSS diperbarui.

## Video tur beranda versi 3D baru
- `assets/video/apel-putih-preview.mp4` dan `apel-putih-poster.jpg` diganti dengan video animasi 3D baru (38 detik, 1080p, 60 fps) yang mengikuti tema monokrom toko. Video lama dihapus.
- Isi video mengikuti alur terbaru: checkout Midtrans/COD dengan pilihan kurir, dashboard admin dengan grafik untung bersih, detail pesanan admin (status, kurir, nomor resi), dan konfirmasi pesanan diterima oleh pelanggan.
- Daftar bab di `index.php` disesuaikan dengan alur video baru (5 bab: Beranda, Katalog produk, Keranjang dan checkout, Panel admin, Riwayat pesanan).
- Video baru memakai efek suara lembut (sapuan halus saat pindah adegan, ketukan ringan saat tombol ditekan, bunyi ketik pelan, dan denting pendek saat notifikasi). Tombol bisukan di pemutar tetap ada.
- Teks penutup video diganti menjadi "Pilih iPhone, pesan online, dan pantau pesananmu sampai tiba."
- Alamat video dan poster kini memakai `?v=ASSET_VERSION` agar peramban tidak memakai berkas lama dari cache; `ASSET_VERSION` dinaikkan.

## Grafik untung bersih bulanan
- Panel "Omset & laba" (kartu dan tabel) diganti **grafik batang hitam-putih "Untung bersih bulanan"** di dashboard admin: 12 bulan terakhir. Geser (mouse, sentuh, atau tombol panah) untuk melihat bulan, tahun, dan untungnya; batang tumbuh saat halaman dibuka, angka berubah mulus, dan ukuran grafik menyesuaikan lebar layar. Nilai dihitung dari omset dikurangi modal (HPP) per bulan (`omset_bulanan()` di `admin/index.php`); omset dihitung dari pesanan tidak dibatalkan yang sudah lunas atau COD yang sudah selesai, HPP dari rata-rata harga modal pengadaan yang sudah diterima.

## Login per peran, kurir, pengadaan diterima, dan omset
- **Login tidak saling mengeluarkan:** setiap peran (pelanggan, admin, supplier) kini punya sesi dan cookie sendiri (`mulai_sesi()` di `config.php`). Login admin atau supplier di tab lain tidak lagi menghapus sesi pelanggan, dan sebaliknya.
- **Checkout:** pelanggan wajib memilih kurir (JNE, J&T Express, SiCepat, AnterAja, Ninja Xpress, POS Indonesia). Pilihan disimpan di `pesanan.kurir` dan menjadi nilai awal di detail pesanan admin.
- **Pengadaan:** halaman baru **Admin → Pengadaan** dengan aksi *Tandai diterima* (mencatat tanggal terima dan menambah stok produk sesuai jumlah) dan *Batalkan*. Supplier dapat mengisi kurir dan nomor resi saat menandai dikirim. Kolom baru di tabel `pengadaan`: `no_resi`, `kurir`, `tanggal_diterima`.
- **Omset & laba:** dashboard admin menampilkan omset, modal (HPP), dan untung kotor untuk bulan ini, bulan lalu, dan tahun ini, serta rincian per bulan. Omset dihitung dari pesanan tidak dibatalkan yang sudah lunas atau COD yang sudah selesai. HPP adalah rata-rata harga modal per produk dari pengadaan yang sudah diterima.

## Nomor resi dan konfirmasi penerimaan
- Kolom baru di tabel `pesanan`: `no_resi`, `kurir`, `tanggal_kirim`, `tanggal_diterima` (ditambahkan otomatis untuk database lama lewat `pastikan_kolom_pengiriman()`, sudah ada di `database.sql`).
- Admin: di detail pesanan, isi kurir dan nomor resi. Status **Dikirim** wajib disertai nomor resi; tanggal kirim dan tanggal terima dicatat otomatis.
- Pelanggan: nomor resi tampil di riwayat pesanan saat sudah diisi. Saat status Dikirim, tombol **Pesanan sudah saya terima** mengubah status menjadi Selesai.
- Belum ada integrasi API kurir; status pengiriman tetap diisi manual oleh admin.

## Tabel pembayaran dan data produk baru
- Tabel `pembayaran` baru (satu catatan per pesanan): metode, status, channel/bank, order ID & ID transaksi Midtrans, jumlah, waktu bayar, respons Midtrans. Ada di `database.sql` dan dibuat otomatis untuk database lama (`pastikan_tabel_pembayaran()`; pesanan lama ikut dicatat).
- Checkout (Midtrans dan COD), webhook, dan pengecekan status mengisi tabel ini; admin melihatnya di detail pesanan.
- Data produk di `database.sql` kini mengikuti `iphone_store.sql` (16 produk, ID asli dipertahankan) dan kategori baru **iPhone Duo**. Data contoh pengadaan disesuaikan ke produk yang ada.


## Metode pembayaran: Midtrans dan COD
- Checkout kini hanya menyediakan **Midtrans** (Snap) dan **COD**; Transfer Bank dan E-Wallet manual dihapus.
- Baru: `includes/midtrans.php`, `bayar.php` (popup Snap), `midtrans-notif.php` (webhook dengan verifikasi signature).
- Kolom `midtrans_order_id`, `snap_token`, `status_bayar` ditambahkan ke tabel `pesanan` (otomatis untuk database lama, sudah ada di `database.sql`).
- Pembayaran lunas mengubah pesanan Pending menjadi Diproses; cancel/expire membatalkan pesanan dan mengembalikan stok.
- Halaman Pesanan pelanggan punya tombol **Bayar sekarang**; admin melihat status pembayaran Midtrans di daftar dan detail pesanan.
- Pesanan lama dengan label Transfer Bank / E-Wallet tetap tampil apa adanya.

## Provinsi dan kode pos
- Pelanggan dapat mengisi provinsi (daftar 38 provinsi) dan kode pos (5 digit) saat mendaftar dan saat checkout; keduanya opsional.
- Kolom `provinsi` dan `kode_pos` ditambahkan ke tabel `pelanggan` (otomatis untuk database lama, sudah ada di `database.sql`).
- Admin melihat provinsi dan kode pos di halaman Pelanggan dan detail Pesanan.

## Perapian kode
- `includes/html.php` baru: `<head>`, skrip penutup, dan ikon SVG dipakai bersama oleh layout pelanggan, admin, dan supplier
  (sebelumnya tiga salinan; fungsi `admin_icon()` dan `supplier_icon()` digabung menjadi `icon()`).
- `config.php`: pengelompokan per bagian, konstanta `ASSET_VERSION` (menggantikan string versi yang tertulis di 6 tempat),
  helper `url_gambar()`, `label_metode()`, dan `ringkasan_keranjang()` (dipakai bersama `keranjang.php` dan `checkout.php`).
- `checkout.php`: tiga kartu pembayaran dan daftar metode yang berulang kini bersumber dari satu array `$metodeBayar`;
  skrip inline dipindah ke `assets/app.js` (`initPaymentCards`).
- Atribut `style="..."` statis di file PHP diganti kelas pembantu di akhir `assets/style.css`.
- `login.php`: gambar panel kiri kini memakai `gambar_produk()` (urutan folder gambar sama dengan halaman lain).
- `app.js`: pengecekan nama folder `/apel-putih/` yang tertulis mati diganti pola umum; selector `.order` yang tidak ada dihapus.

## Dihapus karena tidak terpakai
- `daftar.php`, `preview.html`, `admin_preview.html` (tidak ditautkan dari mana pun).
- Fitur saldo: refund saldo saat pembatalan pesanan, penyesuaian label "Saldo digital", migrasi kolom `saldo`,
  dan kolom `saldo` di `database.sql` (metode pembayaran saldo sudah tidak tersedia di checkout).
- Migrasi otomatis kolom `metode_pembayaran` (sudah ada di `database.sql`); migrasi tabel `pengadaan` kini hanya dijalankan di dashboard supplier.
- Konstanta `STATUS_PENGADAAN` yang tidak dipakai.
- 18 aturan CSS untuk halaman yang sudah tidak ada (login admin/supplier terpisah, `.order`, unggah gambar, tautan footer admin).

## Fitur sebelumnya
- Login 3-in-1: peran ditentukan dari akhiran email (`@admin.com`, `@supplier.com`).
- Tabel pengadaan di dashboard supplier (ajukan, tandai dikirim, batalkan).
- Hapus pelanggan di panel admin (beserta riwayat pesanannya).
- Tampilan baru riwayat pesanan pelanggan dan detail pesanan admin.
