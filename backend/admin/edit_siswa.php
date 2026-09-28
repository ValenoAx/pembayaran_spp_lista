<?php
session_start();
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Tahun ajaran = tahun MULAI (misal 2026 artinya tahun ajaran 2026/2027)
function tahunAjaranDariMasukDanTingkat($tahun_masuk, $tingkat)
{
    // Tingkat 10 -> tahun ajaran = tahun_masuk
    // Tingkat 11 -> tahun ajaran = tahun_masuk + 1
    // Tingkat 12 -> tahun ajaran = tahun_masuk + 2
    return (int) $tahun_masuk + ((int) $tingkat - 10);
}

const JURUSAN_VALID = ['PPLG', 'AKL', 'APHP', 'TSM', 'TKR', 'APAT'];

// Ambil NISN dari URL (pakai prepared statement)
$nisn = $_GET['nisn'] ?? '';
$stmt = mysqli_prepare($koneksi, "SELECT siswa.*, kelas.tingkat, kelas.jurusan AS nama_jurusan
                                  FROM siswa JOIN kelas ON siswa.id_kelas = kelas.id_kelas
                                  WHERE siswa.nisn = ?");
mysqli_stmt_bind_param($stmt, "s", $nisn);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$siswa) {
    echo "<script>alert('Data siswa tidak ditemukan!'); window.location='siswa.php';</script>";
    exit();
}

