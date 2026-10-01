<?php
require_once __DIR__ . '/keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}

// Panggil koneksi database
include '../../koneksi.php'; 

include '../components/header.php';
include '../components/sidebar.php';

// Daftar jurusan yang tersedia (sinkron dengan pilihan di form Tambah Siswa)
$daftar_jurusan = ['PPLG', 'AKL', 'APHP', 'APAT', 'TKR', 'TSM'];

$tingkat_aktif = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';

// Nilai MENTAH dari dropdown (dipakai buat nentuin opsi mana yang "selected"
// & buat deteksi apakah usernya SUDAH benar-benar memilih sesuatu).
// "SEMUA" = user sengaja pilih "Semua Jurusan/Rombel", beda dari null/belum disentuh sama sekali.
$jurusan_raw = isset($_GET['jurusan']) ? $_GET['jurusan'] : null;
$rombel_raw  = isset($_GET['rombel']) ? $_GET['rombel'] : null;

// "Semua Jurusan" cuma valid dipilih kalau lagi di halaman "Semua Siswa" (tingkat belum dikunci).
$boleh_semua_jurusan = ($tingkat_aktif === '' || !in_array($tingkat_aktif, ['10', '11', '12']));
$jurusan_dipilih = ($jurusan_raw !== null) && (($boleh_semua_jurusan && $jurusan_raw === 'SEMUA') || in_array($jurusan_raw, $daftar_jurusan));
$rombel_dipilih  = ($rombel_raw !== null) && ($rombel_raw === 'SEMUA' || in_array($rombel_raw, ['1', '2', '3']));

// Nilai yang dipakai untuk QUERY ke database ('SEMUA' / tidak valid -> dianggap kosong / tidak difilter).
$jurusan_aktif = ($jurusan_raw !== null && in_array($jurusan_raw, $daftar_jurusan)) ? $jurusan_raw : '';
$rombel_aktif  = ($rombel_raw !== null && in_array($rombel_raw, ['1', '2', '3'])) ? $rombel_raw : '';

// Penanda "tombol Terapkan sudah diklik minimal sekali" untuk kombinasi
// filter yang sedang aktif. Kalau belum ada (misal baru masuk dari sidebar
// Kelas 10/11/12), tabel di bawah belum ditampilkan. (Sama persis logic-nya
// dengan halaman Transaksi Pembayaran.)
$sudah_terapkan = isset($_GET['f']);

if (!in_array($tingkat_aktif, ['10', '11', '12'])) $tingkat_aktif = '';

// ================== TAMBAHAN: palet warna buat avatar & badge kelas ==================
// Satu pasang [bg, text] per warna. Nanti dipilih otomatis berdasarkan huruf
// pertama nama siswa, jadi tiap siswa kebagian warna yang beda-beda tapi tetap konsisten
// (nama yang sama selalu dapat warna yang sama tiap halaman dimuat ulang).
$palet_warna = [
    ['bg' => '#fce7f3', 'text' => '#db2777'], // pink
    ['bg' => '#ede9fe', 'text' => '#7c3aed'], // ungu
    ['bg' => '#dbeafe', 'text' => '#2563eb'], // biru
    ['bg' => '#dcfce7', 'text' => '#16a34a'], // hijau
    ['bg' => '#fef3c7', 'text' => '#d97706'], // kuning/amber
    ['bg' => '#cffafe', 'text' => '#0891b2'], // cyan
];

// Ambil 1-2 huruf depan nama buat ditaruh di dalam lingkaran avatar (mis. "Cinta Amelia" -> "CA")
function ambil_inisial($nama) {
    $kata = preg_split('/\s+/', trim($nama));
    $inisial = strtoupper(substr($kata[0], 0, 1));
    if (count($kata) > 1) {
        $inisial .= strtoupper(substr(end($kata), 0, 1));
    }
    return $inisial;
}

