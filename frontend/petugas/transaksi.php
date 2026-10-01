<?php
require_once __DIR__ . '/../keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$DAFTAR_JURUSAN = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];
$DAFTAR_BULAN = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$TOTAL_BULAN_WAJIB = 36; // 12 bulan x 3 tahun ajaran
$TAHUN_INI = (int) date('Y');
$BULAN_INI = (int) date('n');

// Ambil id_petugas berdasarkan username yang beneran login (bukan LIMIT 1 sembarang)
if (!isset($_SESSION['id_petugas'])) {
    $username_sess = mysqli_real_escape_string($koneksi, $_SESSION['username']);
    $q_petugas = mysqli_query($koneksi, "SELECT id_petugas FROM petugas WHERE username = '$username_sess' LIMIT 1");
    $d_petugas = mysqli_fetch_assoc($q_petugas);
    if (!$d_petugas) {
        session_destroy();
        echo "<script>alert('Akun petugas tidak ditemukan. Silakan login ulang.'); window.location='../../login.php';</script>";
        exit();
    }
    $_SESSION['id_petugas'] = $d_petugas['id_petugas'];
}

if (isset($_POST['bayar'])) {
    $nisn  = mysqli_real_escape_string($koneksi, $_POST['nisn'] ?? '');
    $tahun = (int) ($_POST['tahun'] ?? 0);
    $daftar_bulan_dipilih = isset($_POST['bulan']) && is_array($_POST['bulan']) ? $_POST['bulan'] : [];

    if (count($daftar_bulan_dipilih) === 0) {
        echo "<script>alert('Pilih minimal 1 bulan yang mau dibayar.'); history.back();</script>";
        exit();
    }

    $q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn = '$nisn'");
    $siswa_bayar = mysqli_fetch_assoc($q_siswa);
    if (!$siswa_bayar) {
        echo "<script>alert('Data siswa tidak ditemukan.'); history.back();</script>";
        exit();
    }

    // === VALIDASI TAHUN AJARAN (wajib, tidak bisa ditembus lewat POST manual) ===
    $tahun_masuk_siswa = (int) $siswa_bayar['tahun_masuk'];
    $tahun_maks_siswa  = $tahun_masuk_siswa + 2;
    if ($tahun < $tahun_masuk_siswa || $tahun > $tahun_maks_siswa) {
        $pesan = "Gagal! Tahun ajaran " . formatTA($tahun) . " bukan tahun ajaran yang valid untuk siswa ini. "
                . "Siswa ini hanya boleh bayar SPP untuk tahun ajaran " . formatTA($tahun_masuk_siswa)
                . " sampai " . formatTA($tahun_maks_siswa) . ".";
        echo "<script>alert('" . addslashes($pesan) . "'); history.back();</script>";
        exit();
    }

    $q_spp = mysqli_query($koneksi, "SELECT id_spp, nominal FROM spp WHERE tahun = '$tahun' LIMIT 1");
    $data_spp = mysqli_fetch_assoc($q_spp);
    if (!$data_spp) {
        $pesan = "Tarif SPP tahun ajaran " . formatTA($tahun) . " belum ada. Hubungi admin untuk menambahkannya di menu Data SPP.";
        echo "<script>alert('" . addslashes($pesan) . "'); history.back();</script>";
        exit();
    }
    $id_spp  = (int) $data_spp['id_spp'];
    $nominal = (int) $data_spp['nominal'];

    // Bulan yang sudah lunas di tahun ini -> jaga-jaga dobel input
    $sudah_lunas_cek = [];
    $q_cek_lunas = mysqli_query($koneksi, "SELECT bulan_dibayar FROM pembayaran WHERE nisn = '$nisn' AND tahun_dibayar = '$tahun'");
    while ($cl = mysqli_fetch_assoc($q_cek_lunas)) {
        $sudah_lunas_cek[] = $cl['bulan_dibayar'];
    }

    $id_petugas = (int) $_SESSION['id_petugas'];
    $tgl_bayar  = date('Y-m-d');
    $id_baru_list = [];

    // ---------------------------------------------------------------
    // TRANSAKSI DATABASE (COMMIT & ROLLBACK)
    //   begin_transaction : mulai menahan semua INSERT (belum permanen)
    //   commit            : semua berhasil -> simpan permanen
    //   rollback          : ada yang gagal -> batalkan SEMUA
    // ---------------------------------------------------------------
    try {
        mysqli_begin_transaction($koneksi);
        // Kunci baris siswa ini sampai commit/rollback, jadi klik ganda / dua request bersamaan
        // dipaksa antre. Setelah dapat giliran, baca ulang bulan yang sudah lunas.
        mysqli_query($koneksi, "SELECT nisn FROM siswa WHERE nisn = '$nisn' FOR UPDATE");
        $sudah_lunas_cek = [];
        $q_cek_ulang = mysqli_query($koneksi, "SELECT bulan_dibayar FROM pembayaran WHERE nisn = '$nisn' AND tahun_dibayar = '$tahun'");
        while ($cu = mysqli_fetch_assoc($q_cek_ulang)) {
            $sudah_lunas_cek[] = $cu['bulan_dibayar'];
        }

        foreach ($daftar_bulan_dipilih as $bulan_ke) {
            $bulan_ke = (int) $bulan_ke;
            if (!isset($DAFTAR_BULAN[$bulan_ke])) {
                continue;
            }
            $nama_bulan = $DAFTAR_BULAN[$bulan_ke];

            if (in_array($nama_bulan, $sudah_lunas_cek)) {
                continue; // sudah lunas sebelumnya, lewati
            }

            $nama_bulan_esc = mysqli_real_escape_string($koneksi, $nama_bulan);
            $simpan = mysqli_query($koneksi, "INSERT INTO pembayaran
                (id_petugas, nisn, tgl_bayar, bulan_dibayar, tahun_dibayar, id_spp, jumlah_bayar)
                VALUES ('$id_petugas', '$nisn', '$tgl_bayar', '$nama_bulan_esc', '$tahun', '$id_spp', '$nominal')");

            if (!$simpan) {
                throw new Exception(mysqli_error($koneksi)); // loncat ke catch -> rollback
            }

            $id_baru_list[] = mysqli_insert_id($koneksi);
            $sudah_lunas_cek[] = $nama_bulan;
        }

        if (count($id_baru_list) === 0) {
            mysqli_rollback($koneksi);
            echo "<script>alert('Semua bulan yang dipilih sudah lunas sebelumnya. Tidak ada yang disimpan.'); history.back();</script>";
            exit();
        }

        mysqli_commit($koneksi); // semua INSERT berhasil -> simpan permanen
    } catch (Throwable $e) {
        mysqli_rollback($koneksi); // ada yang gagal -> batalkan semuanya
        error_log('Gagal simpan pembayaran: ' . $e->getMessage());
        echo "<script>alert('Pembayaran gagal disimpan, tidak ada data yang tercatat. Silakan coba lagi.'); history.back();</script>";
        exit();
    }

    $ids_teks = implode(',', $id_baru_list);
    header("Location: detail_pembayaran.php?ids=$ids_teks&baru=1");
    exit();
}

$tingkat    = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$jurusan    = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
$rombel     = isset($_GET['rombel']) ? $_GET['rombel'] : '';
$nisn_pilih = isset($_GET['nisn']) ? $_GET['nisn'] : '';

// Penanda "tombol Terapkan sudah diklik minimal sekali" untuk kombinasi
// filter yang sedang aktif. Kalau belum ada (misal baru masuk halaman),
// tabel di bawah belum ditampilkan. Wajib sudah pilih Kelas dulu juga.
$sudah_terapkan = isset($_GET['f']) && $tingkat !== '';

if (!in_array($tingkat, ['10', '11', '12'])) $tingkat = ''; // belum pilih kelas = kosong, tidak default ke 10
if (!in_array($jurusan, $DAFTAR_JURUSAN)) $jurusan = '';
if (!in_array($rombel, ['1', '2', '3'])) $rombel = '';

function linkFilter($ubah = [])
{
    global $tingkat, $jurusan, $rombel;
    // 'f' => 1 disertakan supaya link (misal tombol "Bayar") tetap
    // menampilkan tabel, bukan balik ke kondisi kosong sebelum Terapkan.
    $p = ['tingkat' => $tingkat, 'jurusan' => $jurusan, 'rombel' => $rombel, 'f' => 1];
    foreach ($ubah as $k => $v) $p[$k] = $v;
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return count($p) ? 'transaksi.php?' . http_build_query($p) : 'transaksi.php';
}

$pageTitle   = 'Entri Transaksi Pembayaran - Petugas';
$currentPage = 'transaksi.php';
include __DIR__ . '/components/header.php';
include __DIR__ . '/components/sidebar.php';
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h3 fw-bold mb-1" style="color: #db2777;">Transaksi Pembayaran</h1>
    <p class="text-muted mb-0 small">Daftar siswa yang belum lunas SPP. Klik "Bayar" untuk langsung mencatat pembayaran.</p>
</div>

<?php if ($nisn_pilih === ''): ?>
<!-- Tab pilihan kelas (10/11/12) -->
<ul class="nav nav-pills mb-3 gap-2">
    <?php foreach (['10', '11', '12'] as $t): ?>
        <li class="nav-item">
            <a href="transaksi.php?tingkat=<?= $t; ?>"
               class="nav-link fw-semibold <?= ($tingkat === $t) ? 'text-white' : 'text-dark bg-white border'; ?>"
               style="<?= ($tingkat === $t) ? 'background-color:#db2777;' : ''; ?>">
                Kelas <?= $t; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Filter Jurusan & Rombel: tetap tampil, tapi terkunci sampai Kelas dipilih -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <?php if ($tingkat === ''): ?>
            <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Pilih salah satu <strong>Kelas</strong> di atas dulu untuk mengaktifkan filter Jurusan &amp; Rombel.</p>
        <?php endif; ?>
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tingkat" value="<?= htmlspecialchars($tingkat); ?>">
            <input type="hidden" name="f" value="1">

            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Jurusan</label>
                <select name="jurusan" id="select-jurusan" class="form-select form-select-sm" <?= $tingkat === '' ? 'disabled' : ''; ?>>
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($DAFTAR_JURUSAN as $j): ?>
                        <option value="<?= $j; ?>" <?= ($jurusan === $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Rombel</label>
                <select name="rombel" id="select-rombel" class="form-select form-select-sm" disabled>
                    <option value="">Semua Rombel</option>
                    <?php foreach (['1', '2', '3'] as $r): ?>
                        <option value="<?= $r; ?>" <?= ($rombel === $r) ? 'selected' : ''; ?>>Rombel <?= $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" id="btn-terapkan" class="btn btn-sm text-white flex-fill" style="background-color:#db2777;" disabled>Terapkan</button>
                <?php if ($sudah_terapkan && ($jurusan !== '' || $rombel !== '')): ?>
                    <a href="transaksi.php?tingkat=<?= urlencode($tingkat); ?>&f=1" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var kelasDipilih = <?= $tingkat !== '' ? 'true' : 'false'; ?>;
        var selectJurusan = document.getElementById('select-jurusan');
        var selectRombel  = document.getElementById('select-rombel');
        var btnTerapkan   = document.getElementById('btn-terapkan');
        if (!selectJurusan) return; // form kelas/jurusan lagi disembunyikan (1 siswa sedang diproses)

        function perbaruiKunci() {
            if (!kelasDipilih) {
                selectRombel.disabled = true;
                btnTerapkan.disabled  = true;
                return;
            }
            var sudahIsiJurusan = selectJurusan.value !== '';
            selectRombel.disabled = !sudahIsiJurusan;
            btnTerapkan.disabled  = !sudahIsiJurusan;
            if (!sudahIsiJurusan) selectRombel.value = '';
        }

        selectJurusan.addEventListener('change', perbaruiKunci);
        perbaruiKunci();
    })();
