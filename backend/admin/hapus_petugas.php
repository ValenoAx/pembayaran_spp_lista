<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($koneksi, "DELETE FROM petugas WHERE id_petugas = ? AND level = 'petugas'");
mysqli_stmt_bind_param($stmt, "i", $id);
$query = mysqli_stmt_execute($stmt);

if ($query) {
    echo "<script>alert('Data petugas berhasil dihapus!'); window.location='petugas.php';</script>";
} else {
    echo "<script>alert('Gagal menghapus data: " . keamanan_error($koneksi) . "'); window.location='petugas.php';</script>";
}
?>