<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

if (isset($_POST['simpan'])) {
    $nama_kelas = trim($_POST['nama_kelas']);
    $kompetensi_keahlian = trim($_POST['kompetensi_keahlian']);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, kompetensi_keahlian) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $nama_kelas, $kompetensi_keahlian);
    $query = mysqli_stmt_execute($stmt);

    if ($query) {
        echo "<script>alert('Data kelas berhasil ditambahkan!'); window.location='kelas.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah data!'); window.location='kelas.php';</script>";
    }
}
?>