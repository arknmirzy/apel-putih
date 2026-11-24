<?php
/**
 * Webhook Midtrans (HTTP Notification URL).
 * Daftarkan di dashboard Midtrans: Settings > Configuration > Payment Notification URL
 *   https://DOMAIN-ANDA/apel-putih/midtrans-notif.php
 * Midtrans tidak bisa menjangkau localhost; untuk uji lokal gunakan tunnel (mis. ngrok)
 * atau andalkan pengecekan otomatis saat pelanggan kembali dari popup pembayaran (bayar.php).
 */
require_once __DIR__ . '/includes/midtrans.php';

header('Content-Type: text/plain; charset=utf-8');

if (!midtrans_siap()) {
    http_response_code(503);
    exit('Midtrans belum dikonfigurasi');
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Permintaan tidak valid');
}

// Verifikasi tanda tangan: sha512(order_id + status_code + gross_amount + server_key).
$tandaTangan = hash('sha512',
    ($data['order_id'] ?? '') . ($data['status_code'] ?? '') . ($data['gross_amount'] ?? '') . MIDTRANS_SERVER_KEY);
if (!hash_equals($tandaTangan, (string)($data['signature_key'] ?? ''))) {
    http_response_code(403);
    exit('Tanda tangan tidak valid');
}

pastikan_kolom_pembayaran();
midtrans_terapkan_status($data);
echo 'OK';
