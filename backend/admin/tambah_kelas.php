<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Daftar jurusan yang tersedia (sinkron dengan kelas.php & tambah_siswa.php)
$daftar_jurusan = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];

// Pre-select dari query string kalau datang dari kelas.php?tingkat=..&jurusan=..
$tingkat_default = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$jurusan_default = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';

if (isset($_POST['simpan'])) {
    $tingkat = mysqli_real_escape_string($koneksi, $_POST['tingkat']);
    $jurusan_kode = mysqli_real_escape_string($koneksi, $_POST['jurusan_kode']);
    $rombel = mysqli_real_escape_string($koneksi, $_POST['rombel']);

    if (!in_array($tingkat, ['10', '11', '12']) || !in_array($_POST['jurusan_kode'], $daftar_jurusan) || !in_array($rombel, ['1', '2', '3'])) {
        echo "<script>alert('Data tidak valid. Pastikan Tingkat, Jurusan, dan Rombel terisi dengan benar.'); history.back();</script>";
        exit();
    }

    // Format jurusan disimpan gabung dengan nomor rombel, contoh: "PPLG 1"
    $jurusan = $jurusan_kode . ' ' . $rombel;

    $query = mysqli_query($koneksi, "INSERT INTO kelas (tingkat, jurusan) VALUES ('$tingkat', '$jurusan')");
    if ($query) {
        echo "<script>alert('Data kelas berhasil ditambahkan!'); window.location='kelas.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah data: " . addslashes(keamanan_error($koneksi)) . "');</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Tambah Data Kelas</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Tingkat</label>
                        <select name="tingkat" class="form-select" required>
                            <option value="">Pilih Tingkat</option>
                            <?php foreach (['10', '11', '12'] as $t): ?>
                                <option value="<?= $t; ?>" <?= ($tingkat_default === $t) ? 'selected' : ''; ?>><?= $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jurusan</label>
                        <select name="jurusan_kode" class="form-select" required>
                            <option value="">Pilih Jurusan</option>
                            <?php foreach ($daftar_jurusan as $j): ?>
                                <option value="<?= $j; ?>" <?= ($jurusan_default === $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rombel (nomor kelas)</label>
                        <select name="rombel" class="form-select" required>
                            <option value="">Pilih Rombel</option>
                            <?php foreach (['1', '2', '3'] as $r): ?>
                                <option value="<?= $r; ?>">Rombel <?= $r; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Jurusan + Rombel akan tersimpan sebagai satu kelas, misal "PPLG 1".</div>
                    </div>
                </div>
                <button type="submit" name="simpan" class="btn btn-primary" style="background-color: #db2777; border-color: #db2777;"><i class="bi bi-save me-1"></i> Simpan Data</button>
                <a href="kelas.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>