<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$jenis   = $_GET['jenis'] ?? 'siswa';
$tingkat = $_GET['tingkat'] ?? '';
$tahun   = $_GET['tahun'] ?? '';

// "semua" (dari dropdown "Semua Kelas" / "Semua Tahun") diperlakukan sama
// seperti tidak ada filter sama sekali.
if ($tingkat === 'semua') $tingkat = '';
if ($tahun === 'semua') $tahun = '';

$judul = '';
$data = [];
$total_bayar_keseluruhan = 0;

// Palet warna buat kartu statistik yang jumlahnya dinamis (misal kartu per jurusan)
$palet_warna = [
    ['warna' => '#db2777', 'bg' => '#ffe6f0'],
    ['warna' => '#8b5cf6', 'bg' => '#f3e8ff'],
    ['warna' => '#0ea5e9', 'bg' => '#e0f2fe'],
    ['warna' => '#f59e0b', 'bg' => '#fef3c7'],
    ['warna' => '#10b981', 'bg' => '#d1fae5'],
    ['warna' => '#ef4444', 'bg' => '#fee2e2'],
    ['warna' => '#6366f1', 'bg' => '#e0e7ff'],
    ['warna' => '#14b8a6', 'bg' => '#ccfbf1'],
];

if ($jenis === 'siswa') {
    $judul = 'Laporan Data Siswa';
    if ($tingkat !== '') $judul .= ' - Kelas ' . $tingkat;

    $sql = "SELECT siswa.nisn, siswa.nis, siswa.nama, siswa.alamat, siswa.no_telp,
                   kelas.tingkat, kelas.jurusan
            FROM siswa
            JOIN kelas ON siswa.id_kelas = kelas.id_kelas";
    if ($tingkat !== '') {
        $tingkat_aman = mysqli_real_escape_string($koneksi, $tingkat);
        $sql .= " WHERE kelas.tingkat = '$tingkat_aman'";
    }
    $sql .= " ORDER BY kelas.tingkat ASC, siswa.nama ASC";

    $q = mysqli_query($koneksi, $sql);
    while ($row = mysqli_fetch_assoc($q)) {
        $data[] = $row;
    }

    if ($tingkat === '') {
        // Belum difilter ke kelas tertentu -> kartu breakdown per tingkat (10/11/12) relevan
        $per_tingkat = ['10' => 0, '11' => 0, '12' => 0];
        foreach ($data as $s) {
            if (isset($per_tingkat[$s['tingkat']])) $per_tingkat[$s['tingkat']]++;
        }
    } else {
        // Sudah difilter ke satu kelas -> breakdown per tingkat percuma (pasti 0/0/total).
        // Ganti jadi breakdown per jurusan induk di kelas itu (rombel disatukan).
        // Contoh: "PPLG 1", "PPLG 2", "PPLG 3" -> semua masuk grup "PPLG".
        $per_jurusan = [];
        foreach ($data as $s) {
            // Buang angka rombel di paling belakang (mis. "PPLG 2" -> "PPLG").
            // Kalau field jurusan memang tidak diikuti angka, nama aslinya tetap dipakai.
            $j = trim(preg_replace('/\s+\d+$/', '', $s['jurusan']));
            if (!isset($per_jurusan[$j])) $per_jurusan[$j] = 0;
            $per_jurusan[$j]++;
        }
        ksort($per_jurusan);
    }

} else {
    $judul = 'Laporan Pembayaran SPP';
    if ($tingkat !== '') $judul .= ' - Kelas ' . $tingkat;
    if ($tahun !== '') $judul .= ' - Tahun Ajaran ' . formatTA($tahun);

    $sql = "SELECT pembayaran.tgl_bayar, pembayaran.bulan_dibayar, pembayaran.tahun_dibayar,
                   pembayaran.jumlah_bayar, siswa.nisn, siswa.nama,
                   kelas.tingkat, kelas.jurusan, petugas.nama_petugas
            FROM pembayaran
            JOIN siswa ON pembayaran.nisn = siswa.nisn
            JOIN kelas ON siswa.id_kelas = kelas.id_kelas
            LEFT JOIN petugas ON pembayaran.id_petugas = petugas.id_petugas
            WHERE 1=1";
    if ($tingkat !== '') {
        $tingkat_aman = mysqli_real_escape_string($koneksi, $tingkat);
        $sql .= " AND kelas.tingkat = '$tingkat_aman'";
    }
    if ($tahun !== '') {
        $tahun_aman = mysqli_real_escape_string($koneksi, $tahun);
        $sql .= " AND pembayaran.tahun_dibayar = '$tahun_aman'";
    }
    $sql .= " ORDER BY pembayaran.tgl_bayar ASC";

    $q = mysqli_query($koneksi, $sql);
    while ($row = mysqli_fetch_assoc($q)) {
        $data[] = $row;
        $total_bayar_keseluruhan += (int) $row['jumlah_bayar'];
    }
    $rata_rata = count($data) > 0 ? $total_bayar_keseluruhan / count($data) : 0;
}

