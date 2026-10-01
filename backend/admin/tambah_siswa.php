<?php
require_once __DIR__ . '/keamanan.php';
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

if (isset($_POST['simpan'])) {
    $nisn        = trim($_POST['nisn'] ?? '');
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
    if ($nisn === '' || strlen($nisn) > 20 || !ctype_digit($nisn)
        || $nis === '' || strlen($nis) > 8
        || $nama === '' || strlen($nama) > 100
        || $alamat === '' || strlen($alamat) > 255
        || $no_telp === '' || strlen($no_telp) > 13) {
        echo "<script>alert('Gagal! Periksa kembali NISN (angka saja), NIS, nama, alamat, dan no. telepon.'); window.history.back();</script>";
        exit();
    }

    $tahun_masuk = (int) $tahun_masuk;
    $id_spp      = (int) $id_spp;

    // 1. Cek apakah NISN sudah terdaftar
    $stmt = mysqli_prepare($koneksi, "SELECT nisn FROM siswa WHERE nisn = ?");
    mysqli_stmt_bind_param($stmt, "s", $nisn);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Gagal! NISN sudah terdaftar atas nama siswa lain.'); window.history.back();</script>";
        exit();
    }
    mysqli_stmt_close($stmt);

    // 2. Cari id_kelas dengan EXACT MATCH ke tingkat + jurusan + rombel (misal "PPLG 1")
    $jurusan_lengkap = "$jurusan $rombel";
    $stmt = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE tingkat = ? AND jurusan = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $tingkat, $jurusan_lengkap);
    mysqli_stmt_execute($stmt);
    $hasil_kelas = mysqli_stmt_get_result($stmt);
    $data_kelas = mysqli_fetch_assoc($hasil_kelas);
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
            error_log('tambah_siswa (buat kelas): ' . mysqli_error($koneksi));
            echo "<script>alert('Gagal memproses kelas. Silakan coba lagi.'); window.history.back();</script>";
            exit();
        }
    }

    // 3. Simpan data siswa ke database
    $stmt = mysqli_prepare($koneksi, "INSERT INTO siswa (nisn, nis, nama, id_kelas, tahun_masuk, alamat, no_telp, id_spp)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssiissi", $nisn, $nis, $nama, $id_kelas, $tahun_masuk, $alamat, $no_telp, $id_spp);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        // 4. Catat riwayat kelas untuk tahun ajaran siswa ini masuk ke kelas tersebut
        $tahun_ajaran_ini = tahunAjaranDariMasukDanTingkat($tahun_masuk, $tingkat);
        $stmt = mysqli_prepare($koneksi, "INSERT INTO riwayat_kelas (nisn, id_kelas, tahun_ajaran)
                                          VALUES (?, ?, ?)
                                          ON DUPLICATE KEY UPDATE id_kelas = VALUES(id_kelas)");
        mysqli_stmt_bind_param($stmt, "sii", $nisn, $id_kelas, $tahun_ajaran_ini);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>alert('Data siswa berhasil ditambahkan!'); window.location='siswa.php';</script>";
    } else {
        error_log('tambah_siswa (insert): ' . mysqli_error($koneksi));
        echo "<script>alert('Gagal menambah data. Silakan coba lagi.'); window.history.back();</script>";
    }
}

include '../components/header.php';
include '../components/sidebar.php';

// Kalau datang dari halaman kelas/jurusan tertentu (misal siswa.php?tingkat=11&jurusan=PPLG -> tombol Tambah Siswa),
// nilai ini dipakai buat otomatis pilih di dropdown supaya user tidak perlu pilih manual lagi.
$tingkat_terpilih = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$jurusan_terpilih = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
$rombel_terpilih = isset($_GET['rombel']) ? $_GET['rombel'] : '';

