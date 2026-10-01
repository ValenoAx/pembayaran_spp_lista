<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Ambil ID dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID tidak valid!'); window.location='spp.php';</script>";
    exit();
}
$id = intval($_GET['id']);

// Ambil data SPP berdasarkan ID
$stmt = mysqli_prepare($koneksi, "SELECT * FROM spp WHERE id_spp = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$hasilData = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasilData);

if (!$data) {
    echo "<script>alert('Data SPP tidak ditemukan!'); window.location='spp.php';</script>";
    exit();
}

// Proses update saat form disubmit
if (isset($_POST['simpan'])) {
    $tahun = (int) $_POST['tahun'];
    $nominal = (int) $_POST['nominal'];

    $stmt = mysqli_prepare($koneksi, "UPDATE spp SET tahun = ?, nominal = ? WHERE id_spp = ?");
    mysqli_stmt_bind_param($stmt, "iii", $tahun, $nominal, $id);
    $query = mysqli_stmt_execute($stmt);
    if ($query) {
        echo "<script>alert('Data SPP berhasil diperbarui!'); window.location='spp.php';</script>";
        exit();
    } else {
        echo "<script>alert('Gagal memperbarui data: " . keamanan_error($koneksi) . "');</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Edit Data SPP</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Tahun Ajaran (masukkan tahun awal saja)</label>
                    <input type="text" name="tahun" id="input-tahun" class="form-control" placeholder="Contoh: 2026" required maxlength="4" pattern="\d{4}" inputmode="numeric" value="<?= htmlspecialchars($data['tahun']); ?>">
                    <div class="form-text">
                        Cukup isi tahun awalnya, tahun akhirnya otomatis.
                        Preview: <strong id="preview-tahun-ajaran">-</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal</label>
                    <input type="number" name="nominal" class="form-control" placeholder="Contoh: 150000" required value="<?= htmlspecialchars($data['nominal']); ?>">
                </div>
                <button type="submit" name="simpan" class="btn btn-primary" style="background-color: #db2777; border-color: #db2777;"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                <a href="spp.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<script>
    // Preview otomatis "2026" -> "2026/2027" saat admin ngetik tahun
    const inputTahun = document.getElementById('input-tahun');
    const previewTahun = document.getElementById('preview-tahun-ajaran');
    function updatePreview() {
        const t = parseInt(inputTahun.value, 10);
        previewTahun.textContent = (inputTahun.value.length === 4 && !isNaN(t)) ? (t + '/' + (t + 1)) : '-';
    }
    inputTahun.addEventListener('input', updatePreview);
    updatePreview(); // langsung tampil preview dari data yang sudah ada
</script>

<?php include '../components/footer.php'; ?>