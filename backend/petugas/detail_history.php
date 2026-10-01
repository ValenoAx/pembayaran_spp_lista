<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$nisn = mysqli_real_escape_string($koneksi, $_GET['nisn'] ?? '');

// Ambil data siswa + tingkat kelasnya (BUKAN lewat id_spp lagi, karena sekarang bisa multi-tahun)
$q_siswa = mysqli_query($koneksi, "
    SELECT siswa.*, kelas.tingkat, kelas.jurusan
    FROM siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    WHERE siswa.nisn = '$nisn'
");
$s = mysqli_fetch_assoc($q_siswa);

if (!$s) {
    echo "<script>alert('Data siswa tidak ditemukan!'); window.location='history.php';</script>";
    exit();
}

$tingkat = (int) $s['tingkat'];

// Ambil semua tahun SPP yang ada, urut dari yang paling baru
$tahun_urut = []; // [tahun => nominal], besar ke kecil
$q_tahun = mysqli_query($koneksi, "SELECT tahun, nominal FROM spp ORDER BY tahun DESC");
while ($t = mysqli_fetch_assoc($q_tahun)) {
    $tahun_urut[(int) $t['tahun']] = (int) $t['nominal'];
}

// Batasi jumlah tahun yang ditampilkan sesuai tingkat:
// Kelas 10 -> 1 tahun (tahun berjalan), Kelas 11 -> 2 tahun, Kelas 12 -> 3 tahun
$jumlah_tahun_ditampilkan = $tingkat - 9; // 10->1, 11->2, 12->3
$tahun_untuk_siswa = array_slice($tahun_urut, 0, max($jumlah_tahun_ditampilkan, 1), true);
krsort($tahun_untuk_siswa); // tampilkan dari tahun terbaru ke lama

$pageTitle   = 'Detail Status Pembayaran - Petugas';
$currentPage = 'history.php';
include __DIR__ . '/components/header.php';
include __DIR__ . '/components/sidebar.php';

// Avatar inisial, senada sama halaman History Status Siswa
$PALET_AVATAR = [
    ['bg' => '#fce7f3', 'fg' => '#be185d'],
    ['bg' => '#ede9fe', 'fg' => '#7c3aed'],
    ['bg' => '#fef3c7', 'fg' => '#b45309'],
    ['bg' => '#dcfce7', 'fg' => '#15803d'],
    ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
    ['bg' => '#ffe4e6', 'fg' => '#be123c'],
];
function inisialNama($nama)
{
    $kata = preg_split('/\s+/', trim($nama));
    $inisial = strtoupper(substr($kata[0], 0, 1));
    if (count($kata) > 1) $inisial .= strtoupper(substr(end($kata), 0, 1));
    return $inisial;
}
function warnaAvatar($nama, $palet)
{
    $indeks = array_sum(array_map('ord', str_split($nama))) % count($palet);
    return $palet[$indeks];
}
function warnaProgress($persen)
{
    if ($persen >= 100) return '#16a34a';
    if ($persen >= 60) return '#65a30d';
    if ($persen >= 30) return '#f59e0b';
    return '#f43f5e';
}
$avatar = warnaAvatar($s['nama'], $PALET_AVATAR);

$bulan_arr = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Hitung dulu semua data per tahun ajaran (dipakai buat isi tab & konten tab)
$data_tahun = [];
foreach ($tahun_untuk_siswa as $tahun => $nominal_tagihan) {
    $status_bulan = [];
    $jml_lunas = 0;
    foreach ($bulan_arr as $bln) {
        $q_cek = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total_bayar FROM pembayaran
                                          WHERE nisn = '$nisn' AND bulan_dibayar = '$bln' AND tahun_dibayar = '$tahun'");
        $sudah_bayar = mysqli_fetch_assoc($q_cek)['total_bayar'] ?? 0;
        $status_bulan[$bln] = $sudah_bayar;
        if ($sudah_bayar >= $nominal_tagihan) $jml_lunas++;
    }
    $persen = round($jml_lunas / 12 * 100);
    $data_tahun[$tahun] = [
        'nominal'      => $nominal_tagihan,
        'status_bulan' => $status_bulan,
        'jml_lunas'    => $jml_lunas,
        'persen'       => $persen,
        'warna'        => warnaProgress($persen),
    ];
}
$daftar_tahun_keys = array_keys($data_tahun);
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

<div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
    <h1 class="h3 fw-bold mb-0" style="color: #db2777;">📖 Detail Status Pembayaran SPP</h1>
    <a href="history.php" class="btn-kembali">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<!-- KARTU PROFIL SISWA -->
<div class="card border-0 shadow-sm mb-3 kartu-profil">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-besar" style="background:<?= $avatar['bg']; ?>; color:<?= $avatar['fg']; ?>;">
            <?= htmlspecialchars(inisialNama($s['nama'])); ?>
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold fs-5"><?= htmlspecialchars($s['nama']); ?></div>
            <div class="text-muted small mb-1">NISN: <?= htmlspecialchars($s['nisn']); ?></div>
            <span class="badge-kelas">🎓 <?= htmlspecialchars($tingkat . ' ' . $s['jurusan']); ?></span>
        </div>
    </div>
</div>

<?php if (count($data_tahun) === 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada tarif SPP yang cocok untuk siswa ini.</div>
    </div>
<?php else: ?>

<!-- TAB TAHUN AJARAN -->
<div class="tab-tahun-wrap mb-3">
    <?php foreach ($data_tahun as $tahun => $d): ?>
        <button type="button" class="tab-tahun-btn <?= ($tahun === $daftar_tahun_keys[0]) ? 'aktif' : ''; ?>"
                data-target="tahun-<?= $tahun; ?>">
            <span class="tab-tahun-label"><?= formatTA($tahun); ?></span>
            <span class="tab-tahun-progress" style="color:<?= $d['warna']; ?>;">
                <?= $d['jml_lunas']; ?>/12<?= $d['persen'] >= 100 ? ' 🎉' : ''; ?>
            </span>
        </button>
    <?php endforeach; ?>
</div>

<?php foreach ($data_tahun as $tahun => $d): ?>
<div class="panel-tahun card border-0 shadow-sm mb-3" id="tahun-<?= $tahun; ?>"
     style="<?= ($tahun !== $daftar_tahun_keys[0]) ? 'display:none;' : ''; ?>">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="fw-bold mb-0" style="color:#9d174d;">📅 Tahun Ajaran <?= formatTA($tahun); ?></h6>
            <div class="d-flex align-items-center gap-2" style="min-width:180px;">
                <div class="progress-mini flex-grow-1">
                    <div class="progress-mini-isi" style="width:<?= $d['persen']; ?>%; background:<?= $d['warna']; ?>;"></div>
                </div>
                <span class="small fw-semibold" style="color:<?= $d['warna']; ?>; white-space:nowrap;">
                    <?= $d['jml_lunas']; ?>/12 bulan<?= $d['persen'] >= 100 ? ' 🎉' : ''; ?>
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 tabel-bulan">
                <thead style="background-color: #fdf2f8; color: #db2777;">
                    <tr>
                        <th class="text-start ps-4">Bulan</th>
                        <th>Total Tagihan</th>
                        <th>Sudah Dibayar</th>
                        <th>Status Pelunasan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bulan_arr as $bln):
                        $sudah_bayar = $d['status_bulan'][$bln];
                        if ($sudah_bayar >= $d['nominal']) {
                            $baris_class = 'baris-lunas';
                            $badge = '<span class="badge-status badge-lunas">✅ Lunas</span>';
                        } elseif ($sudah_bayar > 0) {
                            $baris_class = 'baris-cicilan';
                            $badge = '<span class="badge-status badge-cicilan">🟡 Cicilan (Rp ' . number_format($sudah_bayar, 0, ',', '.') . ')</span>';
                        } else {
                            $baris_class = '';
                            $badge = '<span class="badge-status badge-belum">⏳ Belum Bayar</span>';
                        }
                    ?>
                    <tr class="<?= $baris_class; ?>">
                        <td class="fw-semibold text-start ps-4">📆 <?php echo $bln; ?></td>
                        <td>Rp <?php echo number_format($d['nominal'], 0, ',', '.'); ?></td>
                        <td class="fw-semibold text-success">Rp <?php echo number_format($sudah_bayar, 0, ',', '.'); ?></td>
                        <td><?php echo $badge; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<style>
    .btn-kembali {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fff; color: #9d174d; text-decoration: none;
        border: 1px solid #f3d4e4; padding: 7px 16px; border-radius: 999px;
        font-size: .85rem; font-weight: 600;
        transition: background .15s ease, transform .15s ease;
    }
    .btn-kembali:hover { background: #fdf2f8; color: #9d174d; transform: translateX(-2px); }

    .kartu-profil { border-radius: 16px; background: linear-gradient(135deg, #fff, #fdf2f8); }
    .avatar-besar {
        width: 56px; height: 56px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1.2rem; flex-shrink: 0;
    }
    .badge-kelas {
        background: #f3f4f6; color: #374151;
        padding: 4px 10px; border-radius: 999px;
        font-size: .78rem; font-weight: 600; white-space: nowrap;
    }

    /* TAB TAHUN AJARAN */
    .tab-tahun-wrap {
        display: flex; gap: 10px; flex-wrap: wrap;
        background: #fdf2f8; padding: 8px; border-radius: 16px;
    }
    .tab-tahun-btn {
        display: flex; flex-direction: column; align-items: center; gap: 2px;
        border: none; background: #fff; color: #6b7280;
        padding: 10px 20px; border-radius: 12px; cursor: pointer;
        font-weight: 700; font-size: .88rem;
        box-shadow: 0 1px 2px rgba(0,0,0,.05);
        transition: transform .15s ease, background .15s ease, color .15s ease;
    }
    .tab-tahun-btn:hover { transform: translateY(-2px); }
    .tab-tahun-btn.aktif {
        background: linear-gradient(135deg, #db2777, #be185d);
        color: #fff;
    }
    .tab-tahun-btn.aktif .tab-tahun-progress { color: #fff !important; opacity: .9; }
    .tab-tahun-progress { font-size: .74rem; font-weight: 600; }

    .panel-tahun { border-radius: 16px; animation: munculTab .2s ease; }
    @keyframes munculTab {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .progress-mini { height: 8px; border-radius: 999px; background: #f1f1f4; overflow: hidden; }
    .progress-mini-isi { height: 100%; border-radius: 999px; transition: width .3s ease; }

    .tabel-bulan { border-collapse: separate; border-spacing: 0 6px; }
    .tabel-bulan thead th { border: none; font-size: .82rem; padding: 10px 12px; }
    .tabel-bulan tbody td { border: none; padding: 11px 12px; background: #fff; }
    .tabel-bulan tbody tr { box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .tabel-bulan tbody tr.baris-lunas td { background: #f0fdf4; }
    .tabel-bulan tbody tr.baris-cicilan td { background: #fffbeb; }
    .tabel-bulan tbody td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
    .tabel-bulan tbody td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

    .badge-status {
        display: inline-block; padding: 5px 12px; border-radius: 999px;
        font-size: .78rem; font-weight: 700;
    }
    .badge-lunas { background: #dcfce7; color: #15803d; }
    .badge-cicilan { background: #fef3c7; color: #b45309; }
    .badge-belum { background: #f3f4f6; color: #6b7280; }
</style>

<script>
    (function () {
        var tombolTab = document.querySelectorAll('.tab-tahun-btn');
        var panelTab = document.querySelectorAll('.panel-tahun');

        tombolTab.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.dataset.target;

                tombolTab.forEach(function (b) { b.classList.remove('aktif'); });
                btn.classList.add('aktif');

                panelTab.forEach(function (p) {
                    p.style.display = (p.id === target) ? '' : 'none';
                });
            });
        });
    })();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>