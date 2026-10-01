<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// PERBAIKAN: kelas.php mengirim parameter "id_kelas" (bukan "id"),
// jadi sebelumnya $_GET['id'] selalu kosong dan data gagal ditemukan.
if (!isset($_GET['id_kelas'])) {
    header('Location: kelas.php');
    exit();
}
$id = mysqli_real_escape_string($koneksi, $_GET['id_kelas']);

$query = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas = '$id'");
$kelas = mysqli_fetch_assoc($query);

if (!$kelas) {
    echo "<script>alert('Data kelas tidak ditemukan.'); window.location='kelas.php';</script>";
    exit();
}

$daftar_jurusan = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];

// Pecah nilai jurusan yang tersimpan (misal "PPLG 1") jadi kode jurusan + nomor rombel,
// supaya bisa dipre-select di form.
$jurusan_kode_lama = trim(preg_replace('/\s*\d+$/', '', $kelas['jurusan']));
$rombel_lama = '';
if (preg_match('/(\d+)\s*$/', $kelas['jurusan'], $m)) {
    $rombel_lama = $m[1];
}

if (isset($_POST['update_kelas'])) {
    $tingkat = mysqli_real_escape_string($koneksi, $_POST['tingkat']);
    $jurusan_kode = mysqli_real_escape_string($koneksi, $_POST['jurusan_kode']);
    $rombel = mysqli_real_escape_string($koneksi, $_POST['rombel']);

    if (!in_array($tingkat, ['10', '11', '12']) || !in_array($_POST['jurusan_kode'], $daftar_jurusan) || !in_array($rombel, ['1', '2', '3'])) {
        echo "<script>alert('Data tidak valid. Pastikan Tingkat, Jurusan, dan Rombel terisi dengan benar.'); history.back();</script>";
        exit();
    }

    $jurusan = $jurusan_kode . ' ' . $rombel;

    $update = mysqli_query($koneksi, "UPDATE kelas SET tingkat='$tingkat', jurusan='$jurusan' WHERE id_kelas='$id'");
    if ($update) {
        echo "<script>alert('Data kelas berhasil diubah!'); window.location='kelas.php';</script>";
    } else {
        echo "<script>alert('Gagal mengubah kelas: " . addslashes(keamanan_error($koneksi)) . "');</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Edit Data Kelas</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-6 p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Tingkat</label>
                <select name="tingkat" class="form-select" required>
                    <?php foreach (['10', '11', '12'] as $t): ?>
                        <option value="<?= $t; ?>" <?= ($kelas['tingkat'] === $t) ? 'selected' : ''; ?>><?= $t; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Jurusan</label>
                <select name="jurusan_kode" class="form-select" required>
                    <?php foreach ($daftar_jurusan as $j): ?>
                        <option value="<?= $j; ?>" <?= ($jurusan_kode_lama === $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Rombel (nomor kelas)</label>
                <select name="rombel" class="form-select" required>
                    <?php foreach (['1', '2', '3'] as $r): ?>
                        <option value="<?= $r; ?>" <?= ($rombel_lama === $r) ? 'selected' : ''; ?>>Rombel <?= $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="update_kelas" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Update Kelas</button>
            <a href="kelas.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</main>

<?php include '../components/footer.php'; ?>