</script>
<?php endif; // nisn_pilih === '' ?>

<?php if (!$sudah_terapkan): ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-funnel fs-3 d-block mb-2"></i>
            <?php if ($tingkat === ''): ?>
                Pilih salah satu <strong>Kelas 10/11/12</strong> di atas dulu untuk mulai.
            <?php else: ?>
            Pilih Jurusan / Rombel (atau biarkan "Semua" kalau mau lihat semua), lalu klik <strong>Terapkan</strong> untuk menampilkan daftar siswa.
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

<?php
$kondisi = ["kelas.tingkat = '" . mysqli_real_escape_string($koneksi, $tingkat) . "'"];
if ($jurusan !== '') {
    $jrs = mysqli_real_escape_string($koneksi, $jurusan);
    if ($rombel !== '') {
        $rmb = mysqli_real_escape_string($koneksi, $rombel);
        $kondisi[] = "kelas.jurusan = '$jrs $rmb'";
    } else {
        $kondisi[] = "kelas.jurusan LIKE '$jrs%'";
    }
}
$where = "WHERE " . implode(" AND ", $kondisi);

$q_siswa = mysqli_query($koneksi, "
    SELECT siswa.nisn, siswa.nama, siswa.tahun_masuk, kelas.tingkat, kelas.jurusan
    FROM siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    $where
    ORDER BY siswa.nama ASC
");

$lunas_per_siswa = [];
$q_lunas = mysqli_query($koneksi, "SELECT nisn, COUNT(*) AS jml FROM pembayaran GROUP BY nisn");
while ($l = mysqli_fetch_assoc($q_lunas)) {
    $lunas_per_siswa[$l['nisn']] = (int) $l['jml'];
}

$daftar_belum_lunas = [];
if ($q_siswa) {
    while ($s = mysqli_fetch_assoc($q_siswa)) {
        $jml_lunas = $lunas_per_siswa[$s['nisn']] ?? 0;
        if ($jml_lunas < $TOTAL_BULAN_WAJIB) {
            $s['jml_lunas'] = $jml_lunas;
            $daftar_belum_lunas[] = $s;
        }
    }
}

if ($nisn_pilih !== '') {
    $daftar_belum_lunas = array_values(array_filter($daftar_belum_lunas, function ($s) use ($nisn_pilih) {
        return $s['nisn'] === $nisn_pilih;
    }));
}
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <?php if ($nisn_pilih !== ''): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="small text-muted mb-0">
                    Sedang memproses pembayaran <strong>1 siswa</strong>. Siswa lain disembunyikan dulu supaya tidak keganggu.
                </p>
                <a href="<?= htmlspecialchars(linkFilter(['nisn' => ''])); ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Batal / pilih siswa lain
                </a>
            </div>
        <?php else: ?>
        <p class="small text-muted mb-3">
            <?= count($daftar_belum_lunas); ?> siswa belum lunas
            <?= ($jurusan !== '' || $rombel !== '') ? ' di kelas ' . htmlspecialchars(trim($tingkat . ' ' . $jurusan . ' ' . $rombel)) : ' di kelas ' . htmlspecialchars($tingkat); ?>.
        </p>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background-color: #fdf2f8; color: #db2777;">
                    <tr>
                        <th>No</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Status Lunas</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($daftar_belum_lunas) > 0): $no = 1; ?>
                    <?php foreach ($daftar_belum_lunas as $s):
                        $jurusan_kode = trim(preg_replace('/\s*\d+$/', '', $s['jurusan']));
                        $rombel_no = '';
                        if (preg_match('/(\d+)\s*$/', $s['jurusan'], $m)) $rombel_no = $m[1];
                        $aktif = ($nisn_pilih === $s['nisn']);
                    ?>
                        <tr class="<?= $aktif ? 'table-warning' : ''; ?>">
                            <td><?= $no++; ?></td>
                            <td><strong><?= htmlspecialchars($s['nisn']); ?></strong></td>
                            <td><?= htmlspecialchars($s['nama']); ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars(trim($s['tingkat'] . ' ' . $jurusan_kode . ' ' . $rombel_no)); ?></span></td>
                            <td>
                                <span class="badge" style="background-color:#db2777;">
                                    <?= $s['jml_lunas']; ?> / <?= $TOTAL_BULAN_WAJIB; ?> bulan
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= htmlspecialchars(linkFilter(['nisn' => $s['nisn']])); ?>#bayar"
                                   class="btn btn-sm <?= $aktif ? 'btn-secondary' : 'text-white'; ?>"
                                   style="<?= $aktif ? '' : 'background-color:#9d174d;'; ?>">
                                    <?= $aktif ? 'Sedang dipilih' : 'Bayar'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">🎉 Semua siswa di kelas ini sudah lunas. Riwayatnya bisa dicek di <strong>History Status Siswa</strong>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if ($nisn_pilih !== ''):
    $nisn_esc = mysqli_real_escape_string($koneksi, $nisn_pilih);
    $q_detail = mysqli_query($koneksi, "
        SELECT siswa.*, kelas.tingkat, kelas.jurusan
        FROM siswa JOIN kelas ON siswa.id_kelas = kelas.id_kelas
        WHERE siswa.nisn = '$nisn_esc'
    ");
    $siswa = mysqli_fetch_assoc($q_detail);

    if ($siswa):
        $tahun_masuk_siswa = (int) $siswa['tahun_masuk'];
        $tahun_maks_siswa  = $tahun_masuk_siswa + 2;

        $tahun_tersedia = [];
        $q_tahun = mysqli_query($koneksi, "SELECT tahun, nominal FROM spp WHERE tahun BETWEEN '$tahun_masuk_siswa' AND '$tahun_maks_siswa' ORDER BY tahun ASC");
        while ($t = mysqli_fetch_assoc($q_tahun)) {
            $tahun_tersedia[(int) $t['tahun']] = (int) $t['nominal'];
        }

        $tahun_pilih = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;
        if (!isset($tahun_tersedia[$tahun_pilih])) {
            $tahun_pilih = 0;
            foreach ($tahun_tersedia as $th => $nom) {
                $q_cek = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM pembayaran WHERE nisn='$nisn_esc' AND tahun_dibayar='$th'");
                $jml = (int) mysqli_fetch_assoc($q_cek)['jml'];
                if ($jml < 12) { $tahun_pilih = $th; break; }
            }
            if ($tahun_pilih === 0 && count($tahun_tersedia) > 0) $tahun_pilih = array_key_first($tahun_tersedia);
        }
        $nominal_tahun = $tahun_tersedia[$tahun_pilih] ?? 0;
        ?>
        <div class="card border-0 shadow-sm mb-3" id="bayar">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1" style="color:#9d174d;">Catat Pembayaran</h6>
                        <div class="small text-muted">
                            <strong><?= htmlspecialchars($siswa['nama']); ?></strong> &middot;
                            NISN <?= htmlspecialchars($siswa['nisn']); ?> &middot;
                            Kelas <?= htmlspecialchars($siswa['tingkat'] . ' ' . $siswa['jurusan']); ?>
                        </div>
                        <div class="small text-muted">
                            Tahun ajaran valid: <strong><?= formatTA($tahun_masuk_siswa); ?> s.d. <?= formatTA($tahun_maks_siswa); ?></strong>
                        </div>
                    </div>
                    <?php if ($nominal_tahun > 0): ?>
                    <div class="text-end">
                        <div class="small text-muted">SPP per bulan (<?= formatTA($tahun_pilih); ?>)</div>
                        <div class="h5 fw-bold mb-0" style="color:#db2777;">Rp <?= number_format($nominal_tahun, 0, ',', '.'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (count($tahun_tersedia) === 0): ?>
                    <div class="alert alert-warning mb-0">
                        Belum ada tarif SPP yang cocok untuk tahun ajaran siswa ini (<?= formatTA($tahun_masuk_siswa); ?> - <?= formatTA($tahun_maks_siswa); ?>). Hubungi admin untuk menambahkannya lewat menu Data SPP.
                    </div>
                <?php else: ?>
                    <form method="GET" class="d-flex gap-2 align-items-center mb-3">
                        <input type="hidden" name="tingkat" value="<?= htmlspecialchars($tingkat); ?>">
                        <input type="hidden" name="jurusan" value="<?= htmlspecialchars($jurusan); ?>">
                        <input type="hidden" name="rombel" value="<?= htmlspecialchars($rombel); ?>">
                        <input type="hidden" name="nisn" value="<?= htmlspecialchars($nisn_pilih); ?>">
                        <input type="hidden" name="f" value="1">
                        <label class="small text-muted mb-0">Tahun Ajaran</label>
                        <select name="tahun" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
                            <?php foreach ($tahun_tersedia as $th => $nom): ?>
                                <option value="<?= $th; ?>" <?= ($th == $tahun_pilih) ? 'selected' : ''; ?>><?= formatTA($th); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php
                    $sudah_lunas = [];
                    $q_bayar = mysqli_query($koneksi, "SELECT id_pembayaran, bulan_dibayar FROM pembayaran WHERE nisn = '$nisn_esc' AND tahun_dibayar = '$tahun_pilih'");
                    while ($b = mysqli_fetch_assoc($q_bayar)) $sudah_lunas[$b['bulan_dibayar']] = (int) $b['id_pembayaran'];
                    $jml_lunas_tahun = count($sudah_lunas);
                    ?>
                    <div class="mb-3 small">
                        <span class="badge bg-success">Lunas</span>
                        <span class="badge bg-danger">Menunggak</span>
                        <span class="badge" style="background-color:#db2777;">Belum bayar (bisa dicentang)</span>
                        <span class="ms-2 text-muted"><?= $jml_lunas_tahun; ?> dari 12 bulan lunas di tahun ajaran <?= formatTA($tahun_pilih); ?></span>
                    </div>

                    <form method="POST" id="form-bayar">
                        <input type="hidden" name="nisn" value="<?= htmlspecialchars($siswa['nisn']); ?>">
                        <input type="hidden" name="tahun" value="<?= $tahun_pilih; ?>">
                        <div class="row g-2">
                            <?php foreach ($DAFTAR_BULAN as $ke => $nama_bulan):
                                $lunas = isset($sudah_lunas[$nama_bulan]);
                                $sudah_lewat = ($tahun_pilih < $TAHUN_INI) || ($tahun_pilih == $TAHUN_INI && $ke < $BULAN_INI);
                                $berjalan = ($tahun_pilih == $TAHUN_INI && $ke == $BULAN_INI);
                            ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                <?php if ($lunas): ?>
                                    <a href="detail_pembayaran.php?ids=<?= $sudah_lunas[$nama_bulan]; ?>" class="btn btn-success w-100 text-start py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i> <?= $nama_bulan; ?>
                                        <div class="small fw-normal opacity-75">Lunas &middot; lihat bukti</div>
                                    </a>
                                <?php else: ?>
                                    <label class="btn w-100 text-start py-2 text-white pilihan-bulan"
                                           style="background-color: <?= $sudah_lewat ? '#dc3545' : '#db2777'; ?>;"
                                           for="cek_bulan_<?= $ke; ?>">
                                        <input type="checkbox" class="form-check-input me-1 cek-bulan" id="cek_bulan_<?= $ke; ?>"
                                               name="bulan[]" value="<?= $ke; ?>" data-nominal="<?= $nominal_tahun; ?>"
                                               style="vertical-align: -2px;">
                                        <?= $nama_bulan; ?>
                                        <div class="small fw-normal opacity-75 ms-4">
                                            <?= $sudah_lewat ? 'Menunggak' : ($berjalan ? 'Bulan berjalan' : 'Bayar di muka'); ?>
                                        </div>
                                    </label>
                                <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 p-3 rounded-3" style="background-color:#fdf2f8;">
                            <div class="small">
                                <span id="jumlah-terpilih">0</span> bulan dipilih
                                &middot; Total: <strong id="total-bayar" style="color:#db2777;">Rp 0</strong>
                            </div>
                            <button type="submit" name="bayar" value="1" id="btn-submit-bayar"
                                    class="btn text-white px-4" style="background-color:#9d174d;" disabled
                                    onclick="return confirm(document.getElementById('jumlah-terpilih').textContent + ' bulan akan dibayarkan sekaligus untuk <?= htmlspecialchars(addslashes($siswa['nama'])); ?>. Lanjutkan?');">
                                <i class="bi bi-cash-coin me-1"></i> Bayar Bulan Terpilih
                            </button>
                        </div>
                    </form>

                    <script>
                        (function () {
                            var checkboxes = document.querySelectorAll('.cek-bulan');
                            var labelJumlah = document.getElementById('jumlah-terpilih');
                            var labelTotal = document.getElementById('total-bayar');
                            var tombolSubmit = document.getElementById('btn-submit-bayar');

                            function formatRupiah(angka) {
                                return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            }

                            function hitungUlang() {
                                var jumlah = 0, total = 0;
                                checkboxes.forEach(function (cb) {
                                    if (cb.checked) {
                                        jumlah++;
                                        total += parseInt(cb.dataset.nominal, 10);
                                    }
                                    var kartu = cb.closest('.pilihan-bulan');
                                    if (kartu) {
                                        kartu.classList.toggle('border', cb.checked);
                                        kartu.classList.toggle('border-dark', cb.checked);
                                        kartu.style.opacity = cb.checked ? '1' : '0.85';
                                    }
                                });
                                labelJumlah.textContent = jumlah;
                                labelTotal.textContent = formatRupiah(total);
                                tombolSubmit.disabled = (jumlah === 0);
                            }

                            checkboxes.forEach(function (cb) { cb.addEventListener('change', hitungUlang); });
                            hitungUlang();
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php endif; // sudah_terapkan ?>

<?php include __DIR__ . '/components/footer.php'; ?>