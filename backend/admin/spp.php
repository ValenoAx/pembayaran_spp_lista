<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';
include '../components/header.php';
include '../components/sidebar.php';

// ================== TAMBAHAN: palet warna badge Tahun Ajaran ==================
// Dipilih bergantian berdasarkan urutan baris, jadi tiap tahun ajaran kebagian warna beda.
$palet_warna = [
    ['bg' => '#fce7f3', 'text' => '#db2777'], // pink
    ['bg' => '#ede9fe', 'text' => '#7c3aed'], // ungu
    ['bg' => '#dbeafe', 'text' => '#2563eb'], // biru
    ['bg' => '#dcfce7', 'text' => '#16a34a'], // hijau
    ['bg' => '#fef3c7', 'text' => '#d97706'], // kuning/amber
    ['bg' => '#cffafe', 'text' => '#0891b2'], // cyan
];

// ================== TAMBAHAN: ambil semua data dulu ke array, biar bisa dihitung buat kartu ringkasan ==================
$data_spp = [];
$hasil = mysqli_query($koneksi, "SELECT * FROM spp ORDER BY id_spp DESC");
while ($row = mysqli_fetch_assoc($hasil)) {
    $data_spp[] = $row;
}

// ================== TAMBAHAN: hitung info yang lebih variatif buat kartu ringkasan ==================
$total_tahun_ajaran = count($data_spp);

// Urutkan salinan datanya berdasarkan tahun (angka awal tahun ajaran) dari yang paling baru,
// supaya bisa nentuin tahun ajaran terbaru & tahun ajaran sebelumnya buat dibandingkan.
$data_urut_tahun = $data_spp;
usort($data_urut_tahun, function ($a, $b) {
    return $b['tahun'] <=> $a['tahun'];
});

$tahun_terbaru     = $data_urut_tahun[0] ?? null;
$tahun_sebelumnya  = $data_urut_tahun[1] ?? null;

// Selisih nominal antara tahun ajaran terbaru vs sebelumnya (buat kartu "naik/turun berapa").
$selisih_nominal = null;
$persen_perubahan = null;
if ($tahun_terbaru && $tahun_sebelumnya) {
    $selisih_nominal = $tahun_terbaru['nominal'] - $tahun_sebelumnya['nominal'];
    $persen_perubahan = $tahun_sebelumnya['nominal'] > 0
        ? ($selisih_nominal / $tahun_sebelumnya['nominal']) * 100
        : 0;
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold mb-3" style="color: #db2777;">💳 Kelola Data SPP</h1>
        <a href="tambah_spp.php" class="btn btn-primary text-white" style="background-color: #db2777; border-color: #db2777;">
            <i class="bi bi-plus-lg me-1"></i> Tambah SPP
        </a>
    </div>

    <style>
        /* ================== TAMBAHAN: gaya kartu ringkasan & tabel model Data Siswa ================== */
        .kartu-ringkasan {
            border: none;
            border-radius: 16px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            height: 100%;
        }
        .kartu-ringkasan .ikon-box {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .kartu-ringkasan .label-kecil { font-size: 0.8rem; color: #6b7280; margin-bottom: 2px; }
        .kartu-ringkasan .angka-besar { font-size: 1.4rem; font-weight: 700; color: #111827; line-height: 1.1; }

        .badge-ta-cantik {
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-aksi-pil {
            border-radius: 999px;
            width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            border: none;
        }
        #tabelSpp tbody tr:hover { background-color: #fdf2f8; }
    </style>

    <!-- ================== TAMBAHAN: KARTU RINGKASAN (lebih variatif, ga mirip-mirip semua) ================== -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kartu-ringkasan" style="background-color:#fce7f3;">
                <div class="ikon-box" style="background-color:#fbcfe8;">🆕</div>
                <div>
                    <div class="label-kecil">Tahun Ajaran Terbaru</div>
                    <?php if ($tahun_terbaru): ?>
                        <div class="angka-besar"><?= formatTA($tahun_terbaru['tahun']); ?></div>
                        <div class="text-muted small">Rp <?= number_format($tahun_terbaru['nominal'], 0, ',', '.'); ?> / bulan</div>
                    <?php else: ?>
                        <div class="angka-besar">-</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <?php
            // Warna & ikon kartu kedua menyesuaikan: hijau+panah naik kalau nominal naik,
            // merah+panah turun kalau nominal turun dari tahun ajaran sebelumnya.
            if ($selisih_nominal !== null && $selisih_nominal >= 0) {
                $bg_kartu2 = '#dcfce7'; $bg_ikon2 = '#bbf7d0'; $ikon2 = '📈';
            } else {
                $bg_kartu2 = '#fee2e2'; $bg_ikon2 = '#fecaca'; $ikon2 = '📉';
            }
            ?>
            <div class="kartu-ringkasan" style="background-color:<?= $bg_kartu2; ?>;">
                <div class="ikon-box" style="background-color:<?= $bg_ikon2; ?>;"><?= $ikon2; ?></div>
                <div>
                    <div class="label-kecil">Perubahan dari Tahun Sebelumnya</div>
                    <?php if ($selisih_nominal !== null): ?>
                        <div class="angka-besar"><?= $selisih_nominal >= 0 ? '+' : ''; ?>Rp <?= number_format($selisih_nominal, 0, ',', '.'); ?></div>
                        <div class="text-muted small"><?= $selisih_nominal >= 0 ? '+' : ''; ?><?= number_format($persen_perubahan, 1, ',', '.'); ?>%</div>
                    <?php else: ?>
                        <div class="angka-besar">-</div>
                        <div class="text-muted small">Belum ada data pembanding</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kartu-ringkasan" style="background-color:#ede9fe;">
                <div class="ikon-box" style="background-color:#ddd6fe;">📊</div>
                <div>
                    <div class="label-kecil">Total Tahun Ajaran Tercatat</div>
                    <div class="angka-besar"><?= $total_tahun_ajaran; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelSpp" class="table table-hover align-middle mb-0">
                    <thead style="background-color: #fdf2f8; color: #db2777;">
                        <tr>
                            <th>No</th>
                            <th>Tahun Ajaran</th>
                            <th>Nominal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_spp) > 0): ?>
                            <?php $no = 1; foreach ($data_spp as $index => $row):
                                $warna = $palet_warna[$index % count($palet_warna)];
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td>
                                        <span class="badge-ta-cantik" style="background-color: <?= $warna['bg']; ?>; color: <?= $warna['text']; ?>;">
                                            🗓️ <?= formatTA($row['tahun']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold" style="color:#16a34a;">Rp <?= number_format($row['nominal'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="edit_spp.php?id=<?= $row['id_spp']; ?>" class="btn-aksi-pil" style="background-color:#fef3c7; color:#d97706;" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="hapus_spp.php?id=<?= $row['id_spp']; ?>" class="btn-aksi-pil" style="background-color:#fee2e2; color:#dc2626;" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada data SPP.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../components/footer.php'; ?>