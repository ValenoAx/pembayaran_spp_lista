<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// PERBAIKAN: kelas.php mengirim parameter "id_kelas" (bukan "id"),
// jadi sebelumnya $_GET['id'] selalu kosong dan tidak ada baris yang terhapus.
if (!isset($_GET['id_kelas'])) {
    header('Location: kelas.php');
    exit();
}
$id = mysqli_real_escape_string($koneksi, $_GET['id_kelas']);

$query = mysqli_query($koneksi, "DELETE FROM kelas WHERE id_kelas = '$id'");

if ($query) {
    echo "<script>alert('Data kelas berhasil dihapus!'); window.location='kelas.php';</script>";
} else {
    echo "<script>alert('Gagal menghapus data: " . addslashes(keamanan_error($koneksi)) . "'); window.location='kelas.php';</script>";
}
?>