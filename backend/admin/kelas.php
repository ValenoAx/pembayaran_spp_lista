<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';
include '../components/header.php';
include '../components/sidebar.php';

// Daftar jurusan yang tersedia (sinkron dengan form Tambah Siswa & Tambah Kelas)
$daftar_jurusan = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];
$tingkat_aktif = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$jurusan_aktif = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">
            Kelola Data Kelas 
            <?php if ($tingkat_aktif != ''): ?>
                - Tingkat <?= htmlspecialchars($tingkat_aktif); ?>
                <?php if ($jurusan_aktif != ''): ?>
                    - <?= htmlspecialchars($jurusan_aktif); ?>
                <?php endif; ?>
            <?php endif; ?>
        </h1>
    </div>

    <?php
    // Link Tambah Kelas: kalau lagi di halaman tingkat/jurusan tertentu, bawa infonya
    // supaya nanti tambah_kelas.php bisa otomatis pre-select (kalau filenya sudah disesuaikan).
    $param_tambah = [];
    if ($tingkat_aktif != '') $param_tambah['tingkat'] = $tingkat_aktif;
    if ($jurusan_aktif != '') $param_tambah['jurusan'] = $jurusan_aktif;
    $link_tambah = 'tambah_kelas.php' . (count($param_tambah) > 0 ? '?' . http_build_query($param_tambah) : '');
    ?>
    <div class="mb-3">
        <a href="<?= $link_tambah; ?>" class="btn btn-primary" style="background-color: #db2777; border: none;"><i class="bi bi-plus-lg me-1"></i> Tambah Kelas</a>
    </div>

    <?php if ($tingkat_aktif != ''): ?>
    <!-- Sub-filter jurusan: cuma muncul kalau lagi buka halaman tingkat tertentu -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="kelas.php?tingkat=<?= urlencode($tingkat_aktif); ?>"
           class="btn btn-sm <?= ($jurusan_aktif == '') ? 'btn-primary' : 'btn-outline-secondary'; ?>"
           style="<?= ($jurusan_aktif == '') ? 'background-color:#db2777;border:none;' : ''; ?>">
           Semua Jurusan
        </a>
        <?php foreach ($daftar_jurusan as $j): ?>
            <a href="kelas.php?tingkat=<?= urlencode($tingkat_aktif); ?>&jurusan=<?= urlencode($j); ?>"
               class="btn btn-sm <?= ($jurusan_aktif == $j) ? 'btn-primary' : 'btn-outline-secondary'; ?>"
               style="<?= ($jurusan_aktif == $j) ? 'background-color:#db2777;border:none;' : ''; ?>">
               <?= $j; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead style="background-color: #fdf2f8; color: #db2777;">
                        <tr>
                            <th>No</th>
                            <th>Tingkat</th>
                            <th>Jurusan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $kondisi = [];

                        // Filter EXACT MATCH karena data di DB sudah rapi (persis "10"/"11"/"12",
                        // dan jurusan format "PPLG 1", "AKL 2", dst).
                        if ($tingkat_aktif != '') {
                            $tkt = mysqli_real_escape_string($koneksi, $tingkat_aktif);
                            $kondisi[] = "tingkat = '$tkt'";
                        }
                        if ($jurusan_aktif != '') {
                            $jrs = mysqli_real_escape_string($koneksi, $jurusan_aktif);
                            $kondisi[] = "jurusan LIKE '$jrs%'";
                        }
                        $where = count($kondisi) > 0 ? "WHERE " . implode(" AND ", $kondisi) : "";

                        $query = mysqli_query($koneksi, "SELECT * FROM kelas $where ORDER BY tingkat, jurusan");
                        
                        if ($query && mysqli_num_rows($query) > 0) {
                            while ($row = mysqli_fetch_assoc($query)) {
                                // Data sudah bersih di database, tampilkan apa adanya (tidak perlu ditebak lagi).
                                $tampilkan_tingkat = $row['tingkat'];
                                // Jurusan formatnya "PPLG 1", "AKL 2", dst -> tampilkan lengkap dengan nomor rombelnya.
                                $tampilkan_jurusan = $row['jurusan'];
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($tampilkan_tingkat); ?></span></td>
                                    <td><?= htmlspecialchars($tampilkan_jurusan); ?></td>
                                    <td>
                                        <a href="edit_kelas.php?id_kelas=<?= $row['id_kelas']; ?>" class="btn btn-warning btn-sm text-white"><i class="bi bi-pencil-square"></i></a>
                                        <a href="hapus_kelas.php?id_kelas=<?= $row['id_kelas']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus kelas ini?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-3 text-muted'>Tidak ada data kelas untuk filter ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>