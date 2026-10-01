<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

// Tolak logout "titipan" dari website lain (misalnya gambar/link jebakan di situs lain).
// Klik tombol Keluar dari aplikasi sendiri (same-origin) atau ketik alamat langsung (none) tetap jalan.
$sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($sfs !== '' && !in_array($sfs, ['same-origin', 'none'], true)) {
    header("location: login.php");
    exit();
}

// Kosongkan isi session, hapus cookie-nya di browser, lalu hancurkan session di server
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header("location: login.php");
exit();