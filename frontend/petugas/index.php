<?php
session_start();
// Cek apakah sudah login dan levelnya petugas
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Statistik ringkas, sama seperti dashboard admin
$total_siswa   = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM siswa"))['jml'] ?? 0);
$total_petugas = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM petugas"))['jml'] ?? 0);
$total_kelas   = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM kelas"))['jml'] ?? 0);

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
    // Kelas 10 -> 1 tahun ajaran, 11 -> 2 tahun, 12 -> 3 tahun (sama kayak detail_history.php)
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

// Catatan: pencarian sekarang LIVE di browser (JavaScript), bukan reload halaman lagi.
// Jadi semua siswa belum lunas dikirim sekaligus ke tabel, lalu difilter/disembunyikan
// pakai JS pas user ngetik di kolom pencarian (mirip halaman History Status Siswa di admin).
$total_belum_lunas  = count($daftar_belum_lunas_semua);
$daftar_belum_lunas = $daftar_belum_lunas_semua;

function warnaProgressFE($persen)
{
    if ($persen >= 60) return '#65a30d';
    if ($persen >= 30) return '#f59e0b';
    return '#f43f5e';
}

// Link "Bayar Sekarang" -> mengarah ke transaksi.php (halaman entri pembayaran petugas yang sebenarnya)
function link_bayar_petugas_frontend($row) {
    $jurusan_kode = trim(preg_replace('/\s*\d+$/', '', $row['jurusan']));
    $rombel_no = '';
    if (preg_match('/(\d+)\s*$/', $row['jurusan'], $m)) $rombel_no = $m[1];
    return 'transaksi.php?tingkat=' . urlencode($row['tingkat'])
        . '&jurusan=' . urlencode($jurusan_kode)
        . '&rombel=' . urlencode($rombel_no)
        . '&nisn=' . urlencode($row['nisn'])
        . '&f=1#bayar';
}

$pageTitle   = 'Dashboard Petugas - Aplikasi Pembayaran SPP';
$currentPage = 'index.php';
include __DIR__ . '/components/header.php';
include __DIR__ . '/components/sidebar.php';
?>

