<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';
include '../components/header.php';
include '../components/sidebar.php';

// ================== TAMBAHAN: palet warna avatar (sama semangatnya kaya halaman lain, warna beda-beda per orang) ==================
$palet_warna = [
    ['bg' => '#fce7f3', 'text' => '#db2777'], // pink
    ['bg' => '#ede9fe', 'text' => '#7c3aed'], // ungu
    ['bg' => '#dbeafe', 'text' => '#2563eb'], // biru
    ['bg' => '#dcfce7', 'text' => '#16a34a'], // hijau
    ['bg' => '#fef3c7', 'text' => '#d97706'], // kuning/amber
    ['bg' => '#cffafe', 'text' => '#0891b2'], // cyan
];

function ambil_inisial($nama) {
    $kata = preg_split('/\s+/', trim($nama));
    $inisial = strtoupper(substr($kata[0], 0, 1));
    if (count($kata) > 1) {
        $inisial .= strtoupper(substr(end($kata), 0, 1));
    }
    return $inisial;
}

// ================== TAMBAHAN: ambil semua data dulu ke array ==================
$data_petugas = [];
$hasil = mysqli_query($koneksi, "SELECT * FROM petugas WHERE level = 'petugas' ORDER BY id_petugas DESC");
while ($row = mysqli_fetch_assoc($hasil)) {
    $data_petugas[] = $row;
}
$total_petugas = count($data_petugas);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <!-- Judul dan Tombol Tambah di Sebelah Kiri -->
    <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-end flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #db2777;">🧑‍💼 Kelola Data Petugas</h1>
            <p class="text-muted small mb-0"><?= $total_petugas; ?> petugas terdaftar</p>
        </div>
        <a href="tambah_petugas.php" class="btn btn-primary text-white mb-1" style="background-color: #db2777; border-color: #db2777;">
            <i class="bi bi-plus-lg me-1"></i> Tambah Petugas
        </a>
    </div>

    <style>
        /* ================== TAMBAHAN: gaya kartu profil petugas ================== */
        .kartu-petugas {
            border-radius: 18px;
            border: 1px solid #f3e8ff10;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(219, 39, 119, 0.06);
            padding: 1.25rem;
            height: 100%;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kartu-petugas:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(219, 39, 119, 0.12);
        }
        .avatar-petugas {
            width: 56px; height: 56px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.1rem;
            flex-shrink: 0;
        }
        .badge-level-cantik {
            border-radius: 999px;
            padding: 0.3rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .info-password-baris {
            border-top: 1px dashed #f0d9e6;
            margin-top: 0.9rem;
            padding-top: 0.7rem;
            font-size: 0.8rem;
        }
        .btn-aksi-petugas {
            border-radius: 10px;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border: none;
        }
    </style>

    <?php if ($total_petugas === 0): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-person-x fs-3 d-block mb-2"></i>
                Belum ada petugas yang ditambahkan.
            </div>
        </div>

    <?php else: ?>

    <!-- ================== KARTU PROFIL PETUGAS (grid, bukan tabel) ================== -->
    <div class="row g-3">
        <?php foreach ($data_petugas as $index => $row):
            $warna   = $palet_warna[$index % count($palet_warna)];
            $inisial = ambil_inisial($row['nama_petugas']);
            $sudah_ganti_password = !empty($row['password_updated_at']);
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="kartu-petugas">
                    <div class="d-flex align-items-start gap-3">
                        <div class="avatar-petugas" style="background-color: <?= $warna['bg']; ?>; color: <?= $warna['text']; ?>;">
                            <?= $inisial; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars($row['nama_petugas']); ?></div>
                            <div class="text-muted small">@<?= htmlspecialchars($row['username']); ?></div>
                            <span class="badge-level-cantik mt-1 d-inline-block" style="background-color: <?= ($row['level'] == 'admin') ? '#fee2e2' : '#dbeafe'; ?>; color: <?= ($row['level'] == 'admin') ? '#dc2626' : '#2563eb'; ?>;">
                                <?= ucfirst($row['level']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-password-baris">
                        <?php if ($sudah_ganti_password): ?>
                            <i class="bi bi-shield-check text-success me-1"></i>
                            <span class="text-muted">Password terakhir diganti</span><br>
                            <span class="fw-semibold"><?= date('d M Y, H:i', strtotime($row['password_updated_at'])); ?></span>
                        <?php else: ?>
                            <i class="bi bi-shield-exclamation text-warning me-1"></i>
                            <span class="text-warning fst-italic">Belum pernah ganti password</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <a href="edit_petugas.php?id=<?= $row['id_petugas']; ?>" class="btn-aksi-petugas flex-fill text-white" style="background-color:#f59e0b;">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>
                        <a href="hapus_petugas.php?id=<?= $row['id_petugas']; ?>" class="btn-aksi-petugas flex-fill text-white" style="background-color:#ef4444;" onclick="return confirm('Yakin ingin menghapus petugas ini?')">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</main>

<?php include '../components/footer.php'; ?>