if (isset($_POST['update'])) {
    $nis         = trim($_POST['nis'] ?? '');
    $nama        = trim($_POST['nama'] ?? '');
    $tingkat     = $_POST['tingkat'] ?? '';
    $jurusan     = $_POST['jurusan'] ?? '';
    $rombel      = $_POST['rombel'] ?? '';
    $tahun_masuk = $_POST['tahun_masuk'] ?? '';
    $alamat      = trim($_POST['alamat'] ?? '');
    $no_telp     = trim($_POST['no_telp'] ?? '');
    $id_spp      = $_POST['id_spp'] ?? '';

    if (empty($tingkat) || empty($jurusan) || empty($rombel)) {
        echo "<script>alert('Gagal! Tingkat, Jurusan, dan Rombel harus dipilih.'); window.history.back();</script>";
        exit();
    }

    if (empty($tahun_masuk)) {
        echo "<script>alert('Gagal! Tahun Masuk (angkatan) harus dipilih.'); window.history.back();</script>";
        exit();
    }

    // Validasi ketat: nilai dropdown harus salah satu dari pilihan yang sah
    if (!in_array($tingkat, ['10', '11', '12'], true)
        || !in_array($jurusan, JURUSAN_VALID, true)
        || !in_array($rombel, ['1', '2', '3'], true)
        || !ctype_digit((string) $tahun_masuk)
        || !ctype_digit((string) $id_spp)) {
        echo "<script>alert('Gagal! Data yang dikirim tidak valid.'); window.history.back();</script>";
        exit();
    }

    // Validasi panjang & isi field teks
    if ($nis === '' || strlen($nis) > 8
        || $nama === '' || strlen($nama) > 100
        || $alamat === '' || strlen($alamat) > 255
        || $no_telp === '' || strlen($no_telp) > 13) {
        echo "<script>alert('Gagal! Periksa kembali NIS, nama, alamat, dan no. telepon.'); window.history.back();</script>";
        exit();
    }

    $tahun_masuk = (int) $tahun_masuk;
    $id_spp      = (int) $id_spp;

    // Cari id_kelas dengan EXACT MATCH ke tingkat + jurusan + rombel (misal "PPLG 1")
    $jurusan_lengkap = "$jurusan $rombel";
    $stmt = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE tingkat = ? AND jurusan = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $tingkat, $jurusan_lengkap);
    mysqli_stmt_execute($stmt);
    $data_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($data_kelas) {
        $id_kelas = (int) $data_kelas['id_kelas'];
    } else {
        // Jika belum ada di database, otomatis dibuatkan oleh sistem supaya tidak error
        $stmt = mysqli_prepare($koneksi, "INSERT INTO kelas (tingkat, jurusan) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $tingkat, $jurusan_lengkap);
        if (mysqli_stmt_execute($stmt)) {
            $id_kelas = mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmt);
        } else {
            error_log('edit_siswa (buat kelas): ' . mysqli_error($koneksi));
            echo "<script>alert('Gagal memproses kelas. Silakan coba lagi.'); window.history.back();</script>";
            exit();
        }
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE siswa
                                      SET nis = ?, nama = ?, id_kelas = ?, tahun_masuk = ?, alamat = ?, no_telp = ?, id_spp = ?
                                      WHERE nisn = ?");
    mysqli_stmt_bind_param($stmt, "ssiissis", $nis, $nama, $id_kelas, $tahun_masuk, $alamat, $no_telp, $id_spp, $nisn);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        // Catat/perbarui riwayat kelas untuk tahun ajaran hasil kombinasi tingkat + tahun_masuk terbaru
        $tahun_ajaran_ini = tahunAjaranDariMasukDanTingkat($tahun_masuk, $tingkat);
        $stmt = mysqli_prepare($koneksi, "INSERT INTO riwayat_kelas (nisn, id_kelas, tahun_ajaran)
                                          VALUES (?, ?, ?)
                                          ON DUPLICATE KEY UPDATE id_kelas = VALUES(id_kelas)");
        mysqli_stmt_bind_param($stmt, "sii", $nisn, $id_kelas, $tahun_ajaran_ini);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>alert('Data siswa berhasil diubah!'); window.location='siswa.php';</script>";
    } else {
        error_log('edit_siswa (update): ' . mysqli_error($koneksi));
        echo "<script>alert('Gagal mengubah data. Silakan coba lagi.'); window.history.back();</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';

// Ambil rombel yang sedang aktif dari data siswa saat ini (misal dari "PPLG 1" -> ambil "1"),
// supaya dropdown Rombel otomatis kepilih sesuai kondisi siswa sekarang.
$rombel_sekarang = '';
if (preg_match('/(\d+)\s*$/', $siswa['nama_jurusan'], $m)) {
    $rombel_sekarang = $m[1];
}

$tahun_sekarang = (int) date('Y');
// Kalau tahun_masuk siswa di luar rentang dropdown default (misal angkatan lama), tetap dimasukkan supaya tidak hilang
$tahun_masuk_siswa = (int) $siswa['tahun_masuk'];
$daftar_tahun_masuk = range($tahun_sekarang, $tahun_sekarang - 5);
if (!in_array($tahun_masuk_siswa, $daftar_tahun_masuk)) {
    $daftar_tahun_masuk[] = $tahun_masuk_siswa;
    rsort($daftar_tahun_masuk);
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Edit Data Siswa</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">NISN (Tidak dapat diubah)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($siswa['nisn'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIS</label>
                    <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Siswa</label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <!-- Pilihan Tingkat, Jurusan, dan Rombel yang otomatis terpilih sesuai data asli -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tingkat</label>
                        <select name="tingkat" class="form-select" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="10" <?= (trim($siswa['tingkat']) == '10') ? 'selected' : ''; ?>>10</option>
                            <option value="11" <?= (trim($siswa['tingkat']) == '11') ? 'selected' : ''; ?>>11</option>
                            <option value="12" <?= (trim($siswa['tingkat']) == '12') ? 'selected' : ''; ?>>12</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jurusan</label>
                        <select name="jurusan" class="form-select" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <option value="PPLG" <?= (stripos($siswa['nama_jurusan'], 'PPLG') !== false) ? 'selected' : ''; ?>>PPLG</option>
                            <option value="AKL" <?= (stripos($siswa['nama_jurusan'], 'AKL') !== false) ? 'selected' : ''; ?>>AKL</option>
                            <option value="APHP" <?= (stripos($siswa['nama_jurusan'], 'APHP') !== false) ? 'selected' : ''; ?>>APHP</option>
                            <option value="TSM" <?= (stripos($siswa['nama_jurusan'], 'TSM') !== false) ? 'selected' : ''; ?>>TSM</option>
                            <option value="TKR" <?= (stripos($siswa['nama_jurusan'], 'TKR') !== false) ? 'selected' : ''; ?>>TKR</option>
                            <option value="APAT" <?= (stripos($siswa['nama_jurusan'], 'APAT') !== false) ? 'selected' : ''; ?>>APAT</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Rombel</label>
                        <select name="rombel" class="form-select" required>
                            <option value="">-- Pilih Rombel --</option>
                            <option value="1" <?= ($rombel_sekarang == '1') ? 'selected' : ''; ?>>Rombel 1</option>
                            <option value="2" <?= ($rombel_sekarang == '2') ? 'selected' : ''; ?>>Rombel 2</option>
                            <option value="3" <?= ($rombel_sekarang == '3') ? 'selected' : ''; ?>>Rombel 3</option>
                        </select>
                    </div>
                </div>

                <!-- TAHUN MASUK (ANGKATAN), terisi sesuai data siswa saat ini -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tahun Masuk (Angkatan)</label>
                        <select name="tahun_masuk" class="form-select" required>
                            <option value="">-- Pilih Tahun Masuk --</option>
                            <?php foreach ($daftar_tahun_masuk as $y): ?>
                                <option value="<?= $y; ?>" <?= ($y == $tahun_masuk_siswa) ? 'selected' : ''; ?>><?= $y; ?>/<?= $y + 1; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Lulus (estimasi): <?= $tahun_masuk_siswa + 3; ?>. SPP siswa ini hanya bisa dibayar untuk tahun ajaran <?= $tahun_masuk_siswa; ?>/<?= $tahun_masuk_siswa + 1; ?> s.d. <?= $tahun_masuk_siswa + 2; ?>/<?= $tahun_masuk_siswa + 3; ?>.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($siswa['alamat'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($siswa['no_telp'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="13">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal / Tahun Ajaran SPP</label>
                    <select name="id_spp" class="form-select" required>
                        <option value="">-- Pilih SPP --</option>
                        <?php
                        $spp = mysqli_query($koneksi, "SELECT * FROM spp");
                        while ($s = mysqli_fetch_assoc($spp)) {
                            $selected = ($s['id_spp'] == $siswa['id_spp']) ? 'selected' : '';
                            echo "<option value='{$s['id_spp']}' $selected>Tahun Ajaran: " . formatTA($s['tahun']) . " - Rp " . number_format($s['nominal'], 0, ',', '.') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="update" value="1" class="btn btn-primary" style="background-color: #d63384; border: none;"><i class="bi bi-save me-1"></i> Update Data</button>
                <a href="siswa.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>