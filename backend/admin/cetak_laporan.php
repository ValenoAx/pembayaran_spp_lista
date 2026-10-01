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

$judul = '';
$data = [];
$total_bayar_keseluruhan = 0;

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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($judul); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 30px; }
        .kop { border-bottom: 3px double #db2777; padding-bottom: 14px; margin-bottom: 22px; }
        .kop h4 { color: #db2777; font-weight: 700; margin-bottom: 2px; }
        table { font-size: 14px; }
        .total-box {
            background: #fdf2f8; border: 1px dashed #fbcfe8; border-radius: 10px;
            padding: 14px 18px; margin-top: 18px; max-width: 320px; margin-left: auto;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="kop d-flex justify-content-between align-items-start">
    <div>
        <h4>SPP DIGITAL</h4>
        <div class="small text-muted">Sistem Informasi Pembayaran SPP</div>
    </div>
    <div class="text-end small">
        <div class="text-muted">Dicetak pada <?= date('d F Y H:i'); ?></div>
    </div>
</div>

<h5 class="fw-bold mb-3" style="color:#db2777;"><?= htmlspecialchars($judul); ?></h5>

<?php if (count($data) === 0): ?>
    <p class="text-muted">Tidak ada data yang cocok dengan filter ini.</p>

<?php elseif ($jenis === 'siswa'): ?>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th>No</th><th>NISN</th><th>NIS</th><th>Nama</th>
                <th>Kelas</th><th>Alamat</th><th>No. Telp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $i => $s): ?>
            <tr>
                <td><?= $i + 1; ?></td>
                <td><?= htmlspecialchars($s['nisn']); ?></td>
                <td><?= htmlspecialchars($s['nis']); ?></td>
                <td><?= htmlspecialchars($s['nama']); ?></td>
                <td><?= htmlspecialchars($s['tingkat'] . ' ' . $s['jurusan']); ?></td>
                <td><?= htmlspecialchars($s['alamat']); ?></td>
                <td><?= htmlspecialchars($s['no_telp']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-muted small">Total: <?= count($data); ?> siswa</p>

<?php else: ?>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th>No</th><th>Tgl Bayar</th><th>Nama Siswa</th><th>Kelas</th>
                <th>Bulan/Tahun SPP</th><th>Jumlah Bayar</th><th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $i => $p): ?>
            <tr>
                <td><?= $i + 1; ?></td>
                <td><?= date('d-m-Y', strtotime($p['tgl_bayar'])); ?></td>
                <td><?= htmlspecialchars($p['nama']); ?></td>
                <td><?= htmlspecialchars($p['tingkat'] . ' ' . $p['jurusan']); ?></td>
                <td><?= htmlspecialchars($p['bulan_dibayar'] . ' ' . $p['tahun_dibayar']); ?></td>
                <td>Rp <?= number_format($p['jumlah_bayar'], 0, ',', '.'); ?></td>
                <td><?= htmlspecialchars($p['nama_petugas'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-box">
        <div class="small text-muted">Total Keseluruhan (<?= count($data); ?> transaksi)</div>
        <div class="h5 fw-bold mb-0" style="color:#db2777;">Rp <?= number_format($total_bayar_keseluruhan, 0, ',', '.'); ?></div>
    </div>
<?php endif; ?>

<div class="text-center mt-4 no-print">
    <button onclick="window.print()" class="btn text-white" style="background-color:#db2777;">Cetak / Simpan PDF</button>
    <a href="laporan.php" class="btn btn-secondary">Kembali</a>
</div>

<script>
    window.addEventListener('load', function () { window.print(); });
</script>
</body>
</html>