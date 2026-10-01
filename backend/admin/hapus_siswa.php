<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$nisn = $_GET['nisn'] ?? '';
$stmt = mysqli_prepare($koneksi, "DELETE FROM siswa WHERE nisn = ?");
mysqli_stmt_bind_param($stmt, "s", $nisn);
$hapus = mysqli_stmt_execute($stmt);

if ($hapus) {
    echo "<script>alert('Data siswa berhasil dihapus!'); window.location='siswa.php';</script>";
} else {
    echo "<script>alert('Gagal menghapus data: " . keamanan_error($koneksi) . "'); window.location='siswa.php';</script>";
}
?>