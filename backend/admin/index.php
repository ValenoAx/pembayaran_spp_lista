<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Menghitung jumlah data secara dinamis dari database
$query_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa");
$data_siswa = mysqli_fetch_assoc($query_siswa);
$total_siswa = $data_siswa['total'];

$query_petugas = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM petugas");
$data_petugas = mysqli_fetch_assoc($query_petugas);
$total_petugas = $data_petugas['total'];

$query_kelas = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas");
$data_kelas = mysqli_fetch_assoc($query_kelas);
$total_kelas = $data_kelas['total'];

// ================== Siswa Belum Lunas (semua tahun ajaran, bukan cuma bulan ini) ==================
$bulan_arr = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$tahun_sekarang     = (int) date('Y');
$bulan_angka        = (int) date('n');
// Tahun ajaran berjalan dianggap mulai bulan Juli (umum di sekolah Indonesia)
$tahun_ajaran_aktif = ($bulan_angka >= 7) ? $tahun_sekarang : $tahun_sekarang - 1;

// Ambil nominal SPP per tahun (tahun => nominal), urut terbaru dulu
$tahun_nominal = [];
$q_tahun_spp = mysqli_query($koneksi, "SELECT tahun, nominal FROM spp ORDER BY tahun DESC");
while ($t = mysqli_fetch_assoc($q_tahun_spp)) {
    $tahun_nominal[(int) $t['tahun']] = (int) $t['nominal'];
}

