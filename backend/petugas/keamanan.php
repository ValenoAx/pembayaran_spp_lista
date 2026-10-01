<?php
/*
 * keamanan.php
 * ------------------------------------------------------------
 * Dipanggil di paling atas SEMUA halaman admin (menggantikan session_start()).
 * Tugasnya: mengamankan sesi, memasang header keamanan, dan menolak
 * permintaan "titipan" dari website lain (serangan CSRF).
 * Tampilan dan cara kerja halaman admin TIDAK berubah.
 */

// 1) Mulai session dengan cookie yang lebih aman:
//    - httponly : cookie nggak bisa dibaca oleh JavaScript (melawan pencurian session lewat XSS)
//    - samesite : cookie nggak ikut terkirim kalau request datang dari website lain
//    - strict   : PHP menolak session id karangan orang lain (melawan session fixation)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// 2) Header keamanan
header('X-Frame-Options: SAMEORIGIN');          // halaman nggak bisa dimasukin ke iframe web lain (anti clickjacking)
header('X-Content-Type-Options: nosniff');      // browser nggak boleh "menebak" tipe file
header('Referrer-Policy: same-origin');         // alamat halaman admin nggak bocor ke website lain

// 3) Pesan error database ke layar diganti pesan umum, aslinya dicatat di log server
function keamanan_error($koneksi)
{
    error_log('DB error: ' . mysqli_error($koneksi));
    return 'terjadi kesalahan pada database';
}

// 4) Anti-CSRF: pastikan request benar-benar datang dari website kita sendiri
function keamanan_tolak()
{
    http_response_code(403);
    echo "<script>alert('Permintaan ditolak demi keamanan. Silakan ulangi dari halaman admin.'); window.location='index.php';</script>";
    exit();
}

function keamanan_cek_asal()
{
    $metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $file   = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $hapus  = (strpos($file, 'hapus_') === 0);          // halaman hapus_*.php dipanggil lewat link (GET)
    $kirim  = ($metode !== 'GET' && $metode !== 'HEAD'); // semua form (POST)

    if (!$hapus && !$kirim) {
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

    // b) Cadangan: cocokkan alamat asal (Origin / Referer) dengan alamat website kita
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

    // c) Nggak ada keterangan asal sama sekali:
    //    form (POST) dibiarkan, tapi hapus lewat link wajib punya bukti asal
    if ($hapus) {
        keamanan_tolak();
    }
}

keamanan_cek_asal();