// Pilih warna dari $palet_warna berdasarkan huruf pertama nama, supaya konsisten
function ambil_warna($nama, $palet_warna) {
    $index = ord(strtoupper(substr(trim($nama), 0, 1))) % count($palet_warna);
    return $palet_warna[$index];
}
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h3 fw-bold" style="color: #db2777;">📖 Kelola Data Siswa</h1>
    </div>

    <div class="mb-3">
        <?php
        // Kalau lagi di halaman kelas tertentu (misal ?tingkat=11), bawa info itu
        // (termasuk jurusan kalau sedang difilter) ke halaman tambah siswa,
        // supaya dropdown Tingkat & Jurusan otomatis kepilih.
        $link_tambah = 'tambah_siswa.php';
        $param_tambah = [];
        if ($tingkat_aktif != '') $param_tambah['tingkat'] = $tingkat_aktif;
        if ($jurusan_aktif != '') $param_tambah['jurusan'] = $jurusan_aktif;
        if ($rombel_aktif != '') $param_tambah['rombel'] = $rombel_aktif;
        if (count($param_tambah) > 0) {
            $link_tambah .= '?' . http_build_query($param_tambah);
        }
        ?>
        <a href="<?= $link_tambah; ?>" class="btn btn-primary" style="background-color: #db2777; border: none;"><i class="bi bi-plus-lg me-1"></i> Tambah Siswa</a>
    </div>

    <!-- FILTER: modelnya disamakan dengan halaman Transaksi Pembayaran -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tingkat" value="<?= htmlspecialchars($tingkat_aktif); ?>">
                <input type="hidden" name="f" value="1">

                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Jurusan</label>
                    <select name="jurusan" id="select-jurusan" class="form-select form-select-sm">
                        <option value="" disabled hidden <?= !$jurusan_dipilih ? 'selected' : ''; ?>>-- Pilih Jurusan --</option>
                        <?php if ($tingkat_aktif === ''): // "Semua Jurusan" cuma tersedia di halaman "Semua Siswa" ?>
                        <option value="SEMUA" <?= ($jurusan_raw === 'SEMUA') ? 'selected' : ''; ?>>Semua Jurusan</option>
                        <?php endif; ?>
                        <?php foreach ($daftar_jurusan as $j): ?>
                            <option value="<?= $j; ?>" <?= ($jurusan_raw === $j) ? 'selected' : ''; ?>><?= $j; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Rombel</label>
                    <select name="rombel" id="select-rombel" class="form-select form-select-sm" <?= !$jurusan_dipilih ? 'disabled' : ''; ?>>
                        <option value="" disabled hidden <?= !$rombel_dipilih ? 'selected' : ''; ?>>-- Pilih Rombel --</option>
                        <option value="SEMUA" <?= ($rombel_raw === 'SEMUA') ? 'selected' : ''; ?>>Semua Rombel</option>
                        <?php foreach (['1', '2', '3'] as $r): ?>
                            <option value="<?= $r; ?>" <?= ($rombel_raw === $r) ? 'selected' : ''; ?>>Rombel <?= $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" id="btn-terapkan" class="btn btn-sm text-white flex-fill" style="background-color:#db2777;" <?= !($jurusan_dipilih && $rombel_dipilih) ? 'disabled' : ''; ?>>Terapkan</button>
                    <?php if ($sudah_terapkan && ($jurusan_aktif !== '' || $rombel_aktif !== '')): ?>
                        <a href="siswa.php?tingkat=<?= urlencode($tingkat_aktif); ?>&f=1" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Supaya kelihatan JELAS abu-abu & tidak bisa diklik saat masih terkunci,
           bukan cuma agak transparan seperti default Bootstrap. */
        #btn-terapkan:disabled {
            background-color: #adb5bd !important;
            opacity: 1 !important;
            cursor: not-allowed;
        }
        #select-rombel:disabled {
            background-color: #e9ecef !important;
            cursor: not-allowed;
        }

        /* ================== TAMBAHAN: gaya card ringkasan & tabel model History ================== */
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

        .avatar-siswa {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            flex-shrink: 0;
        }
        .badge-kelas-cantik {
            border-radius: 999px;
            padding: 0.32rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 600;
            background-color: #f3f4f6;
            color: #374151;
            white-space: nowrap;
        }
        .btn-aksi-pil {
            border-radius: 999px;
            width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            border: none;
        }
        #tabelSiswa tbody tr:hover { background-color: #fdf2f8; }
    </style>

    <script>
        // Kunci berjenjang, sama kaya alur di halaman Transaksi Pembayaran:
        // 1. Rombel terkunci selama Jurusan belum dipilih.
        // 2. Terapkan terkunci selama Rombel belum "disentuh" (dipilih), meski
        //    yang dipilih itu "Semua Rombel" sekalipun.
        (function () {
            var selectJurusan = document.getElementById('select-jurusan');
            var selectRombel  = document.getElementById('select-rombel');
            var btnTerapkan   = document.getElementById('btn-terapkan');
            if (!selectJurusan) return;

            // "Sudah dipilih" itu beda dari "value kosong": milih opsi "Semua Jurusan" /
            // "Semua Rombel" TETAP dihitung sebagai pilihan yang sah, bukan "belum pilih".
            var jurusanDipilih = <?= $jurusan_dipilih ? 'true' : 'false'; ?>;
            var rombelDipilih  = <?= $rombel_dipilih ? 'true' : 'false'; ?>;

            function perbaruiKunci() {
                selectRombel.disabled = !jurusanDipilih;
                btnTerapkan.disabled  = !jurusanDipilih || !rombelDipilih;
            }

            selectJurusan.addEventListener('change', function () {
                jurusanDipilih = true;
                rombelDipilih = false; // ganti jurusan -> rombel harus dipilih ulang
                selectRombel.selectedIndex = 0; // balik ke placeholder "-- Pilih Rombel --"
                perbaruiKunci();
            });
            selectRombel.addEventListener('change', function () {
                rombelDipilih = true;
                perbaruiKunci();
            });

            perbaruiKunci(); // jalankan sekali di awal
        })();
    </script>

    <?php if (!$sudah_terapkan): ?>

        <!-- Belum klik Terapkan sama sekali: jangan tampilkan tabel dulu -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-funnel fs-3 d-block mb-2"></i>
                Pilih Jurusan / Rombel (atau biarkan "Semua" kalau mau lihat semua), lalu klik <strong>Terapkan</strong> untuk menampilkan daftar siswa.
            </div>
        </div>

    <?php else: ?>

    <?php
    // ================== QUERY (logic-nya SAMA PERSIS seperti sebelumnya) ==================
    $kondisi = [];

    // tingkat pakai EXACT MATCH ('=') karena data di DB sudah rapi (persis "10"/"11"/"12").
    if ($tingkat_aktif != '') {
        $tkt = mysqli_real_escape_string($koneksi, $tingkat_aktif);
        $kondisi[] = "kelas.tingkat = '$tkt'";
    }
    // jurusan dicek di AWAL teks (misal "PPLG%" cocok ke "PPLG 1", "PPLG 2", dst).
    // Kalau rombel juga dipilih, nyari EXACT ke "PPLG 1" (jurusan + rombel spesifik).
    if ($jurusan_aktif != '') {
        $jrs = mysqli_real_escape_string($koneksi, $jurusan_aktif);
        if ($rombel_aktif != '') {
            $rmb = mysqli_real_escape_string($koneksi, $rombel_aktif);
            $kondisi[] = "kelas.jurusan = '$jrs $rmb'";
        } else {
            $kondisi[] = "kelas.jurusan LIKE '$jrs%'";
        }
    }

    $where = count($kondisi) > 0 ? "WHERE " . implode(" AND ", $kondisi) : "";

    // Query utama dengan ORDER BY nisn DESC agar data terbaru muncul di paling atas
    $query = mysqli_query($koneksi, "SELECT siswa.*, kelas.tingkat, kelas.jurusan, spp.nominal 
             FROM siswa 
             JOIN kelas ON siswa.id_kelas = kelas.id_kelas 
             JOIN spp ON siswa.id_spp = spp.id_spp 
             $where 
             ORDER BY (siswa.nisn + 0) DESC");

    // ================== TAMBAHAN: tampung dulu ke array supaya bisa dihitung buat kartu ringkasan ==================
    $data_siswa = [];
    if ($query && mysqli_num_rows($query) > 0) {
        while ($row = mysqli_fetch_assoc($query)) {
            // Kolom tingkat di DB sekarang sudah bersih (persis "10"/"11"/"12"),
            // jadi tampilkan apa adanya, TIDAK perlu ditebak-tebak lagi.
            $tampilkan_tingkat = $row['tingkat'];

            // Kolom jurusan formatnya "PPLG 1", "AKL 2", "APHP 3", dst.
            // Ambil kode jurusannya saja (buang angka rombel di belakang) untuk ditampilkan.
            $tampilkan_jurusan = trim(preg_replace('/\s*\d+$/', '', $row['jurusan']));

            // Ambil nomor rombel-nya saja (angka di belakang, misal "1" dari "PPLG 1")
            $tampilkan_rombel = '';
            if (preg_match('/(\d+)\s*$/', $row['jurusan'], $m)) {
                $tampilkan_rombel = $m[1];
            }

            // Simpan tiap bagian secara terpisah (dipakai buat kolom Tingkat/Jurusan/Rombel sendiri-sendiri).
            $row['tampilkan_tingkat'] = $tampilkan_tingkat;
            $row['tampilkan_jurusan'] = $tampilkan_jurusan;
            $row['tampilkan_rombel']  = $tampilkan_rombel;
            // Gabungan Tingkat + Jurusan + Rombel, dipakai buat hitung "Jumlah Kelas Ditampilkan" di kartu ringkasan.
            $row['tampilkan_kelas'] = trim($tampilkan_tingkat . ' ' . $tampilkan_jurusan . ' ' . $tampilkan_rombel);
            $data_siswa[] = $row;
        }
    }

    // Angka-angka buat 3 kartu ringkasan di atas tabel
    $total_siswa   = count($data_siswa);
    $total_nominal = array_sum(array_column($data_siswa, 'nominal'));
    $jumlah_kelas  = count(array_unique(array_column($data_siswa, 'tampilkan_kelas')));
    ?>

    <!-- ================== TAMBAHAN: KARTU RINGKASAN (model History) ================== -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kartu-ringkasan" style="background-color:#fce7f3;">
                <div class="ikon-box" style="background-color:#fbcfe8;">👥</div>
                <div>
                    <div class="label-kecil">Total Siswa (sesuai filter)</div>
                    <div class="angka-besar"><?= $total_siswa; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kartu-ringkasan" style="background-color:#ede9fe;">
                <div class="ikon-box" style="background-color:#ddd6fe;">🏫</div>
                <div>
                    <div class="label-kecil">Jumlah Kelas Ditampilkan</div>
                    <div class="angka-besar"><?= $jumlah_kelas; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kartu-ringkasan" style="background-color:#dcfce7;">
                <div class="ikon-box" style="background-color:#bbf7d0;">💰</div>
                <div>
                    <div class="label-kecil">Total Tagihan SPP / Bulan</div>
                    <div class="angka-besar">Rp <?= number_format($total_nominal, 0, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelSiswa" class="table table-hover align-middle mb-0">
                    <thead style="background-color: #fdf2f8; color: #db2777;">
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Tingkat</th>
                            <th>Jurusan</th>
                            <th>Rombel</th>
                            <th>Alamat</th>
                            <th>No. Telp</th>
                            <th>Nominal SPP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_siswa) > 0): ?>
                            <?php $no = 1; foreach ($data_siswa as $row):
                                $inisial = ambil_inisial($row['nama']);
                                $warna   = ambil_warna($row['nama'], $palet_warna);
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nisn']); ?></td>
                                    <td><?= htmlspecialchars($row['nis']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-siswa" style="background-color: <?= $warna['bg']; ?>; color: <?= $warna['text']; ?>;">
                                                <?= $inisial; ?>
                                            </div>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['nama']); ?></div>
                                        </div>
                                    </td>
                                    <td><span class="badge-kelas-cantik"><?= htmlspecialchars($row['tampilkan_tingkat']); ?></span></td>
                                    <td><span class="badge-kelas-cantik">🎓 <?= htmlspecialchars($row['tampilkan_jurusan']); ?></span></td>
                                    <td><span class="badge-kelas-cantik">Rombel <?= htmlspecialchars($row['tampilkan_rombel']); ?></span></td>
                                    <td><?= htmlspecialchars($row['alamat']); ?></td>
                                    <td><?= htmlspecialchars($row['no_telp']); ?></td>
                                    <td class="fw-semibold" style="color:#16a34a;">Rp <?= number_format($row['nominal'], 0, ',', '.'); ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="edit_siswa.php?nisn=<?= $row['nisn']; ?>" class="btn-aksi-pil" style="background-color:#fef3c7; color:#d97706;" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="hapus_siswa.php?nisn=<?= $row['nisn']; ?>" class="btn-aksi-pil" style="background-color:#fee2e2; color:#dc2626;" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="11" class="text-center py-3 text-muted">Tidak ada data siswa ditemukan untuk kelas ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; // sudah_terapkan ?>
</main>

<?php include '../components/footer.php'; ?>