/* ============================================================
   PAGINASI TABEL

   Ini CUMA buat tampilan tabelnya. Variabel $data yang asli (lengkap,
   semua baris) tetap dipakai apa adanya buat:
   - kartu statistik di atas (Total Siswa/Transaksi, Total Dana Masuk, dst)
   - tombol Download PDF (lewat $query_pdf, tidak ikut bawa parameter
     halaman, jadi PDF-nya tetap isi semua data)

   $data_halaman_ini isinya cuma potongan 10 baris sesuai halaman yang
   lagi dibuka, dipakai gantinya $data pas nge-loop <tbody> tabel.
   ============================================================ */
$per_halaman = 50;
$halaman = isset($_GET['halaman']) ? max(1, (int) $_GET['halaman']) : 1;
$total_data = count($data);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
if ($halaman > $total_halaman) {
    $halaman = $total_halaman;
}
$offset = ($halaman - 1) * $per_halaman;
$data_halaman_ini = array_slice($data, $offset, $per_halaman);

// Bikin link ke nomor halaman tertentu, sambil bawa filter (jenis/tingkat/tahun) yang sama
function linkHalaman($h)
{
    global $jenis, $tingkat, $tahun;
    return 'hasil_laporan.php?' . http_build_query([
        'jenis'   => $jenis,
        'tingkat' => $tingkat,
        'tahun'   => $tahun,
        'halaman' => $h,
    ]);
}

// Query string buat tombol "Download PDF" (bawa filter yang sama, TANPA halaman -> selalu semua data)
$query_pdf = http_build_query(['jenis' => $jenis, 'tingkat' => $tingkat, 'tahun' => $tahun]);