// Ambil semua siswa + kelasnya
$q_semua_siswa = mysqli_query($koneksi, "
    SELECT siswa.nisn, siswa.nis, siswa.nama, kelas.tingkat, kelas.jurusan
    FROM siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    ORDER BY siswa.nama ASC
");

$daftar_belum_lunas_semua = [];

while ($s = mysqli_fetch_assoc($q_semua_siswa)) {
    $tingkat = (int) $s['tingkat'];
    // Kelas 10 -> 1 tahun ajaran, 11 -> 2 tahun, 12 -> 3 tahun
    $jumlah_tahun = max($tingkat - 9, 1);
    // FIX: sebelumnya ambil N tahun PALING BARU dari tabel spp (array_slice),
    // padahal kalau di tabel spp sudah ada nominal untuk tahun-tahun depan
    // (2026, 2027, dst) itu bikin tahun yang dicek jadi salah sasaran -
    // bukan tahun yang benar-benar dijalani siswa. Sekarang dihitung mundur
    // dari tahun ajaran aktif sekarang, sesuai jumlah tahun siswa itu sudah sekolah.
    $tahun_untuk_siswa = [];
    for ($i = 0; $i < $jumlah_tahun; $i++) {
        $th = $tahun_ajaran_aktif - $i;
        if (isset($tahun_nominal[$th])) {
            $tahun_untuk_siswa[$th] = $tahun_nominal[$th];
        }
    }

    $total_wajib  = $jumlah_tahun * 12;
    $jumlah_lunas = 0;

    foreach ($tahun_untuk_siswa as $tahun => $nominal) {
        // FIX: kolom tahun_dibayar di tabel pembayaran disimpan dalam format
        // "2025/2026" (bukan cuma "2025"), jadi pakai LIKE 'tahun%' supaya
        // tetap cocok baik format "2025" maupun "2025/2026".
        $q_bayar = mysqli_query($koneksi, "
            SELECT bulan_dibayar, SUM(jumlah_bayar) AS total
            FROM pembayaran
            WHERE nisn = '" . mysqli_real_escape_string($koneksi, $s['nisn']) . "'
              AND tahun_dibayar LIKE '" . mysqli_real_escape_string($koneksi, $tahun) . "%'
            GROUP BY bulan_dibayar
        ");
        $bayar_per_bulan = [];
        while ($b = mysqli_fetch_assoc($q_bayar)) {
            $bayar_per_bulan[$b['bulan_dibayar']] = (int) $b['total'];
        }
        foreach ($bulan_arr as $bln) {
            $sudah = $bayar_per_bulan[$bln] ?? 0;
            if ($sudah >= $nominal) $jumlah_lunas++;
        }
    }

    $persen = $total_wajib > 0 ? round($jumlah_lunas / $total_wajib * 100) : 0;

    // Kalau udah lunas penuh, skip (gak usah ditampilin)
    if ($persen >= 100) continue;

    $daftar_belum_lunas_semua[] = [
        'nisn'         => $s['nisn'],
        'nis'          => $s['nis'],
        'nama'         => $s['nama'],
        'tingkat'      => $s['tingkat'],
        'jurusan'      => $s['jurusan'],
        'jumlah_lunas' => $jumlah_lunas,
        'total_wajib'  => $total_wajib,
        'persen'       => $persen,
    ];
}

// Urutkan: yang bayarnya PALING SEDIKIT (persen paling kecil) di paling atas
usort($daftar_belum_lunas_semua, function ($a, $b) {
    return $a['persen'] <=> $b['persen'];
});

// Catatan: pencarian LIVE di browser (JavaScript), bukan reload halaman.
$total_belum_lunas  = count($daftar_belum_lunas_semua);
$daftar_belum_lunas = $daftar_belum_lunas_semua;

function warnaProgressBackendAdmin($persen)
{
    if ($persen >= 60) return '#65a30d';
    if ($persen >= 30) return '#f59e0b';
    return '#f43f5e';
}

// Bikin link "Bayar Sekarang" yang otomatis mengarah & memfilter ke siswa terkait di halaman Pembayaran
function link_bayar_admin($row) {
    $jurusan_kode = trim(preg_replace('/\s*\d+$/', '', $row['jurusan']));
    $rombel_no = '';
    if (preg_match('/(\d+)\s*$/', $row['jurusan'], $m)) $rombel_no = $m[1];
    return 'pembayaran.php?tingkat=' . urlencode($row['tingkat'])
        . '&jurusan=' . urlencode($jurusan_kode)
        . '&rombel=' . urlencode($rombel_no)
        . '&nisn=' . urlencode($row['nisn'])
        . '&f=1#bayar';
}

include '../components/header.php';
include '../components/sidebar.php';
?>

<style>
    /* ================== TAMBAHAN: widget jam & awan ================== */
    .widget-jam {
        position: relative;
        overflow: hidden;
        min-height: 220px;
        background: linear-gradient(160deg, #38bdf8 0%, #0ea5e9 55%, #0284c7 100%);
    }
    .widget-jam .awan-area {
        position: absolute;
        inset: 0;
        overflow: hidden;
        pointer-events: none;
    }
    .widget-jam .awan {
        position: absolute;
        color: rgba(255, 255, 255, 0.3);
    }
    .widget-jam .awan-1 { top: 14px;  left: -10px; font-size: 3.2rem; animation: mengambang 9s ease-in-out infinite; }
    .widget-jam .awan-2 { top: 60px;  right: -6px; font-size: 2.2rem; animation: mengambang 7s ease-in-out infinite reverse; }
    .widget-jam .awan-3 { bottom: 18px; left: 20%;  font-size: 1.6rem; animation: mengambang 11s ease-in-out infinite; }
    .widget-jam .awan-4 { top: 100px; left: 55%;    font-size: 1.2rem; animation: mengambang 8s ease-in-out infinite reverse; }

    @keyframes mengambang {
        0%, 100% { transform: translate(0, 0); }
        50%      { transform: translate(10px, -6px); }
    }

    .widget-jam .jam-besar {
        font-size: 2.6rem;
        font-weight: 800;
        letter-spacing: 1px;
        color: #fff;
        line-height: 1.1;
    }
    .widget-jam .tanggal-kecil {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.9rem;
    }

    /* ================== progress mini (samain sama history_siswa.php) ================== */
    .progress-mini {
        width: 100%; height: 8px; border-radius: 999px;
        background: #f1f1f4; overflow: hidden;
    }
    .progress-mini-isi {
        height: 100%; border-radius: 999px;
        transition: width .3s ease;
    }
</style>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div>
            <h1 class="h3 fw-bold" style="color: #db2777;">Dashboard</h1>
            <p class="text-muted mb-0">Selamat datang kembali, <strong><?php echo htmlspecialchars($_SESSION['nama_petugas'] ?? 'Administrator'); ?></strong> </p>
        </div>
        <div>
            <span class="badge px-3 py-2 text-white shadow-sm" style="background-color: #db2777;">
                <i class="bi bi-calendar-event me-1"></i> <?php echo date('d M Y'); ?>
            </span>
        </div>
    </div>

    <!-- Cards Statistik Dinamis (3 kolom) -->
    <div class="row g-3 mb-3">
        <!-- Total Siswa (Nuansa Pink Soft) -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 h-100" style="border-left-color: #db2777 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color: #db2777;">Total Siswa</div>
                        <div class="h2 fw-bold mb-0 text-dark"><?php echo $total_siswa; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color: #db2777; background-color: #ffe6f0;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Petugas / Admin (Nuansa Ungu Soft) -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 h-100" style="border-left-color: #8b5cf6 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color: #8b5cf6;">Petugas / Admin</div>
                        <div class="h2 fw-bold mb-0 text-dark"><?php echo $total_petugas; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color: #8b5cf6; background-color: #f3e8ff;">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Kelas (Nuansa Biru/Cyan Soft) -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 h-100" style="border-left-color: #0ea5e9 !important;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1" style="color: #0ea5e9;">Data Kelas</div>
                        <div class="h2 fw-bold mb-0 text-dark"><?php echo $total_kelas; ?></div>
                    </div>
                    <div class="fs-1 p-3 rounded-3" style="color: #0ea5e9; background-color: #e0f2fe;">
                        <i class="bi bi-building-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Aplikasi + Widget Jam & Awan berdampingan -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3" style="color: #db2777;"><i class="bi bi-info-circle me-2"></i>Informasi Aplikasi Pembayaran SPP</h5>
                    <p class="text-secondary">
                        Aplikasi ini dikembangkan untuk mempermudah pengelolaan data pembayaran SPP siswa secara terkomputerisasi. Sistem ini mendukung pembagian hak akses untuk <strong>Administrator</strong>, <strong>Petugas</strong>, dan <strong>Siswa</strong>.
                    </p>
                    <div class="alert border-0 text-dark mb-0" style="background-color: #ffe6f0;" role="alert">
                        <i class="bi bi-lightbulb me-2 fw-bold" style="color: #db2777;"></i> <strong>Tips:</strong> Pastikan untuk selalu melakukan pengecekan data kelas dan nominal SPP sebelum memasukkan data siswa baru.
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== TAMBAHAN: Widget Jam & Awan ================== -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 widget-jam">
                <div class="awan-area">
                    <i class="bi bi-cloud-fill awan awan-1"></i>
                    <i class="bi bi-cloud-fill awan awan-2"></i>
                    <i class="bi bi-cloud-fill awan awan-3"></i>
                    <i class="bi bi-cloud-fill awan awan-4"></i>
                </div>
                <div class="card-body d-flex flex-column justify-content-center" style="position: relative; z-index: 2;">
                    <div class="small fw-bold text-uppercase mb-2" style="color: rgba(255,255,255,0.85);">
                        <i class="bi bi-clock-history me-1"></i> Waktu Sekarang
                    </div>
                    <div class="jam-besar" id="jamSekarangAdmin">--:--:--</div>
                    <div class="tanggal-kecil mt-1" id="tanggalSekarangAdmin">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================== Siswa Belum Lunas (semua tahun ajaran, urut dari paling sedikit bayar) ================== -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0" style="color: #db2777;">
                    <i class="bi bi-exclamation-circle me-2"></i>Siswa Belum Lunas &middot; <?= formatTA($tahun_ajaran_aktif); ?>
                    <span class="badge rounded-pill ms-1" style="background-color:#fee2e2; color:#dc2626;" id="badge-jumlah-lunas"><?= $total_belum_lunas; ?></span>
                </h5>
                <div class="input-group input-group-sm" style="max-width:260px;">
                    <span class="input-group-text bg-white" style="color:#db2777;"><i class="bi bi-search"></i></span>
                    <input type="text" id="cari-belum-lunas" class="form-control" placeholder="Cari NIS / NISN / Nama / Kelas..." autocomplete="off">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabel-belum-lunas">
                    <thead style="background-color: #fdf2f8; color: #db2777;">
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th style="min-width:180px;">Bulan Tercatat Bayar</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_belum_lunas) > 0): ?>
                            <?php $no = 1; foreach ($daftar_belum_lunas as $row):
                                $warna = warnaProgressBackendAdmin($row['persen']);
                                $kata_cari = strtolower($row['nis'] . ' ' . $row['nisn'] . ' ' . $row['nama'] . ' ' . $row['tingkat'] . ' ' . $row['jurusan']);
                            ?>
                                <tr data-cari="<?= htmlspecialchars($kata_cari); ?>">
                                    <td class="kolom-no"><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nis']); ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['nama']); ?></td>
                                    <td><?= htmlspecialchars($row['tingkat'] . ' ' . $row['jurusan']); ?></td>
                                    <td>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="fw-semibold" style="color:<?= $warna; ?>;"><?= $row['jumlah_lunas']; ?>/<?= $row['total_wajib']; ?> bulan</span>
                                            <span class="text-muted"><?= $row['persen']; ?>%</span>
                                        </div>
                                        <div class="progress-mini">
                                            <div class="progress-mini-isi" style="width:<?= $row['persen']; ?>%; background:<?= $warna; ?>;"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= link_bayar_admin($row); ?>" class="btn btn-sm text-white" style="background-color:#db2777;">
                                            <i class="bi bi-cash-coin me-1"></i>Bayar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">🎉 Semua siswa sudah lunas!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="belum-lunas-kosong" class="text-center text-muted py-4" style="display:none;">
                    <div style="font-size:1.6rem;">🔍</div>
                    Tidak ada siswa yang cocok dengan pencarian.
                </div>
            </div>
            <nav class="mt-3" id="paginasi-belum-lunas"></nav>
        </div>
    </div>
</main>

<style>
    .pg-btn {
        border: none; background: #fff; color: #9d174d;
        padding: 5px 12px; border-radius: 8px; font-size: .82rem; font-weight: 600;
        box-shadow: 0 1px 2px rgba(0,0,0,.06);
    }
    .pg-btn.aktif { background: #db2777; color: #fff; }
    .pg-btn:disabled { opacity: .4; cursor: default; }
</style>

<script>
    // ================== TAMBAHAN: jam berjalan real-time ==================
    const hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];
    const bulanIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function perbaruiJamAdmin() {
        const sekarang = new Date();
        const jam   = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');

        const elJam = document.getElementById('jamSekarangAdmin');
        if (elJam) elJam.textContent = jam + ':' + menit + ':' + detik;

        const elTanggal = document.getElementById('tanggalSekarangAdmin');
        if (elTanggal) {
            elTanggal.textContent = hariIndo[sekarang.getDay()] + ', ' + sekarang.getDate() + ' ' + bulanIndo[sekarang.getMonth()] + ' ' + sekarang.getFullYear();
        }
    }
    perbaruiJamAdmin();
    setInterval(perbaruiJamAdmin, 1000);

    // ================== Live filter + pagination "Siswa Belum Lunas" (tanpa reload halaman) ==================
    (function () {
        var input        = document.getElementById('cari-belum-lunas');
        var semuaBaris    = Array.from(document.querySelectorAll('#tabel-belum-lunas tbody tr[data-cari]'));
        var pesanKosong   = document.getElementById('belum-lunas-kosong');
        var badgeJumlah   = document.getElementById('badge-jumlah-lunas');
        var kontainerPage = document.getElementById('paginasi-belum-lunas');
        if (!input || semuaBaris.length === 0) return;

        var PER_HALAMAN = 10;
        var halamanAktif = 1;

        function ambilYangCocok() {
            var kata = input.value.trim().toLowerCase();
            return semuaBaris.filter(function (tr) {
                return kata === '' || tr.dataset.cari.indexOf(kata) !== -1;
            });
        }

        function render() {
            var cocok = ambilYangCocok();
            var totalHalaman = Math.max(1, Math.ceil(cocok.length / PER_HALAMAN));
            if (halamanAktif > totalHalaman) halamanAktif = totalHalaman;
            if (halamanAktif < 1) halamanAktif = 1;

            var mulai = (halamanAktif - 1) * PER_HALAMAN;
            var akhir  = mulai + PER_HALAMAN;

            semuaBaris.forEach(function (tr) { tr.style.display = 'none'; });
            cocok.slice(mulai, akhir).forEach(function (tr, i) {
                tr.style.display = '';
                var selNo = tr.querySelector('.kolom-no');
                if (selNo) selNo.textContent = mulai + i + 1;
            });

            if (badgeJumlah) badgeJumlah.textContent = cocok.length;
            pesanKosong.style.display = (cocok.length === 0) ? 'block' : 'none';

            kontainerPage.innerHTML = '';
            if (totalHalaman > 1) {
                var ul = document.createElement('ul');
                ul.className = 'pagination pagination-sm justify-content-center mb-0';

                function buatTombol(label, halaman, nonaktif, aktif) {
                    var li = document.createElement('li');
                    li.className = 'page-item';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pg-btn' + (aktif ? ' aktif' : '');
                    btn.textContent = label;
                    btn.disabled = !!nonaktif;
                    btn.addEventListener('click', function () {
                        halamanAktif = halaman;
                        render();
                    });
                    li.appendChild(btn);
                    ul.appendChild(li);
                }

                buatTombol('«', halamanAktif - 1, halamanAktif <= 1, false);
                for (var i = 1; i <= totalHalaman; i++) {
                    buatTombol(i, i, false, i === halamanAktif);
                }
                buatTombol('»', halamanAktif + 1, halamanAktif >= totalHalaman, false);

                kontainerPage.appendChild(ul);
            }
        }

        input.addEventListener('input', function () {
            halamanAktif = 1;
            render();
        });

        render();
    })();
</script>

<?php include '../components/footer.php'; ?>