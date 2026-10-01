<?php
/*
 * frontend/keamanan.php
 * LETAKKAN DI: pembayaran_spp/frontend/keamanan.php  (sejajar dengan index.php & cek_spp.php)
 *
 * Dipanggil di paling atas halaman frontend (siswa) dan halaman petugas.
 * Menggantikan session_start(). Tugasnya: mengamankan sesi, memasang header
 * keamanan, menolak request palsu dari website lain (CSRF), dan membatasi
 * tebak-tebakan NISN/NIS per perangkat. Tampilan & cara kerja halaman tidak berubah.
 */

// 1) Mulai session dengan cookie yang lebih aman
//    httponly : cookie nggak bisa dibaca JavaScript
//    samesite : cookie nggak ikut terkirim kalau request datang dari website lain
//    strict   : PHP menolak session id karangan orang lain
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// 2) Header keamanan
header('X-Frame-Options: SAMEORIGIN');      // nggak bisa dimasukin iframe web lain (anti clickjacking)
header('X-Content-Type-Options: nosniff');  // browser nggak boleh "menebak" tipe file
header('Referrer-Policy: same-origin');     // alamat halaman nggak bocor ke website lain

// 3) Anti-CSRF: pastikan request benar-benar datang dari website kita sendiri
function keamanan_tolak()
{
    http_response_code(403);
    echo "<script>alert('Permintaan ditolak demi keamanan. Silakan ulangi dari halaman aplikasi.'); window.location='index.php';</script>";
    exit();
}

function keamanan_cek_asal()
{
    $metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($metode === 'GET' || $metode === 'HEAD') {
        return; // halaman biasa (cuma melihat), nggak perlu dicek
    }

    // a) Browser modern kasih tahu sendiri asal request-nya
    $sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($sfs !== '') {
        if ($sfs === 'same-origin') {
            return;
        }
        keamanan_tolak();
    }

    // b) Cadangan: cocokkan Origin / Referer dengan alamat website kita
    $asal = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($asal !== '') {
        $host_asal = parse_url($asal, PHP_URL_HOST);
        $port_asal = parse_url($asal, PHP_URL_PORT);
        $alamat    = $host_asal . ($port_asal ? ':' . $port_asal : '');
        $host_kita = $_SERVER['HTTP_HOST'] ?? '';
        if ($host_asal && strcasecmp($alamat, $host_kita) === 0) {
            return;
        }
        keamanan_tolak();
    }
}

// 4) Batas tebak-tebakan NISN/NIS per perangkat (IP).
//    Pembatas lama hanya mengandalkan cookie, jadi bisa dilewati dengan menghapus cookie.
//    Yang ini mencatat percobaan GAGAL per IP di file sementara.
const KEAMANAN_IP_MAKS    = 15;   // maksimal gagal per IP ...
const KEAMANAN_IP_JENDELA = 900;  // ... dalam 15 menit

function keamanan_file_ip()
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spp_gagal_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '.txt';
}

function keamanan_baca_gagal()
{
    $f = keamanan_file_ip();
    if (!is_file($f)) {
        return [];
    }
    $batas = time() - KEAMANAN_IP_JENDELA;
    $waktu = array_map('intval', file($f, FILE_IGNORE_NEW_LINES) ?: []);
    return array_values(array_filter($waktu, function ($t) use ($batas) { return $t > $batas; }));
}

// Mengembalikan sisa detik terkunci (0 = tidak terkunci)
function keamanan_ip_terkunci()
{
    $waktu = keamanan_baca_gagal();
    if (count($waktu) >= KEAMANAN_IP_MAKS) {
        return max(1, min($waktu) + KEAMANAN_IP_JENDELA - time());
    }
    return 0;
}

function keamanan_catat_gagal()
{
    $waktu   = keamanan_baca_gagal();
    $waktu[] = time();
    @file_put_contents(keamanan_file_ip(), implode("\n", $waktu), LOCK_EX);
}

keamanan_cek_asal();