include '../components/header.php';
include '../components/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div>
            <h1 class="h3 fw-bold" style="color: #db2777;"><?= htmlspecialchars($judul); ?></h1>
            <p class="text-muted mb-0">Hasil laporan berdasarkan filter yang dipilih</p>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Ubah Filter
            </a>
            <a href="cetak_laporan.php?<?= $query_pdf; ?>" target="_blank" class="btn text-white" style="background-color:#db2777;">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
        </div>
    </div>

    <?php if ($jenis === 'siswa'): ?>
    <!-- KARTU STATISTIK: LAPORAN SISWA -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#db2777 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#db2777;">Total Siswa</div>
                        <div class="h2 fw-bold mb-0"><?= count($data); ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#db2777; background-color:#ffe6f0;"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>

        <?php if ($tingkat === ''): ?>
            <!-- Belum difilter ke kelas tertentu -> tampilkan breakdown per tingkat -->
            <?php
            $label_tingkat = ['10' => 'Kelas 10', '11' => 'Kelas 11', '12' => 'Kelas 12'];
            $i = 1; // mulai dari warna ke-2 di palet (indeks 0 sudah dipakai kartu Total)
            foreach ($per_tingkat as $tk => $jml):
                $warna = $palet_warna[$i % count($palet_warna)];
                $i++;
            ?>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:<?= $warna['warna']; ?> !important;">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1" style="color:<?= $warna['warna']; ?>;"><?= $label_tingkat[$tk]; ?></div>
                            <div class="h2 fw-bold mb-0"><?= $jml; ?></div>
                        </div>
                        <div class="fs-1 p-3 rounded-3" style="color:<?= $warna['warna']; ?>; background-color:<?= $warna['bg']; ?>;"><i class="bi bi-mortarboard-fill"></i></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <!-- Sudah difilter ke satu kelas -> tampilkan breakdown per jurusan (bukan per tingkat, karena pasti 0) -->
            <?php
            $i = 1;
            foreach ($per_jurusan as $jurusan => $jml):
                $warna = $palet_warna[$i % count($palet_warna)];
                $i++;
            ?>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:<?= $warna['warna']; ?> !important;">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1" style="color:<?= $warna['warna']; ?>;"><?= htmlspecialchars($jurusan); ?></div>
                            <div class="h2 fw-bold mb-0"><?= $jml; ?></div>
                        </div>
                        <div class="fs-1 p-3 rounded-3" style="color:<?= $warna['warna']; ?>; background-color:<?= $warna['bg']; ?>;"><i class="bi bi-mortarboard-fill"></i></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- KARTU STATISTIK: LAPORAN PEMBAYARAN -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#db2777 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#db2777;">Total Transaksi</div>
                        <div class="h2 fw-bold mb-0"><?= count($data); ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#db2777; background-color:#ffe6f0;"><i class="bi bi-receipt"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1 text-success">Total Dana Masuk</div>
                        <div class="h4 fw-bold mb-0">Rp <?= number_format($total_bayar_keseluruhan, 0, ',', '.'); ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3 text-success bg-success bg-opacity-10"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4" style="border-left-color:#0ea5e9 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color:#0ea5e9;">Rata-rata / Transaksi</div>
                        <div class="h4 fw-bold mb-0">Rp <?= number_format($rata_rata, 0, ',', '.'); ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color:#0ea5e9; background-color:#e0f2fe;"><i class="bi bi-graph-up"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABEL DATA -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (count($data) === 0): ?>
                <p class="text-muted text-center my-4">Tidak ada data yang cocok dengan filter ini.</p>

            <?php elseif ($jenis === 'siswa'): ?>
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr style="background-color:#ffe6f0;">
                            <th>No</th><th>NISN</th><th>NIS</th><th>Nama</th>
                            <th>Kelas</th><th>Alamat</th><th>No. Telp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_halaman_ini as $i => $s): ?>
                        <tr>
                            <td><?= $offset + $i + 1; ?></td>
                            <td><?= htmlspecialchars($s['nisn']); ?></td>
                            <td><?= htmlspecialchars($s['nis']); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($s['nama']); ?></td>
                            <td><span class="badge" style="background-color:#fbcfe8; color:#9d174d;"><?= htmlspecialchars($s['tingkat'] . ' ' . $s['jurusan']); ?></span></td>
                            <td><?= htmlspecialchars($s['alamat']); ?></td>
                            <td><?= htmlspecialchars($s['no_telp']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr style="background-color:#ffe6f0;">
                            <th>No</th><th>Tgl Bayar</th><th>Nama Siswa</th><th>Kelas</th>
                            <th>Bulan/Tahun SPP</th><th>Jumlah Bayar</th><th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_halaman_ini as $i => $p): ?>
                        <tr>
                            <td><?= $offset + $i + 1; ?></td>
                            <td><?= date('d-m-Y', strtotime($p['tgl_bayar'])); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['nama']); ?></td>
                            <td><span class="badge" style="background-color:#fbcfe8; color:#9d174d;"><?= htmlspecialchars($p['tingkat'] . ' ' . $p['jurusan']); ?></span></td>
                            <td><?= htmlspecialchars($p['bulan_dibayar'] . ' ' . $p['tahun_dibayar']); ?></td>
                            <td class="fw-semibold text-success">Rp <?= number_format($p['jumlah_bayar'], 0, ',', '.'); ?></td>
                            <td><?= htmlspecialchars($p['nama_petugas'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <?php if (count($data) > 0 && $total_halaman > 1): ?>
                <!-- NOMOR HALAMAN -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <div class="small text-muted">
                        Menampilkan <?= $offset + 1; ?>–<?= min($offset + $per_halaman, $total_data); ?> dari <?= $total_data; ?> data
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($halaman <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?= htmlspecialchars(linkHalaman(max(1, $halaman - 1))); ?>">&laquo;</a>
                            </li>
                            <?php for ($h = 1; $h <= $total_halaman; $h++): ?>
                                <li class="page-item <?= ($h === $halaman) ? 'active' : ''; ?>">
                                    <a class="page-link <?= ($h === $halaman) ? 'text-white' : ''; ?>"
                                       style="<?= ($h === $halaman) ? 'background-color:#db2777; border-color:#db2777;' : 'color:#db2777;'; ?>"
                                       href="<?= htmlspecialchars(linkHalaman($h)); ?>"><?= $h; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($halaman >= $total_halaman) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?= htmlspecialchars(linkHalaman(min($total_halaman, $halaman + 1))); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>