<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

if (isset($_POST['simpan'])) {
    $tahun = (int) $_POST['tahun'];
    $nominal = (int) $_POST['nominal'];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO spp (tahun, nominal) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $tahun, $nominal);
    $query = mysqli_stmt_execute($stmt);
    if ($query) {
        echo "<script>alert('Data SPP berhasil ditambahkan!'); window.location='spp.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah data: " . keamanan_error($koneksi) . "');</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Tambah Data SPP</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Tahun Ajaran (masukkan tahun awal saja)</label>
                    <input type="text" name="tahun" id="input-tahun" class="form-control" placeholder="Contoh: 2026" required maxlength="4" pattern="\d{4}" inputmode="numeric">
                    <div class="form-text">
                        Cukup isi tahun awalnya, tahun akhirnya otomatis.
                        Preview: <strong id="preview-tahun-ajaran">-</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal</label>
                    <input type="number" name="nominal" class="form-control" placeholder="Contoh: 150000" required>
                </div>
                <button type="submit" name="simpan" class="btn btn-primary" style="background-color: #db2777; border-color: #db2777;"><i class="bi bi-save me-1"></i> Simpan Data</button>
                <a href="spp.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<script>
    // Preview otomatis "2026" -> "2026/2027" saat admin ngetik tahun
    const inputTahun = document.getElementById('input-tahun');
    const previewTahun = document.getElementById('preview-tahun-ajaran');
    inputTahun.addEventListener('input', function () {
        const t = parseInt(this.value, 10);
        previewTahun.textContent = (this.value.length === 4 && !isNaN(t)) ? (t + '/' + (t + 1)) : '-';
    });
</script>

<?php include '../components/footer.php'; ?>