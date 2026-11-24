<?php
require_once __DIR__ . '/../config.php';
unset($_SESSION['supplier_id'], $_SESSION['supplier_nama']);
flash('Kamu berhasil keluar dari portal supplier.');
redirect('../login.php');
