<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

/* ============================================================
   DETAIL PEMBAYARAN
   Mendukung 2 mode:
   - 1 id_pembayaran   -> tampilan detail satu transaksi (dari link lama)
   - beberapa id (ids) -> tampilan gabungan (dari entri pembayaran multi-bulan)
   Keduanya dibaca lewat parameter "ids", boleh berisi satu angka atau
   beberapa angka dipisah koma.
   ============================================================ */

$ids_mentah = '';
if (isset($_GET['ids'])) {
    $ids_mentah = $_GET['ids'];
} elseif (isset($_GET['id_pembayaran'])) {
    $ids_mentah = $_GET['id_pembayaran'];
}

$daftar_id = array_filter(array_map('intval', explode(',', $ids_mentah)));
if (count($daftar_id) === 0) {
    header("Location: pembayaran.php");
    exit();
}

$baru = isset($_GET['baru']);
$ids_sql = implode(',', $daftar_id);

$q = mysqli_query($koneksi, "
    SELECT pembayaran.*, siswa.nama, siswa.nis, siswa.alamat, siswa.no_telp,
           kelas.tingkat, kelas.jurusan, spp.tahun AS tahun_spp, spp.nominal,
           petugas.nama_petugas
    FROM pembayaran
    JOIN siswa ON pembayaran.nisn = siswa.nisn
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    JOIN spp ON pembayaran.id_spp = spp.id_spp
    LEFT JOIN petugas ON pembayaran.id_petugas = petugas.id_petugas
    WHERE pembayaran.id_pembayaran IN ($ids_sql)
    ORDER BY pembayaran.tahun_dibayar ASC, pembayaran.id_pembayaran ASC
");

$daftar_transaksi = [];
$total_bayar = 0;
while ($row = mysqli_fetch_assoc($q)) {
    $daftar_transaksi[] = $row;
    $total_bayar += (int) $row['jumlah_bayar'];
}

if (count($daftar_transaksi) === 0) {
    echo "<script>alert('Data pembayaran tidak ditemukan.'); window.location='pembayaran.php';</script>";
    exit();
}

$d = $daftar_transaksi[0]; // data siswa & kelas sama untuk semua baris, ambil dari yang pertama
$jurusan_kode = trim(preg_replace('/\s*\d+$/', '', $d['jurusan']));
$rombel_no = '';
if (preg_match('/(\d+)\s*$/', $d['jurusan'], $m)) {
    $rombel_no = $m[1];
}

// Rekap bulan yang sudah lunas di tahun SPP ini (pakai tahun dari transaksi pertama)
$nisn_esc = mysqli_real_escape_string($koneksi, $d['nisn']);
$thn_esc  = mysqli_real_escape_string($koneksi, $d['tahun_dibayar']);
$q_rekap = mysqli_query($koneksi, "SELECT bulan_dibayar FROM pembayaran
                                   WHERE nisn = '$nisn_esc' AND tahun_dibayar = '$thn_esc'");
$bulan_lunas = [];
while ($r = mysqli_fetch_assoc($q_rekap)) {
    $bulan_lunas[] = $r['bulan_dibayar'];
}

$bulan_di_transaksi_ini = array_map(function ($t) {
    return $t['bulan_dibayar'];
}, $daftar_transaksi);
$satu_transaksi = (count($daftar_transaksi) === 1);

include '../components/header.php';
include '../components/sidebar.php';
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold mb-1" style="color: #db2777;">Detail Pembayaran</h1>
        <p class="text-muted mb-0 small">
            <?= $satu_transaksi
                ? 'Bukti transaksi No. #' . str_pad($d['id_pembayaran'], 5, '0', STR_PAD_LEFT)
                : count($daftar_transaksi) . ' bulan dibayar dalam satu transaksi'; ?>
        </p>
    </div>

    <?php if ($baru): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            Pembayaran berhasil disimpan<?= $satu_transaksi ? '' : ' untuk ' . count($daftar_transaksi) . ' bulan sekaligus'; ?>.
            Silakan cetak kuitansinya untuk siswa.
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3" style="color:#9d174d;">Rincian Transaksi</h6>

                    <?php if ($satu_transaksi): ?>
                        <table class="table table-sm align-middle mb-0">
                            <tr>
                                <td class="text-muted" style="width:45%;">No. Transaksi</td>
                                <td><strong>#<?= str_pad($d['id_pembayaran'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal Bayar</td>
                                <td><?= date('d F Y', strtotime($d['tgl_bayar'])); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Bulan Dibayar</td>
                                <td><strong><?= htmlspecialchars($d['bulan_dibayar'] . ' ' . $d['tahun_dibayar']); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nominal</td>
                                <td class="fw-bold" style="color:#db2777;">Rp <?= number_format($d['jumlah_bayar'], 0, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td><span class="badge bg-success">Lunas</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Petugas</td>
                                <td><?= htmlspecialchars($d['nama_petugas'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <table class="table table-sm align-middle mb-2">
                            <thead>
                                <tr class="text-muted small">
                                    <th>No. Transaksi</th>
                                    <th>Bulan</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftar_transaksi as $t): ?>
                                    <tr>
                                        <td class="small text-muted">#<?= str_pad($t['id_pembayaran'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?= htmlspecialchars($t['bulan_dibayar'] . ' ' . $t['tahun_dibayar']); ?></td>
                                        <td class="text-end">Rp <?= number_format($t['jumlah_bayar'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold" style="background-color:#fdf2f8;">
                                    <td colspan="2">Total <?= count($daftar_transaksi); ?> bulan</td>
                                    <td class="text-end" style="color:#db2777;">Rp <?= number_format($total_bayar, 0, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="small text-muted">
                            Tanggal bayar <?= date('d F Y', strtotime($d['tgl_bayar'])); ?> &middot;
                            Petugas <?= htmlspecialchars($d['nama_petugas'] ?? '-'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3" style="color:#9d174d;">Data Siswa</h6>
                    <table class="table table-sm align-middle mb-0">
                        <tr>
                            <td class="text-muted" style="width:40%;">Nama</td>
                            <td><strong><?= htmlspecialchars($d['nama']); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">NISN</td>
                            <td><?= htmlspecialchars($d['nisn']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">NIS</td>
                            <td><?= htmlspecialchars($d['nis']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kelas</td>
                            <td><?= htmlspecialchars($d['tingkat'] . ' ' . $jurusan_kode . ' ' . $rombel_no); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">No. Telp</td>
                            <td><?= htmlspecialchars($d['no_telp']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekap 12 bulan -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <h6 class="fw-bold mb-3" style="color:#9d174d;">
                Rekap Pembayaran Tahun Ajaran <?= formatTA($d['tahun_dibayar']); ?> <span class="badge bg-secondary ms-1"><?= count($bulan_lunas); ?> / 12 bulan lunas</span>
            </h6>
            <div class="d-flex flex-wrap gap-2">
                <?php
                $semua_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                foreach ($semua_bulan as $b) {
                    $ok = in_array($b, $bulan_lunas);
                    $aktif = in_array($b, $bulan_di_transaksi_ini);
                    $cls = $ok ? 'bg-success' : 'bg-light text-muted border';
                    echo "<span class='badge $cls px-3 py-2" . ($aktif ? " border border-dark border-2" : "") . "'>$b</span>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="pembayaran.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <div class="d-flex gap-2">
            <a href="tambah_pembayaran.php?tingkat=<?= urlencode($d['tingkat']); ?>&jurusan=<?= urlencode($jurusan_kode); ?>&rombel=<?= urlencode($rombel_no); ?>&nisn=<?= urlencode($d['nisn']); ?>"
                class="btn btn-outline-secondary">Bayar Bulan Lain</a>
            <a href="cetak_pembayaran.php?ids=<?= implode(',', $daftar_id); ?>" target="_blank"
                class="btn text-white" style="background-color:#db2777;"><i class="bi bi-printer me-1"></i> Cetak Kuitansi</a>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>