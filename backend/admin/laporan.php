<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Ambil daftar tahun dari tabel spp, buat isi dropdown filter tahun
$q_tahun = mysqli_query($koneksi, "SELECT DISTINCT tahun FROM spp ORDER BY tahun DESC");
$daftar_tahun = [];
while ($r = mysqli_fetch_assoc($q_tahun)) {
    $daftar_tahun[] = $r['tahun'];
}

// Ringkasan cepat buat kartu statistik di atas halaman
$total_siswa = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM siswa"))['total'];
$total_transaksi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pembayaran"))['total'];
$total_dana = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) AS total FROM pembayaran"))['total'] ?? 0;
$tahun_ini = date('Y');
$transaksi_tahun_ini = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pembayaran WHERE tahun_dibayar = '$tahun_ini'"))['total'];

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

    <!-- BANNER JUDUL -->
    <div class="p-4 mb-4 rounded-4 text-white shadow-sm" style="background: linear-gradient(135deg, #db2777, #f472b6);">
        <div class="d-flex align-items-center">
            <div class="fs-1 me-3"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div>
                <h1 class="h3 fw-bold mb-1">Generate Laporan</h1>
                <p class="mb-0 opacity-90">Lihat ringkasan data, lalu unduh laporan dalam bentuk PDF kapan saja.</p>
            </div>
        </div>
    </div>

    <!-- KARTU RINGKASAN CEPAT -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#db2777 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#db2777;">Total Siswa</div>
                        <div class="h2 fw-bold mb-0"><?= $total_siswa; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#db2777; background-color:#ffe6f0;"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#8b5cf6 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#8b5cf6;">Total Transaksi</div>
                        <div class="h2 fw-bold mb-0"><?= $total_transaksi; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#8b5cf6; background-color:#f3e8ff;"><i class="bi bi-receipt"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#0ea5e9 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#0ea5e9;">Transaksi <?= $tahun_ini; ?></div>
                        <div class="h2 fw-bold mb-0"><?= $transaksi_tahun_ini; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#0ea5e9; background-color:#e0f2fe;"><i class="bi bi-calendar-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1 text-success">Total Dana Masuk</div>
                        <div class="h5 fw-bold mb-0">Rp <?= number_format($total_dana, 0, ',', '.'); ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3 text-success bg-success bg-opacity-10"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-sliders me-2"></i>Pilih Jenis Laporan</h5>

    <div class="row g-4">
        <!-- KARTU 1: LAPORAN DATA SISWA -->
        <div class="col-md-6">
            <div class="card border-0 shadow h-100 laporan-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="fs-2 p-3 rounded-circle me-3" style="color:#db2777; background-color:#ffe6f0;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color:#db2777;">Laporan Data Siswa</h5>
                            <span class="text-muted small">Daftar lengkap siswa & kelasnya</span>
                        </div>
                    </div>
                    <hr>
                    <form action="hasil_laporan.php" method="GET" id="form-siswa">
                        <input type="hidden" name="jenis" value="siswa">

                        <label class="form-label small fw-semibold">Filter Kelas</label>
                        <select name="tingkat" id="siswa-tingkat" class="form-select mb-3" required>
                            <option value="" disabled selected>-- Pilih Kelas --</option>
                            <option value="semua">Semua Kelas</option>
                            <option value="10">Kelas 10</option>
                            <option value="11">Kelas 11</option>
                            <option value="12">Kelas 12</option>
                        </select>

                        <button type="submit" id="siswa-submit" class="btn text-white w-100 py-2 fw-semibold" style="background-color: #db2777;" disabled>
                            <i class="bi bi-eye me-1"></i> Lihat Laporan Siswa
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- KARTU 2: LAPORAN PEMBAYARAN -->
        <div class="col-md-6">
            <div class="card border-0 shadow h-100 laporan-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="fs-2 p-3 rounded-circle me-3" style="color:#0ea5e9; background-color:#e0f2fe;">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color:#0ea5e9;">Laporan Pembayaran SPP</h5>
                            <span class="text-muted small">Rekap transaksi pembayaran siswa</span>
                        </div>
                    </div>
                    <hr>
                    <form action="hasil_laporan.php" method="GET" id="form-pembayaran">
                        <input type="hidden" name="jenis" value="pembayaran">

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Filter Kelas</label>
                                <select name="tingkat" id="bayar-tingkat" class="form-select" required>
                                    <option value="" disabled selected>-- Pilih Kelas --</option>
                                    <option value="semua">Semua Kelas</option>
                                    <option value="10">Kelas 10</option>
                                    <option value="11">Kelas 11</option>
                                    <option value="12">Kelas 12</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Filter Tahun</label>
                                <select name="tahun" id="bayar-tahun" class="form-select" required disabled>
                                    <option value="" disabled selected>-- Pilih Kelas dulu --</option>
                                    <option value="semua">Semua Tahun</option>
                                    <?php foreach ($daftar_tahun as $th): ?>
                                        <option value="<?= htmlspecialchars($th); ?>"><?= htmlspecialchars(formatTA($th)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" id="bayar-submit" class="btn text-white w-100 py-2 fw-semibold" style="background-color: #0ea5e9;" disabled>
                            <i class="bi bi-eye me-1"></i> Lihat Laporan Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .laporan-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .laporan-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08) !important;
    }

    .form-select:disabled {
        background-color: #f1f1f1;
        cursor: not-allowed;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<script>
    // ===== FORM LAPORAN SISWA =====
    // Cuma 1 filter, jadi tombol Terapkan langsung aktif begitu Kelas dipilih.
    const siswaTingkat = document.getElementById('siswa-tingkat');
    const siswaSubmit = document.getElementById('siswa-submit');

    siswaTingkat.addEventListener('change', function() {
        siswaSubmit.disabled = (this.value === '');
    });

    // ===== FORM LAPORAN PEMBAYARAN =====
    // Filter Tahun terkunci sampai Filter Kelas diisi.
    // Tombol Terapkan terkunci sampai kedua filter terisi.
    const bayarTingkat = document.getElementById('bayar-tingkat');
    const bayarTahun = document.getElementById('bayar-tahun');
    const bayarSubmit = document.getElementById('bayar-submit');
    const placeholderTahunAwal = bayarTahun.querySelector('option[value=""]');

    function cekTombolPembayaran() {
        bayarSubmit.disabled = (bayarTingkat.value === '' || bayarTahun.value === '');
    }

    bayarTingkat.addEventListener('change', function() {
        if (this.value !== '') {
            bayarTahun.disabled = false;
            if (placeholderTahunAwal) {
                placeholderTahunAwal.textContent = '-- Pilih Tahun --';
            }
        } else {
            bayarTahun.disabled = true;
            bayarTahun.value = '';
        }
        cekTombolPembayaran();
    });

    bayarTahun.addEventListener('change', cekTombolPembayaran);
</script>

<?php include '../components/footer.php'; ?>