<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

if (isset($_GET['id_pembayaran'])) {
    $id_pembayaran = (int) $_GET['id_pembayaran'];

    $stmt = mysqli_prepare($koneksi, "DELETE FROM pembayaran WHERE id_pembayaran = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_pembayaran);
    $query = mysqli_stmt_execute($stmt);

    if ($query) {
        echo "<script>alert('Data transaksi berhasil dihapus!'); window.location='pembayaran.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data transaksi.'); window.location='pembayaran.php';</script>";
    }
} else {
    header("location:pembayaran.php");
}
?>