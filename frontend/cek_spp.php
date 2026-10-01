<?php
/**
 * frontend/cek_spp.php — Pengatur alur: verifikasi (NISN + NIS) lalu tampilkan status SPP siswa
 * LETAKKAN DI: pembayaran_spp/frontend/cek_spp.php
 *
 * Pembagian tugas file frontend:
 *   includes/fungsi.php    -> konstanta & fungsi bantu (rp, tglIndo, dst.)
 *   includes/data_spp.php  -> query database & perhitungan status
 *   views/status_spp.php   -> tampilan HTML
 *   assets/css, assets/js  -> gaya dan skrip halaman
 */
require_once __DIR__ . '/keamanan.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/includes/fungsi.php';
require_once __DIR__ . '/includes/data_spp.php';

// ------------------------------------------------------------
// 1) KELUAR
// ------------------------------------------------------------
if (isset($_GET['keluar'])) {
    unset($_SESSION['siswa_nisn'], $_SESSION['siswa_masuk_pada']);
    header('Location: index.php');
    exit();
}

// ------------------------------------------------------------
// 2) PROSES FORM (POST dari index.php)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sekarang = time();

    // Batas percobaan per perangkat (IP) - tidak bisa dilewati dengan hapus cookie
    $sisa_ip = keamanan_ip_terkunci();
    if ($sisa_ip > 0) {
        balikKeDepan('Terlalu banyak percobaan dari perangkat ini. Coba lagi sekitar ' . max(1, (int) ceil($sisa_ip / 60)) . ' menit lagi.');
    }

    // Token keamanan form (CSRF)
    if (!isset($_POST['csrf'], $_SESSION['csrf_cek']) || !hash_equals($_SESSION['csrf_cek'], (string) $_POST['csrf'])) {
        balikKeDepan('Formulir kedaluwarsa. Silakan coba lagi.');
    }

    // Sedang dikunci karena terlalu sering salah?
    $kunci_sampai = (int) ($_SESSION['cek_kunci_sampai'] ?? 0);
    if ($kunci_sampai > $sekarang) {
        $sisa_menit = (int) ceil(($kunci_sampai - $sekarang) / 60);
        balikKeDepan("Terlalu banyak percobaan. Coba lagi sekitar $sisa_menit menit lagi.");
    }

    $nisn = trim($_POST['nisn'] ?? '');
    $nis  = trim($_POST['nis'] ?? '');
    if ($nisn === '' || $nis === '') {
        balikKeDepan('NISN dan NIS wajib diisi.');
    }

    // Cari lewat NISN dulu, baru bandingkan NIS-nya
    $siswa_cari = cariSiswaByNisn($koneksi, $nisn);
    $cocok = ($siswa_cari && hash_equals((string) $siswa_cari['nis'], $nis)) ? $siswa_cari : null;

    if ($cocok) {
        session_regenerate_id(true);
        $_SESSION['siswa_nisn']       = $cocok['nisn'];
        $_SESSION['siswa_masuk_pada'] = $sekarang;
        unset($_SESSION['cek_gagal'], $_SESSION['cek_kunci_sampai']);
        header('Location: cek_spp.php');
        exit();
    }

    // Salah: hitung percobaan, kunci kalau sudah terlalu banyak
    $_SESSION['cek_gagal'] = (int) ($_SESSION['cek_gagal'] ?? 0) + 1;
    keamanan_catat_gagal();
    if ($_SESSION['cek_gagal'] >= GAGAL_MAKS) {
        $_SESSION['cek_kunci_sampai'] = $sekarang + KUNCI_DETIK;
        $_SESSION['cek_gagal'] = 0;
        balikKeDepan('Terlalu banyak percobaan. Pengecekan dikunci 5 menit.');
    }

    if (!$siswa_cari) {
        balikKeDepan('NISN yang kamu masukkan salah atau belum terdaftar. Periksa lagi ya.');
    }
    balikKeDepan('NIS yang kamu masukkan salah. Periksa lagi ya.');
}

// ------------------------------------------------------------
// 3) TAMPILAN (GET) — wajib punya sesi siswa yang masih aktif
// ------------------------------------------------------------
if (empty($_SESSION['siswa_nisn'])) {
    header('Location: index.php');
    exit();
}
if (time() - (int) ($_SESSION['siswa_masuk_pada'] ?? 0) > SESI_MAKS_DETIK) {
    unset($_SESSION['siswa_nisn'], $_SESSION['siswa_masuk_pada']);
    balikKeDepan('Sesi berakhir demi keamanan. Silakan masukkan NISN dan NIS lagi.');
}
header('Cache-Control: no-store');

$nisn  = $_SESSION['siswa_nisn'];
$siswa = ambilProfilSiswa($koneksi, $nisn);
if (!$siswa) {
    unset($_SESSION['siswa_nisn'], $_SESSION['siswa_masuk_pada']);
    balikKeDepan('Data siswa tidak ditemukan.');
}

// Siapkan data untuk tampilan
$tarif         = ambilTarifSpp($koneksi);
$pembayaran    = ambilPembayaranSiswa($koneksi, $nisn);
$riwayat       = $pembayaran['riwayat'];
$total_dibayar = $pembayaran['total'];

$status       = susunStatusSpp($siswa, $tarif, $pembayaran['bayar']);
$daftar_tahun = $status['daftar_tahun'];
$tab_awal     = $status['tab_awal'];
$data_tahun   = $status['data_tahun'];
$total_lunas  = $status['total_lunas'];
$jml_tunggak  = $status['jml_tunggak'];
$rp_tunggak   = $status['rp_tunggak'];

$label_status = labelStatusSpp();
$inisial      = strtoupper(mb_substr(trim($siswa['nama']), 0, 1));

require __DIR__ . '/views/status_spp.php';