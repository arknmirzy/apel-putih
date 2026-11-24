<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['pelanggan_id'], $_SESSION['cart'], $_SESSION['checkout_ids']);
session_regenerate_id(true);
flash('Kamu sudah keluar.');
redirect('index.php');
