CREATE DATABASE IF NOT EXISTS iphone_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iphone_store;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS percobaan_reset, pengadaan, pembayaran, detail_pesanan, pesanan, produk, supplier, pelanggan, kategori, brand, admin;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin (
  id_admin INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_admin VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE brand (
  id_brand INT AUTO_INCREMENT PRIMARY KEY,
  nama_brand VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE kategori (
  id_kategori INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE pelanggan (
  id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
  nama_pelanggan VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NULL,
  no_hp VARCHAR(20) NULL,
  alamat TEXT NULL,
  provinsi VARCHAR(60) NULL,
  kode_pos VARCHAR(10) NULL
) ENGINE=InnoDB;

CREATE TABLE supplier (
  id_supplier INT AUTO_INCREMENT PRIMARY KEY,
  nama_supplier VARCHAR(100) NOT NULL,
  no_telp VARCHAR(20) NULL,
  alamat TEXT NULL,
  username VARCHAR(50) NULL UNIQUE,
  password VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE produk (
  id_produk INT AUTO_INCREMENT PRIMARY KEY,
  id_brand INT NOT NULL,
  id_kategori INT NOT NULL,
  id_supplier INT NULL,
  nama_produk VARCHAR(150) NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  stok INT NOT NULL DEFAULT 0,
  deskripsi TEXT NULL,
  gambar VARCHAR(255) NULL,
  FOREIGN KEY (id_brand) REFERENCES brand(id_brand) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE pesanan (
  id_pesanan INT AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT NOT NULL,
  tanggal_pesanan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_harga DECIMAL(12,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  metode_pembayaran VARCHAR(50) NOT NULL DEFAULT 'COD (Bayar di Tempat)',
  midtrans_order_id VARCHAR(64) NULL,
  snap_token VARCHAR(100) NULL,
  status_bayar VARCHAR(30) NOT NULL DEFAULT 'Belum dibayar',
  no_resi VARCHAR(60) NULL,
  kurir VARCHAR(60) NULL,
  tanggal_kirim DATETIME NULL,
  tanggal_diterima DATETIME NULL,
  UNIQUE KEY uq_midtrans_order (midtrans_order_id),
  FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE detail_pesanan (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  id_produk INT NOT NULL,
  jumlah INT NOT NULL,
  harga_satuan DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pembayaran (
  id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  metode VARCHAR(50) NOT NULL,
  midtrans_order_id VARCHAR(64) NULL,
  midtrans_transaction_id VARCHAR(64) NULL,
  tipe_pembayaran VARCHAR(40) NULL,
  bank VARCHAR(40) NULL,
  jumlah DECIMAL(12,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Belum dibayar',
  status_transaksi VARCHAR(30) NULL,
  fraud_status VARCHAR(30) NULL,
  waktu_bayar DATETIME NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  respons_midtrans TEXT NULL,
  UNIQUE KEY uq_pembayaran_pesanan (id_pesanan),
  UNIQUE KEY uq_pembayaran_order (midtrans_order_id),
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pencatat verifikasi gagal pada fitur lupa kata sandi (pembatas percobaan per email dan per IP).
CREATE TABLE percobaan_reset (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reset_email (email, waktu),
  KEY idx_reset_ip (ip, waktu)
) ENGINE=InnoDB;

CREATE TABLE pengadaan (
  id_pengadaan INT AUTO_INCREMENT PRIMARY KEY,
  id_supplier INT NOT NULL,
  id_produk INT NOT NULL,
  tanggal_pengadaan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  jumlah INT NOT NULL,
  harga_satuan DECIMAL(12,2) NOT NULL,
  total_harga DECIMAL(14,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Diajukan',
  catatan VARCHAR(255) NULL,
  no_resi VARCHAR(60) NULL,
  kurir VARCHAR(60) NULL,
  tanggal_diterima DATETIME NULL,
  FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Data awal ----------
-- Login admin default: admin / admin123
INSERT INTO admin (username, password, nama_admin) VALUES
('admin', '$2y$10$Tst78OIBbVR1F8CjFaEtrezLaWdapuTSTAIFI2cjZ0aHbIZKcJdpK', 'Administrator');

INSERT INTO brand (nama_brand) VALUES ('Apple');
INSERT INTO `kategori` (`id_kategori`, `nama_kategori`) VALUES
(1, 'iPhone Standar'),
(2, 'iPhone Plus & Air'),
(3, 'iPhone Pro'),
(4, 'iPhone Duo');
-- Login supplier contoh: supplier1 / supplier123 dan supplier2 / supplier123
INSERT INTO supplier (nama_supplier, no_telp, alamat, username, password) VALUES
('PT Distributor Gadget', '021-5551234', 'Jakarta', 'supplier1', '$2y$10$o0Yj/yWUBlqg4J3Oyjl7n.5CumOILiQVjQJdkz37zongOqnTJyxy6'),
('CV Mobile Jaya', '022-5554321', 'Bandung', 'supplier2', '$2y$10$o0Yj/yWUBlqg4J3Oyjl7n.5CumOILiQVjQJdkz37zongOqnTJyxy6');

-- Data produk (sesuai iphone_store.sql). ID produk dipertahankan, gambar mengacu ke assets/product-display/.
INSERT INTO `produk` (`id_produk`, `id_brand`, `id_kategori`, `id_supplier`, `nama_produk`, `harga`, `stok`, `deskripsi`, `gambar`) VALUES
(2, 1, 2, 2, 'iPhone 15 Plus 256GB', 16999000.00, 5, 'Storage: 256GB\r\nLayar: 6.7 inch Super Retina XDR\r\nChip: A16\r\nKonektor: USB-C', 'Firefly (6).png'),
(3, 1, 1, 1, 'iPhone 16 128GB', 17999000.00, 10, 'Storage: 128GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A18\r\nKonektor: USB-C', 'Firefly (4).png'),
(4, 1, 1, 2, 'iPhone 16 Plus 256GB', 19499000.00, 8, 'Storage: 256GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A18\r\nKonektor: LIGHTNING', 'Firefly (3).png'),
(5, 1, 1, 2, 'iPhone 17 256GB', 19999000.00, 14, 'Storage: 256GB\r\nLayar: 6.3 inch Super Retina XDR\r\nChip: A19\r\nKonektor: USB-C', 'Firefly (2).png'),
(6, 1, 1, 1, 'iPhone 18 512GB', 32499000.00, 6, 'Storage: 512GB\r\nLayar: 6.3 inch Super Retina XDR\r\nChip: A20 Pro\r\nKonektor: USB-C', '18 basic.png'),
(7, 1, 3, 1, 'iPhone 17 Pro Max  512GB', 29499000.00, 7, 'Storage: 512GB\r\nLayar: 6.9 inch Super Retina XDR\r\nChip: A19 Pro\r\nKonektor: USB-C', '17 Pro.png'),
(8, 1, 3, 2, 'iPhone 18 Pro Max Burgundy 512GB', 34999000.00, 5, 'Storage: 512GB\r\nLayar: 6.3 inch Super Retina XDR\r\nChip: A20 Pro\r\nKonektor: USB-C', 'Burgundy.png'),
(9, 1, 4, 1, 'iPhone Duo 1TB', 60999000.00, 4, 'Storage: 1TB\r\nLayar: 7.6 inch Super Retina XDR\r\nChip: A20 Pro\r\nKonektor: USB-C', 'duo black.png'),
(10, 1, 4, 2, 'iPhone Duo 2TB', 75999000.00, 3, 'Storage: 2TB\r\nLayar: 7.6 inch Super Retina XDR\r\nChip: A20 Pro\r\nKonektor: USB-C', 'duo white.png'),
(11, 1, 2, 2, 'iPhone Air 256GB', 17299000.00, 5, 'Storage: 256GB\r\nLayar: 6.5 inch Super Retina XDR\r\nChip: A19 Pro\r\nKonektor: USB-C', 'air.png'),
(12, 1, 2, 2, 'iPhone Air 512GB', 21749000.00, 5, 'Storage: 512GB\r\nLayar: 6.5 inch Super Retina XDR\r\nChip: A19 Pro\r\nKonektor: USB-C', 'Firefly (7).png'),
(13, 1, 2, 2, 'iPhone Air 1TB', 25999000.00, 3, 'Storage: 1TB\r\nLayar: 6.5 inch Super Retina XDR\r\nChip: A19 Pro\r\nKonektor: USB-C', 'Firefly (1).png'),
(14, 1, 1, 2, 'iPhone 15 256GB', 15975000.00, 15, 'Storage: 128GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A16\r\nKonektor: USB-C', 'Firefly (5).png'),
(15, 1, 3, 2, 'iPhone 14 Pro 512GB', 13499000.00, 5, 'Storage: 512GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A16\r\nKonektor: LIGHTNING', '14 pro.png'),
(16, 1, 1, 2, 'iPhone 14 128GB', 9400000.00, 3, 'Storage: 128GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A15\r\nKonektor: LIGHTNING', '14 basic.png'),
(17, 1, 3, 2, 'iPhone 13 Pro 256GB', 8000000.00, 2, 'Storage: 256GB\r\nLayar: 6.1 inch Super Retina XDR\r\nChip: A15\r\nKonektor: LIGHTNING', '13pro.png');

-- Data contoh pengadaan (status: Diajukan, Dikirim, Diterima, Dibatalkan)
INSERT INTO pengadaan (id_supplier, id_produk, tanggal_pengadaan, jumlah, harga_satuan, total_harga, status, catatan) VALUES
(1, 3, NOW() - INTERVAL 12 DAY, 20, 15500000, 310000000, 'Diterima', 'Pengiriman batch awal'),
(1, 7, NOW() - INTERVAL 6 DAY, 10, 27500000, 275000000, 'Dikirim', 'Dikirim via ekspedisi darat'),
(1, 9, NOW() - INTERVAL 2 DAY, 4, 57000000, 228000000, 'Diajukan', NULL),
(2, 5, NOW() - INTERVAL 9 DAY, 15, 18500000, 277500000, 'Diterima', NULL),
(2, 11, NOW() - INTERVAL 3 DAY, 6, 16000000, 96000000, 'Diajukan', 'Stok menipis, mohon diprioritaskan');
