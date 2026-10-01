<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

/* ============================================================
   ENTRI PEMBAYARAN SPP (per bulan, TANPA cicilan)
   Alur: Tingkat -> Jurusan -> Rombel -> Siswa -> Centang bulan.

   ATURAN TAHUN AJARAN (BARU):
   - Setiap siswa hanya boleh bayar SPP untuk tahun ajaran dari
     tahun dia masuk (tahun_masuk) sampai 2 tahun ajaran setelahnya
     (tahun_masuk s.d. tahun_masuk+2). Ini dicek dua kali: saat
     menampilkan pilihan bulan (supaya tidak salah pilih tahun),
     dan sekali lagi saat menyimpan (supaya tidak bisa ditembus).

   ATURAN BULAN (sesuai permintaan sebelumnya):
   - Bulan yang SUDAH LUNAS  -> ditampilkan hijau, tidak bisa dicentang lagi.
   - Bulan yang BELUM LUNAS  -> selalu bisa dicentang (tunggakan, berjalan,
     atau bayar di muka).
   - Petugas boleh centang BEBERAPA bulan sekaligus dan bayar dalam SATU
     transaksi/kuitansi. Nominal per bulan selalu ambil dari tabel spp.
   ============================================================ */

$DAFTAR_JURUSAN = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];
$DAFTAR_BULAN = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$TAHUN_INI = (int) date('Y');
$BULAN_INI = (int) date('n');

// Pastikan ada id_petugas untuk dicatat sebagai penerima pembayaran
if (!isset($_SESSION['id_petugas'])) {
    $q_petugas = mysqli_query($koneksi, "SELECT id_petugas FROM petugas LIMIT 1");
    $d_petugas = mysqli_fetch_assoc($q_petugas);
    $_SESSION['id_petugas'] = $d_petugas['id_petugas'] ?? 1;
}

