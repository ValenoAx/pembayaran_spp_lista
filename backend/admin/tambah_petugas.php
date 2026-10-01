<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

if (isset($_POST['simpan'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_petugas = trim($_POST['nama_petugas']);
    $level = 'petugas'; // selalu petugas, tidak ada pilihan level lain

    // Prepared statement supaya aman dari SQL Injection
    $stmt = mysqli_prepare($koneksi, "INSERT INTO petugas (username, password, nama_petugas, level) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $username, $password, $nama_petugas, $level);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Data petugas berhasil ditambahkan!'); window.location='petugas.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah petugas: " . keamanan_error($koneksi) . "');</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Tambah Data Petugas</h1>
    </div>

    <!-- Menggunakan col-md-12 agar full ke sebelah kanan -->
    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Petugas</label>
                    <input type="text" name="nama_petugas" class="form-control" required>
                </div>
                <button type="submit" name="simpan" class="btn btn-primary" style="background-color: #db2777; border-color: #db2777;"><i class="bi bi-save me-1"></i> Simpan Data</button>
                <a href="petugas.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>