$tahun_sekarang = (int) date('Y');
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">Tambah Data Siswa</h1>
    </div>

    <div class="card border-0 shadow-sm col-md-12">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">NISN</label>
                    <input type="text" name="nisn" class="form-control" required maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="form-label">NIS</label>
                    <input type="text" name="nis" class="form-control" required maxlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Siswa</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>

                <!-- TIGA KOLOM: TINGKAT, JURUSAN, ROMBEL -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tingkat Kelas</label>
                        <select name="tingkat" id="tingkat" class="form-select" required onchange="saranTahunMasuk()">
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="10" <?= ($tingkat_terpilih == '10') ? 'selected' : ''; ?>>10</option>
                            <option value="11" <?= ($tingkat_terpilih == '11') ? 'selected' : ''; ?>>11</option>
                            <option value="12" <?= ($tingkat_terpilih == '12') ? 'selected' : ''; ?>>12</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jurusan</label>
                        <select name="jurusan" class="form-select" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <option value="PPLG" <?= ($jurusan_terpilih == 'PPLG') ? 'selected' : ''; ?>>PPLG (Pengembangan Perangkat Lunak dan Gim)</option>
                            <option value="AKL" <?= ($jurusan_terpilih == 'AKL') ? 'selected' : ''; ?>>AKL (Akuntansi Keuangan Lembaga)</option>
                            <option value="APHP" <?= ($jurusan_terpilih == 'APHP') ? 'selected' : ''; ?>>APHP (Agribisnis Pengolahan Hasil Pertanian)</option>
                            <option value="TSM" <?= ($jurusan_terpilih == 'TSM') ? 'selected' : ''; ?>>TSM (Teknik Sepeda Motor)</option>
                            <option value="TKR" <?= ($jurusan_terpilih == 'TKR') ? 'selected' : ''; ?>>TKR (Teknik Kendaraan Ringan)</option>
                            <option value="APAT" <?= ($jurusan_terpilih == 'APAT') ? 'selected' : ''; ?>>APAT (Agribisnis Perikanan Air Tawar)</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Rombel</label>
                        <select name="rombel" class="form-select" required>
                            <option value="">-- Pilih Rombel --</option>
                            <option value="1" <?= ($rombel_terpilih == '1') ? 'selected' : ''; ?>>Rombel 1</option>
                            <option value="2" <?= ($rombel_terpilih == '2') ? 'selected' : ''; ?>>Rombel 2</option>
                            <option value="3" <?= ($rombel_terpilih == '3') ? 'selected' : ''; ?>>Rombel 3</option>
                        </select>
                    </div>
                </div>

                <!-- TAHUN MASUK (ANGKATAN) -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tahun Masuk (Angkatan)</label>
                        <select name="tahun_masuk" id="tahun_masuk" class="form-select" required>
                            <option value="">-- Pilih Tahun Masuk --</option>
                            <?php for ($y = $tahun_sekarang; $y >= $tahun_sekarang - 5; $y--): ?>
                                <option value="<?= $y; ?>"><?= $y; ?>/<?= $y + 1; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text">Tahun ajaran saat siswa pertama kali masuk (kelas 10). SPP siswa ini nanti hanya bisa dibayar dari tahun ini sampai 2 tahun ajaran setelahnya (sampai lulus).</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telp" class="form-control" required maxlength="13">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal / Tahun Ajaran SPP</label>
                    <select name="id_spp" class="form-select" required>
                        <option value="">-- Pilih SPP --</option>
                        <?php
                        $spp = mysqli_query($koneksi, "SELECT * FROM spp");
                        while ($s = mysqli_fetch_assoc($spp)) {
                            echo "<option value='{$s['id_spp']}'>Tahun Ajaran: " . formatTA($s['tahun']) . " - Rp " . number_format($s['nominal'], 0, ',', '.') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="simpan" class="btn btn-primary" style="background-color: #d63384; border: none;"><i class="bi bi-save me-1"></i> Simpan Data</button>
                <a href="siswa.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</main>

<script>
    // Bantu isi otomatis tahun masuk berdasarkan tingkat yang dipilih (masih bisa diubah manual oleh admin)
    function saranTahunMasuk() {
        var tingkat = document.getElementById('tingkat').value;
        var tahunMasukSelect = document.getElementById('tahun_masuk');
        if (!tingkat || tahunMasukSelect.value !== '') return; // jangan timpa kalau admin sudah pilih manual

        var tahunSekarang = <?= $tahun_sekarang; ?>;
        var selisih = {
            '10': 0,
            '11': 1,
            '12': 2
        };
        var saran = tahunSekarang - (selisih[tingkat] || 0);

        for (var i = 0; i < tahunMasukSelect.options.length; i++) {
            if (tahunMasukSelect.options[i].value == saran) {
                tahunMasukSelect.selectedIndex = i;
                break;
            }
        }
    }
</script>

<?php include '../components/footer.php'; ?>