// ------------------------------------------------------------
// PROSES SIMPAN PEMBAYARAN (bisa lebih dari satu bulan sekaligus)
// ------------------------------------------------------------
if (isset($_POST['bayar'])) {
    $nisn  = mysqli_real_escape_string($koneksi, $_POST['nisn']);
    $tahun = (int) $_POST['tahun'];
    $daftar_bulan_dipilih = isset($_POST['bulan']) && is_array($_POST['bulan']) ? $_POST['bulan'] : [];

    if (count($daftar_bulan_dipilih) === 0) {
        echo "<script>alert('Pilih minimal 1 bulan yang mau dibayar.'); history.back();</script>";
        exit();
    }

    // Cek siswa ada
    $q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn = '$nisn'");
    $siswa_bayar = mysqli_fetch_assoc($q_siswa);
    if (!$siswa_bayar) {
        echo "<script>alert('Data siswa tidak ditemukan.'); history.back();</script>";
        exit();
    }

    // === VALIDASI TAHUN AJARAN (WAJIB, tidak bisa ditembus lewat POST manual) ===
    $tahun_masuk_siswa = (int) $siswa_bayar['tahun_masuk'];
    $tahun_maks_siswa  = $tahun_masuk_siswa + 2;
    if ($tahun < $tahun_masuk_siswa || $tahun > $tahun_maks_siswa) {
        $pesan = "Gagal! Tahun ajaran $tahun/" . ($tahun + 1) . " bukan tahun ajaran yang valid untuk siswa ini. "
                . "Siswa ini hanya boleh bayar SPP untuk tahun ajaran $tahun_masuk_siswa/" . ($tahun_masuk_siswa + 1)
                . " sampai $tahun_maks_siswa/" . ($tahun_maks_siswa + 1) . ".";
        echo "<script>alert('" . addslashes($pesan) . "'); history.back();</script>";
        exit();
    }

    // Tarif SPP tahun yang dibayar
    $q_spp = mysqli_query($koneksi, "SELECT id_spp, nominal FROM spp WHERE tahun = '$tahun' LIMIT 1");
    $data_spp = mysqli_fetch_assoc($q_spp);
    if (!$data_spp) {
        echo "<script>alert('Tarif SPP tahun $tahun belum ada. Tambahkan dulu di menu Data SPP.'); history.back();</script>";
        exit();
    }
    $id_spp  = (int) $data_spp['id_spp'];
    $nominal = (int) $data_spp['nominal'];

    // Bulan yang sudah lunas di tahun ini -> dipakai buat menyaring, jaga-jaga dobel
    $sudah_lunas_cek = [];
    $q_cek_lunas = mysqli_query($koneksi, "SELECT bulan_dibayar FROM pembayaran WHERE nisn = '$nisn' AND tahun_dibayar = '$tahun'");
    while ($cl = mysqli_fetch_assoc($q_cek_lunas)) {
        $sudah_lunas_cek[] = $cl['bulan_dibayar'];
    }

    $id_petugas = (int) $_SESSION['id_petugas'];
    $tgl_bayar  = date('Y-m-d');
    $id_baru_list = [];
    $bulan_dilewati = [];

       // ---------------------------------------------------------------
    // TRANSAKSI DATABASE (COMMIT & ROLLBACK)
    //   begin_transaction : mulai menahan semua INSERT (belum permanen)
    //   commit            : semua berhasil -> simpan permanen
    //   rollback          : ada yang gagal -> batalkan SEMUA
    // ---------------------------------------------------------------
    try {
        mysqli_begin_transaction($koneksi);

        foreach ($daftar_bulan_dipilih as $bulan_ke) {
            $bulan_ke = (int) $bulan_ke;
            if (!isset($DAFTAR_BULAN[$bulan_ke])) {
                continue;
            }
            $nama_bulan = $DAFTAR_BULAN[$bulan_ke];

            // Lewati kalau ternyata sudah lunas (misal double klik / sudah dibayar petugas lain)
            if (in_array($nama_bulan, $sudah_lunas_cek)) {
                $bulan_dilewati[] = $nama_bulan;
                continue;
            }

            $nama_bulan_esc = mysqli_real_escape_string($koneksi, $nama_bulan);
            $simpan = mysqli_query($koneksi, "INSERT INTO pembayaran
                (id_petugas, nisn, tgl_bayar, bulan_dibayar, tahun_dibayar, id_spp, jumlah_bayar)
                VALUES ('$id_petugas', '$nisn', '$tgl_bayar', '$nama_bulan_esc', '$tahun', '$id_spp', '$nominal')");

            if (!$simpan) {
                throw new Exception(mysqli_error($koneksi)); // loncat ke catch -> rollback
            }

            $id_baru_list[] = mysqli_insert_id($koneksi);
            $sudah_lunas_cek[] = $nama_bulan; // tandai supaya tidak dobel dalam loop yang sama
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

    // Satu bulan atau beberapa bulan -> semua diarahkan ke halaman detail gabungan
    $ids_teks = implode(',', $id_baru_list);
    header("Location: detail_pembayaran.php?ids=$ids_teks&baru=1");
    exit();
}

// ------------------------------------------------------------
// TAMPILAN: baca pilihan dari URL
// ------------------------------------------------------------
$tingkat = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$jurusan = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
$rombel  = isset($_GET['rombel']) ? $_GET['rombel'] : '';
$nisn_pilih = isset($_GET['nisn']) ? $_GET['nisn'] : '';
$tahun_pilih = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $TAHUN_INI;

if (!in_array($tingkat, ['10', '11', '12'])) $tingkat = '';
if (!in_array($jurusan, $DAFTAR_JURUSAN)) $jurusan = '';
if (!in_array($rombel, ['1', '2', '3'])) $rombel = '';

// Daftar tahun SPP yang tersedia (buat dropdown tahun)
$tahun_tersedia = [];
$q_tahun = mysqli_query($koneksi, "SELECT tahun, nominal FROM spp ORDER BY tahun ASC");
while ($t = mysqli_fetch_assoc($q_tahun)) {
    $tahun_tersedia[(int) $t['tahun']] = (int) $t['nominal'];
}
if (!isset($tahun_tersedia[$tahun_pilih]) && count($tahun_tersedia) > 0) {
    $tahun_pilih = isset($tahun_tersedia[$TAHUN_INI]) ? $TAHUN_INI : array_key_last($tahun_tersedia);
}
$nominal_tahun = $tahun_tersedia[$tahun_pilih] ?? 0;

// Link helper supaya pilihan sebelumnya ikut terbawa di URL
function linkStep($ubah = [])
{
    global $tingkat, $jurusan, $rombel, $nisn_pilih, $tahun_pilih;
    $p = [
        'tingkat' => $tingkat,
        'jurusan' => $jurusan,
        'rombel'  => $rombel,
        'nisn'    => $nisn_pilih,
        'tahun'   => $tahun_pilih,
    ];
    foreach ($ubah as $k => $v) {
        $p[$k] = $v;
    }
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return 'tambah_pembayaran.php?' . http_build_query($p);
}

include '../components/header.php';
include '../components/sidebar.php';
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold mb-1" style="color: #db2777;">Entri Pembayaran SPP</h1>
        <p class="text-muted mb-0 small">Pilih kelas siswa, lalu centang satu atau beberapa bulan yang mau dibayar sekaligus.</p>
    </div>

    <!-- LANGKAH 1: PILIH KELAS (Tingkat + Jurusan + Rombel jadi 1 card, 3 dropdown bersebelahan) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-bold mb-3" style="color:#9d174d;">Pilih Kelas Siswa</h6>
            <form method="GET" id="form-filter-kelas" class="row g-2">
                <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun_pilih); ?>">
                <input type="hidden" name="nisn" id="filter_nisn" value="<?= htmlspecialchars($nisn_pilih); ?>">

                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Tingkat</label>
                    <select name="tingkat" id="filter_tingkat" class="form-select form-select-sm"
                            onchange="document.getElementById('filter_nisn').value=''; this.form.submit();">
                        <option value="">-- Pilih --</option>
                        <?php foreach (['10', '11', '12'] as $t): ?>
                            <option value="<?= $t; ?>" <?= ($tingkat === $t) ? 'selected' : ''; ?>>Kelas <?= $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Jurusan</label>
                    <select name="jurusan" id="filter_jurusan" class="form-select form-select-sm"
                            <?= ($tingkat === '') ? 'disabled' : ''; ?>
                            onchange="document.getElementById('filter_nisn').value=''; this.form.submit();">
                        <option value="">-- Pilih --</option>
                        <?php foreach ($DAFTAR_JURUSAN as $j): ?>
                            <option value="<?= $j; ?>" <?= ($jurusan === $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Rombel</label>
                    <select name="rombel" id="filter_rombel" class="form-select form-select-sm"
                            <?= ($jurusan === '') ? 'disabled' : ''; ?>
                            onchange="document.getElementById('filter_nisn').value=''; this.form.submit();">
                        <option value="">-- Pilih --</option>
                        <?php foreach (['1', '2', '3'] as $r): ?>
                            <option value="<?= $r; ?>" <?= ($rombel === $r) ? 'selected' : ''; ?>>
                                Rombel <?= $r; ?><?= $jurusan !== '' ? ' (' . $jurusan . ' ' . $r . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 d-flex align-items-end">
                    <?php if ($tingkat !== '' || $jurusan !== '' || $rombel !== ''): ?>
                        <a href="tambah_pembayaran.php?tahun=<?= $tahun_pilih; ?>" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- LANGKAH 2: PILIH SISWA -->
    <?php if ($tingkat !== '' && $jurusan !== '' && $rombel !== ''): ?>
    <?php
    $kelas_teks = $jurusan . ' ' . $rombel;
    $kelas_esc = mysqli_real_escape_string($koneksi, $kelas_teks);
    $tkt_esc = mysqli_real_escape_string($koneksi, $tingkat);

    $q_siswa_kelas = mysqli_query($koneksi, "
        SELECT siswa.nisn, siswa.nis, siswa.nama, siswa.tahun_masuk
        FROM siswa
        JOIN kelas ON siswa.id_kelas = kelas.id_kelas
        WHERE kelas.tingkat = '$tkt_esc' AND kelas.jurusan = '$kelas_esc'
        ORDER BY siswa.nama ASC
    ");

    // Hitung berapa bulan yang sudah lunas di tahun terpilih, untuk tiap siswa
    $lunas_per_siswa = [];
    $q_lunas = mysqli_query($koneksi, "SELECT nisn, COUNT(*) AS jml FROM pembayaran
                                        WHERE tahun_dibayar = '$tahun_pilih' GROUP BY nisn");
    while ($l = mysqli_fetch_assoc($q_lunas)) {
        $lunas_per_siswa[$l['nisn']] = (int) $l['jml'];
    }
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0" style="color:#9d174d;">Siswa kelas <?= htmlspecialchars($tingkat . ' ' . $kelas_teks); ?></h6>
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="tingkat" value="<?= htmlspecialchars($tingkat); ?>">
                    <input type="hidden" name="jurusan" value="<?= htmlspecialchars($jurusan); ?>">
                    <input type="hidden" name="rombel" value="<?= htmlspecialchars($rombel); ?>">
                    <input type="hidden" name="nisn" value="<?= htmlspecialchars($nisn_pilih); ?>">
                    <label class="small text-muted mb-0">Tahun SPP</label>
                    <select name="tahun" class="form-select form-select-sm" style="width:110px;" onchange="this.form.submit()">
                        <?php foreach ($tahun_tersedia as $th => $nom): ?>
                            <option value="<?= $th; ?>" <?= ($th == $tahun_pilih) ? 'selected' : ''; ?>><?= $th; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <p class="small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Tahun SPP di atas berlaku untuk sebagian besar siswa di kelas ini, tapi tetap dicek ulang per siswa
                di langkah 3 sesuai tahun masuknya masing-masing (untuk siswa pindahan/tinggal kelas).
            </p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background-color: #fdf2f8; color: #db2777;">
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Angkatan</th>
                            <th>Lunas <?= $tahun_pilih; ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($q_siswa_kelas && mysqli_num_rows($q_siswa_kelas) > 0): $n = 1; ?>
                        <?php while ($s = mysqli_fetch_assoc($q_siswa_kelas)):
                            $jml_lunas = $lunas_per_siswa[$s['nisn']] ?? 0;
                            $aktif = ($nisn_pilih === $s['nisn']);
                        ?>
                            <tr class="<?= $aktif ? 'table-warning' : ''; ?>">
                                <td><?= $n++; ?></td>
                                <td><?= htmlspecialchars($s['nisn']); ?></td>
                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                <td class="small text-muted"><?= (int) $s['tahun_masuk']; ?>/<?= (int) $s['tahun_masuk'] + 1; ?></td>
                                <td>
                                    <span class="badge <?= ($jml_lunas >= 12) ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?= $jml_lunas; ?> / 12 bulan
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= htmlspecialchars(linkStep(['nisn' => $s['nisn']])); ?>#bulan"
                                       class="btn btn-sm <?= $aktif ? 'btn-secondary' : 'text-white'; ?>"
                                       style="<?= $aktif ? '' : 'background-color:#db2777;'; ?>">
                                        <?= $aktif ? 'Sedang dipilih' : 'Pilih siswa'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada siswa di kelas <?= htmlspecialchars($tingkat . ' ' . $kelas_teks); ?>.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- LANGKAH 3: CENTANG BULAN (bisa lebih dari satu) -->
    <?php if ($nisn_pilih !== ''): ?>
    <?php
    $nisn_esc = mysqli_real_escape_string($koneksi, $nisn_pilih);
    $q_detail = mysqli_query($koneksi, "
        SELECT siswa.*, kelas.tingkat, kelas.jurusan
        FROM siswa JOIN kelas ON siswa.id_kelas = kelas.id_kelas
        WHERE siswa.nisn = '$nisn_esc'
    ");
    $siswa = mysqli_fetch_assoc($q_detail);
    ?>
    <?php if ($siswa): ?>
    <?php
    // === Rentang tahun ajaran yang VALID buat siswa ini ===
    $tahun_masuk_siswa = (int) $siswa['tahun_masuk'];
    $tahun_maks_siswa  = $tahun_masuk_siswa + 2;
    $tahun_valid_untuk_siswa = ($tahun_pilih >= $tahun_masuk_siswa && $tahun_pilih <= $tahun_maks_siswa);

    // Daftar tahun yang boleh dipilih buat siswa ini = irisan antara tahun yang ada tarif SPP-nya
    // DAN tahun yang ada dalam rentang masuk s.d. lulus siswa ini.
    $tahun_pilihan_siswa = [];
    foreach ($tahun_tersedia as $th => $nom) {
        if ($th >= $tahun_masuk_siswa && $th <= $tahun_maks_siswa) {
            $tahun_pilihan_siswa[$th] = $nom;
        }
    }
    ?>
    <div class="card border-0 shadow-sm mb-3" id="bulan">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-1" style="color:#9d174d;">Centang bulan yang dibayar</h6>
                    <div class="small text-muted">
                        <strong><?= htmlspecialchars($siswa['nama']); ?></strong> &middot;
                        NISN <?= htmlspecialchars($siswa['nisn']); ?> &middot;
                        Kelas <?= htmlspecialchars($siswa['tingkat'] . ' ' . $siswa['jurusan']); ?>
                    </div>
                    <div class="small text-muted">
                        Angkatan <?= $tahun_masuk_siswa; ?>/<?= $tahun_masuk_siswa + 1; ?> &middot;
                        Tahun ajaran valid untuk SPP:
                        <strong><?= $tahun_masuk_siswa; ?>/<?= $tahun_masuk_siswa + 1; ?> s.d. <?= $tahun_maks_siswa; ?>/<?= $tahun_maks_siswa + 1; ?></strong>
                    </div>
                </div>
                <?php if ($tahun_valid_untuk_siswa): ?>
                <div class="text-end">
                    <div class="small text-muted">SPP per bulan (<?= $tahun_pilih; ?>)</div>
                    <div class="h5 fw-bold mb-0" style="color:#db2777;">Rp <?= number_format($nominal_tahun, 0, ',', '.'); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$tahun_valid_untuk_siswa): ?>
                <!-- Tahun yang dipilih di Langkah 2 TIDAK valid buat siswa ini -->
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Tahun ajaran <strong><?= $tahun_pilih; ?>/<?= $tahun_pilih + 1; ?></strong> bukan tahun ajaran yang valid
                    untuk <strong><?= htmlspecialchars($siswa['nama']); ?></strong>.
                    Siswa ini hanya boleh bayar SPP untuk tahun ajaran
                    <strong><?= $tahun_masuk_siswa; ?>/<?= $tahun_masuk_siswa + 1; ?></strong> sampai
                    <strong><?= $tahun_maks_siswa; ?>/<?= $tahun_maks_siswa + 1; ?></strong>.
                </div>

                <?php if (count($tahun_pilihan_siswa) > 0): ?>
                    <p class="small text-muted mb-2">Pilih tahun ajaran yang valid untuk siswa ini:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($tahun_pilihan_siswa as $th => $nom): ?>
                            <a href="<?= htmlspecialchars(linkStep(['tahun' => $th])); ?>#bulan" class="btn btn-sm text-white" style="background-color:#db2777;">
                                <?= $th; ?>/<?= $th + 1; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Belum ada tarif SPP yang cocok dengan tahun ajaran siswa ini (<?= $tahun_masuk_siswa; ?>-<?= $tahun_maks_siswa; ?>).
                        Tambahkan dulu lewat menu <strong>Data SPP</strong>.
                    </div>
                <?php endif; ?>

            <?php elseif ($nominal_tahun == 0): ?>
                <div class="alert alert-warning mb-0">
                    Tarif SPP tahun <?= $tahun_pilih; ?> belum diisi. Tambahkan dulu lewat menu <strong>Data SPP</strong>.
                </div>
            <?php else: ?>

            <?php
            // Ambil bulan yang sudah lunas di tahun terpilih (beserta id transaksinya)
            $sudah_lunas = [];
            $q_bayar = mysqli_query($koneksi, "SELECT id_pembayaran, bulan_dibayar FROM pembayaran
                                                WHERE nisn = '$nisn_esc' AND tahun_dibayar = '$tahun_pilih'");
            while ($b = mysqli_fetch_assoc($q_bayar)) {
                $sudah_lunas[$b['bulan_dibayar']] = (int) $b['id_pembayaran'];
            }
            $jml_lunas = count($sudah_lunas);
            ?>

            <div class="mb-3 small">
                <span class="badge bg-success">Lunas</span>
                <span class="badge bg-danger">Menunggak (bulan lewat, belum bayar)</span>
                <span class="badge" style="background-color:#db2777;">Belum bayar (bisa dicentang)</span>
                <span class="ms-2 text-muted"><?= $jml_lunas; ?> dari 12 bulan lunas di tahun <?= $tahun_pilih; ?></span>
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
                            <a href="detail_pembayaran.php?ids=<?= $sudah_lunas[$nama_bulan]; ?>"
                               class="btn btn-success w-100 text-start py-2">
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

    <div class="mt-4">
        <a href="pembayaran.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali ke Transaksi</a>
    </div>
</main>

<?php include '../components/footer.php'; ?>