<style>
    /* ================== widget jam & awan ================== */
    .widget-jam {
        position: relative;
        overflow: hidden;
        min-height: 200px;
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
    .widget-jam .awan-1 { top: 12px;  left: -10px; font-size: 2.8rem; animation: mengambang 9s ease-in-out infinite; }
    .widget-jam .awan-2 { top: 54px;  right: -6px; font-size: 2rem;   animation: mengambang 7s ease-in-out infinite reverse; }
    .widget-jam .awan-3 { bottom: 14px; left: 22%;  font-size: 1.4rem; animation: mengambang 11s ease-in-out infinite; }

    @keyframes mengambang {
        0%, 100% { transform: translate(0, 0); }
        50%      { transform: translate(10px, -6px); }
    }

    .widget-jam .jam-besar {
        font-size: 2.3rem;
        font-weight: 800;
        letter-spacing: 1px;
        color: #fff;
        line-height: 1.1;
    }
    .widget-jam .tanggal-kecil {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.85rem;
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

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold" style="color: #db2777;">Dashboard Petugas</h1>
        <p class="text-muted mb-0">Selamat datang, <b><?= htmlspecialchars($_SESSION['nama_petugas']); ?></b></p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge p-2 fs-6" style="background-color: #db2777;"><i class="bi bi-calendar-event me-1"></i> <?= date('d M Y'); ?></span>
    </div>
</div>

<!-- Kartu Statistik (3 kolom) -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-uppercase text-muted small fw-semibold">Total Siswa</div>
                    <div class="fs-3 fw-bold"><?= $total_siswa; ?></div>
                </div>
                <div class="d-flex align-items-center justify-content-center rounded-3" style="width:56px; height:56px; background-color:#fce7f3;">
                    <i class="bi bi-people" style="font-size:1.4rem; color:#db2777;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-uppercase text-muted small fw-semibold">Petugas / Admin</div>
                    <div class="fs-3 fw-bold"><?= $total_petugas; ?></div>
                </div>
                <div class="d-flex align-items-center justify-content-center rounded-3" style="width:56px; height:56px; background-color:#ede9fe;">
                    <i class="bi bi-person-badge" style="font-size:1.4rem; color:#7c3aed;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-uppercase text-muted small fw-semibold">Data Kelas</div>
                    <div class="fs-3 fw-bold"><?= $total_kelas; ?></div>
                </div>
                <div class="d-flex align-items-center justify-content-center rounded-3" style="width:56px; height:56px; background-color:#dbeafe;">
                    <i class="bi bi-building" style="font-size:1.4rem; color:#2563eb;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card Utama Petugas + Widget Jam & Awan berdampingan -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-journal-check me-2"></i>Menu Utama Petugas Kasir</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Sebagai petugas, tugas utama kamu adalah melayani pembayaran SPP siswa dan melihat riwayat status pembayaran siswa.</p>
                <div class="alert mb-0" style="background-color: #fdf2f8; color: #9d174d;" role="alert">
                    <i class="bi bi-shield-lock me-2"></i><b>Catatan:</b> Pastikan untuk selalu mengecek NISN siswa dengan teliti sebelum memproses transaksi pembayaran SPP.
                </div>
            </div>
        </div>
    </div>

    <!-- ================== Widget Jam & Awan ================== -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 widget-jam">
            <div class="awan-area">
                <i class="bi bi-cloud-fill awan awan-1"></i>
                <i class="bi bi-cloud-fill awan awan-2"></i>
                <i class="bi bi-cloud-fill awan awan-3"></i>
            </div>
            <div class="card-body d-flex flex-column justify-content-center" style="position: relative; z-index: 2;">
                <div class="small fw-bold text-uppercase mb-2" style="color: rgba(255,255,255,0.85);">
                    <i class="bi bi-clock-history me-1"></i> Waktu Sekarang
                </div>
                <div class="jam-besar" id="jamSekarangPetugasFE">--:--:--</div>
                <div class="tanggal-kecil mt-1" id="tanggalSekarangPetugasFE">-</div>
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
                            $warna = warnaProgressFE($row['persen']);
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
                                    <a href="<?= link_bayar_petugas_frontend($row); ?>" class="btn btn-sm text-white" style="background-color:#db2777;">
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

            // Sembunyikan semua dulu, lalu tampilkan cuma yang cocok & di halaman aktif
            semuaBaris.forEach(function (tr) { tr.style.display = 'none'; });
            cocok.slice(mulai, akhir).forEach(function (tr, i) {
                tr.style.display = '';
                var selNo = tr.querySelector('.kolom-no');
                if (selNo) selNo.textContent = mulai + i + 1;
            });

            if (badgeJumlah) badgeJumlah.textContent = cocok.length;
            pesanKosong.style.display = (cocok.length === 0) ? 'block' : 'none';

            // Render tombol halaman
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
            halamanAktif = 1; // reset ke halaman 1 tiap kali kata kunci berubah
            render();
        });

        render(); // tampilan awal
    })();
</script>

<script>
    // ================== jam berjalan real-time ==================
    const hariIndoFE = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];
    const bulanIndoFE = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function perbaruiJamPetugasFE() {
        const sekarang = new Date();
        const jam   = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');

        const elJam = document.getElementById('jamSekarangPetugasFE');
        if (elJam) elJam.textContent = jam + ':' + menit + ':' + detik;

        const elTanggal = document.getElementById('tanggalSekarangPetugasFE');
        if (elTanggal) {
            elTanggal.textContent = hariIndoFE[sekarang.getDay()] + ', ' + sekarang.getDate() + ' ' + bulanIndoFE[sekarang.getMonth()] + ' ' + sekarang.getFullYear();
        }
    }
    perbaruiJamPetugasFE();
    setInterval(perbaruiJamPetugasFE, 1000);
</script>

<?php include __DIR__ . '/components/footer.php'; ?>