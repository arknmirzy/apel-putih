# Apel Putih — Toko iPhone

Aplikasi toko iPhone berbasis PHP + MySQL (XAMPP) dengan tiga peran: pelanggan, admin, dan supplier.

## Cara menjalankan
1. Salin folder proyek ke `htdocs/` (mis. `htdocs/apel-putih`).
2. Nyalakan **Apache** dan **MySQL** di XAMPP.
3. Buka phpMyAdmin, impor `database.sql` (membuat database `iphone_store` beserta data contoh).
4. Buka `http://localhost/apel-putih/`.

Pengaturan database ada di bagian atas `config.php`.

## Pembayaran: Midtrans dan COD
Hanya dua metode yang tersedia di checkout: **Midtrans** (QRIS, Virtual Account, e-wallet, kartu) dan **COD**.

1. Daftar di [Midtrans](https://dashboard.midtrans.com) dan pakai mode **Sandbox** untuk uji coba.
2. Kredensial Sandbox yang diberikan sudah disimpan di `secrets.local.php` (tidak ditampilkan di frontend). Untuk menggantinya, edit file tersebut atau atur environment variable `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, dan `MIDTRANS_IS_PRODUCTION`. Server Key hanya dipakai di backend.
3. Isi **Payment Notification URL** di dashboard Midtrans (Settings > Configuration) dengan `https://DOMAIN-ANDA/apel-putih/midtrans-notif.php`.
   Midtrans tidak bisa menjangkau `localhost`; untuk uji lokal pakai tunnel (mis. ngrok). Tanpa webhook, status tetap diperbarui
   saat pelanggan kembali dari popup pembayaran.
4. Ekstensi `curl` PHP harus aktif (bawaan XAMPP).
5. Untuk produksi, gunakan Server Key dan Client Key Production milik akun yang sudah Live, lalu set `production` ke `true` di `secrets.local.php` (atau environment variable `MIDTRANS_IS_PRODUCTION=true`). Jangan pernah memakai key Sandbox di Production.

Setiap pesanan punya satu catatan di tabel `pembayaran` (metode, status, channel/bank, order ID & ID transaksi Midtrans, waktu bayar). Tabel ini dibuat otomatis bila belum ada dan terlihat di detail pesanan admin.

Alur Midtrans: pesanan dibuat (status Pending, pembayaran *Belum dibayar*) → popup Snap → bila lunas, pesanan otomatis menjadi
*Diproses*; bila dibatalkan/kedaluwarsa (24 jam), pesanan *Dibatalkan* dan stok dikembalikan. Pelanggan bisa membuka ulang
pembayaran lewat tombol **Bayar sekarang** di halaman Pesanan. Pesanan COD langsung berstatus Pending dengan pembayaran *Bayar di tempat*.

## Login (satu halaman: `login.php`)
Peran ditentukan dari akhiran email:

| Email                    | Peran     | Tujuan setelah login     |
|--------------------------|-----------|--------------------------|
| `admin@admin.com`        | Admin     | `admin/index.php`        |
| `supplier1@supplier.com` | Supplier  | `supplier/index.php`     |
| email lain               | Pelanggan | landing page `index.php` |

Bagian sebelum `@` dicocokkan dengan kolom `username` di tabel `admin` / `supplier`.
Email berakhiran `@admin.com` / `@supplier.com` tidak bisa didaftarkan sebagai pelanggan.

Akun contoh: `admin` / `admin123`, `supplier1` / `supplier123`, `supplier2` / `supplier123`.

## Struktur folder
```
apel-putih/
├── config.php            Konfigurasi, koneksi database, dan fungsi bantu bersama
├── database.sql          Skema + data contoh
├── includes/
│   ├── midtrans.php      Klien API Midtrans (buat token Snap, cek & terapkan status, catat ke tabel pembayaran)
│   ├── html.php          <head>, skrip penutup, dan ikon SVG (dipakai semua layout)
│   └── layout.php        Header & footer halaman pelanggan
├── index.php             Beranda & katalog
├── produk.php            Detail produk
├── keranjang.php         Keranjang belanja
├── checkout.php          Checkout & metode pembayaran (Midtrans / COD)
├── bayar.php             Popup pembayaran Midtrans Snap untuk satu pesanan
├── midtrans-notif.php    Webhook notifikasi pembayaran dari Midtrans
├── pesanan.php           Riwayat pesanan pelanggan
├── profil.php            Profil pelanggan: ubah data diri dan ganti kata sandi
├── login.php             Masuk / daftar (pelanggan, admin, supplier)
├── lupa-password.php     Lupa kata sandi (khusus pelanggan): verifikasi email + nomor HP, lalu kata sandi baru
├── logout.php            Keluar (pelanggan)
├── admin/                Dashboard admin (produk, brand, kategori, pesanan, pelanggan, supplier, pengadaan, laporan)
├── supplier/             Dashboard supplier (produk, tabel pengadaan, pesanan produk)
├── assets/               style.css, app.js, video tur, product-display/ (foto produk bawaan)
└── uploads/              Foto produk tambahan (prioritas di atas assets/product-display/)
```

## Lupa kata sandi (pelanggan)
- Tautan *Lupa kata sandi?* di halaman Masuk membuka `lupa-password.php`. Karena proyek ini tidak mengirim email, identitas diverifikasi
  dengan **email + nomor HP yang tersimpan di akun** (format 08xx, 628xx, atau +62 sama saja). Setelah cocok, pelanggan punya 10 menit
  untuk membuat kata sandi baru (minimal 6 karakter, diketik dua kali).
- Verifikasi gagal dibatasi: 5 kali per email atau per alamat IP dalam 15 menit (tabel `percobaan_reset`, dibuat otomatis bila belum ada).
  Pesan galat dibuat sama untuk email tak dikenal, nomor HP salah, dan akun tanpa nomor HP.
- Akun admin (`@admin.com`) dan supplier (`@supplier.com`) tidak bisa memakai fitur ini.

## Fitur pesanan
- **Batalkan pesanan:** pelanggan dapat membatalkan pesanan **COD** yang masih Pending; stok dikembalikan otomatis. Pesanan Midtrans
  tidak bisa dibatalkan sendiri oleh pelanggan karena pembayarannya bisa masuk bersamaan (batal lewat kedaluwarsa atau admin).
- **Admin → Pesanan:** saring menurut status dan *Unduh CSV* (mengikuti saringan yang aktif; pemisah titik koma, terbuka rapi di Excel).
- **Profil pelanggan:** menu *Profil* untuk mengubah data diri dan alamat, serta mengganti kata sandi (meminta kata sandi saat ini).
- **Admin → Laporan:** laporan penjualan per periode (omset, modal, laba kotor, per produk, daftar transaksi) dengan tombol *Cetak* dan *Unduh CSV*.
- **Admin → Produk dan Pelanggan:** kotak pencarian dan paginasi (10 data per halaman).
- **Katalog:** pencarian instan dan saring per kategori, jumlah produk ikut diperbarui.

## Catatan pengembangan
- Setelah mengubah `assets/style.css` atau `assets/app.js`, ganti `ASSET_VERSION` di `config.php` agar cache browser diperbarui.
- Gambar produk dicari berurutan di `uploads/` lalu `assets/product-display/` (lihat `FOLDER_GAMBAR` di `config.php`; galeri di Admin → Produk memakai daftar yang sama).
- Tabel `pengadaan` dibuat otomatis saat dashboard supplier dibuka, sehingga database lama tidak perlu diimpor ulang.


## Keamanan kredensial
- `secrets.local.php` dikecualikan dari Git dan diblokir akses HTTP melalui `.htaccess`.
- Jangan kirim Server Key ke browser, repositori publik, atau pihak lain.
- Jika kredensial pernah dibagikan di tempat umum, lakukan rotasi dari dashboard Midtrans.
