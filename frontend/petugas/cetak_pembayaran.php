<?php
// LETAKKAN FILE INI DI: pembayaran_spp/frontend/petugas/cetak_pembayaran.php
// (timpa/replace file yang lama)

require_once __DIR__ . '/../keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

// Terima "ids" (boleh satu angka atau beberapa dipisah koma) atau id_pembayaran lama
$ids_mentah = '';
if (isset($_GET['ids'])) {
    $ids_mentah = $_GET['ids'];
} elseif (isset($_GET['id_pembayaran'])) {
    $ids_mentah = $_GET['id_pembayaran'];
}

$daftar_id = array_filter(array_map('intval', explode(',', $ids_mentah)));
if (count($daftar_id) === 0) {
    header("Location: transaksi.php");
    exit();
}
$ids_sql = implode(',', $daftar_id);

$q = mysqli_query($koneksi, "
    SELECT pembayaran.*, siswa.nama, siswa.nis, kelas.tingkat, kelas.jurusan,
           spp.tahun AS tahun_spp, petugas.nama_petugas
    FROM pembayaran
    JOIN siswa ON pembayaran.nisn = siswa.nisn
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    JOIN spp ON pembayaran.id_spp = spp.id_spp
    LEFT JOIN petugas ON pembayaran.id_petugas = petugas.id_petugas
    WHERE pembayaran.id_pembayaran IN ($ids_sql)
    ORDER BY pembayaran.tahun_dibayar ASC, pembayaran.id_pembayaran ASC
");

$daftar = [];
$total_bayar = 0;
while ($row = mysqli_fetch_assoc($q)) {
    $daftar[] = $row;
    $total_bayar += (int) $row['jumlah_bayar'];
}

if (count($daftar) === 0) {
    echo "<script>alert('Data pembayaran tidak ditemukan.'); window.location='transaksi.php';</script>";
    exit();
}

$d = $daftar[0];
$jurusan_kode = trim(preg_replace('/\s*\d+$/', '', $d['jurusan']));
$rombel_no = '';
if (preg_match('/(\d+)\s*$/', $d['jurusan'], $m)) {
    $rombel_no = $m[1];
}

// Nomor kuitansi: kalau 1 transaksi pakai id aslinya, kalau gabungan pakai id pertama + jumlah bulan
$nomor_kuitansi = (count($daftar) === 1)
    ? str_pad($daftar[0]['id_pembayaran'], 5, '0', STR_PAD_LEFT)
    : str_pad($daftar[0]['id_pembayaran'], 5, '0', STR_PAD_LEFT) . '-' . count($daftar) . 'BLN';

// Ubah angka jadi kata (buat baris "terbilang" di kuitansi)
function terbilang($angka)
{
    $angka = (int) $angka;
    $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($angka < 12) {
        return ' ' . $huruf[$angka];
    } elseif ($angka < 20) {
        return terbilang($angka - 10) . ' belas';
    } elseif ($angka < 100) {
        return terbilang((int)($angka / 10)) . ' puluh' . terbilang($angka % 10);
    } elseif ($angka < 200) {
        return ' seratus' . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        return terbilang((int)($angka / 100)) . ' ratus' . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        return ' seribu' . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        return terbilang((int)($angka / 1000)) . ' ribu' . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        return terbilang((int)($angka / 1000000)) . ' juta' . terbilang($angka % 1000000);
    }
    return '';
}
$terbilang_teks = ucwords(trim(terbilang($total_bayar))) . ' Rupiah';
$satu_transaksi = (count($daftar) === 1);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kuitansi SPP - <?= htmlspecialchars($d['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        .kuitansi {
            max-width: 720px;
            margin: 30px auto;
            background: #fff;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .08);
        }

        .kop {
            border-bottom: 3px double #db2777;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }

        .kop h4 {
            color: #db2777;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .label {
            color: #64748b;
            width: 38%;
        }

        .total-box {
            background: #fdf2f8;
            border: 1px dashed #fbcfe8;
            border-radius: 10px;
            padding: 14px 18px;
            margin-top: 18px;
        }

        .ttd {
            margin-top: 42px;
        }

        @media print {
            body {
                background: #fff;
            }

            .kuitansi {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="kuitansi">
        <div class="kop d-flex justify-content-between align-items-start">
            <div>
                <h4>SPP DIGITAL</h4>
                <div class="small text-muted">Bukti Pembayaran SPP Siswa</div>
            </div>
            <div class="text-end small">
                <div class="fw-bold">No. #<?= $nomor_kuitansi; ?></div>
                <div class="text-muted"><?= date('d F Y', strtotime($d['tgl_bayar'])); ?></div>
            </div>
        </div>

        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td class="label">Nama Siswa</td>
                <td>: <strong><?= htmlspecialchars($d['nama']); ?></strong></td>
            </tr>
            <tr>
                <td class="label">NISN / NIS</td>
                <td>: <?= htmlspecialchars($d['nisn'] . ' / ' . $d['nis']); ?></td>
            </tr>
            <tr>
                <td class="label">Kelas</td>
                <td>: <?= htmlspecialchars($d['tingkat'] . ' ' . $jurusan_kode . ' ' . $rombel_no); ?></td>
            </tr>
            <tr>
                <td class="label" style="vertical-align: top;">Pembayaran Untuk</td>
                <td>
                    <?php if ($satu_transaksi): ?>
                        : SPP Bulan <strong><?= htmlspecialchars($d['bulan_dibayar'] . ' ' . $d['tahun_dibayar']); ?></strong>
                    <?php else: ?>
                        : SPP <?= count($daftar); ?> bulan sekaligus, tahun ajaran <?= formatTA($d['tahun_dibayar']); ?> <div class="mt-1">
                            <?php foreach ($daftar as $t): ?>
                                <span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars($t['bulan_dibayar']); ?> &middot; Rp <?= number_format($t['jumlah_bayar'], 0, ',', '.'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td>: <span class="badge bg-success">LUNAS</span></td>
            </tr>
        </table>

        <div class="total-box d-flex justify-content-between align-items-center">
            <div>
                <div class="small text-muted">Jumlah Dibayar<?= $satu_transaksi ? '' : ' (' . count($daftar) . ' bulan)'; ?></div>
                <div class="h4 fw-bold mb-0" style="color:#db2777;">Rp <?= number_format($total_bayar, 0, ',', '.'); ?></div>
            </div>
            <div class="text-end small fst-italic text-muted" style="max-width: 50%;">
                Terbilang: <?= $terbilang_teks; ?>
            </div>
        </div>

        <div class="ttd d-flex justify-content-between">
            <div class="text-center small">
                <div class="text-muted mb-5">Siswa / Orang Tua</div>
                <div style="border-top:1px solid #cbd5e1; padding-top:4px; min-width:160px;">
                    <?= htmlspecialchars($d['nama']); ?>
                </div>
            </div>
            <div class="text-center small">
                <div class="text-muted mb-5">Petugas</div>
                <div style="border-top:1px solid #cbd5e1; padding-top:4px; min-width:160px;">
                    <?= htmlspecialchars($d['nama_petugas'] ?? '-'); ?>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 pt-3 border-top">
            Kuitansi ini sah tanpa tanda tangan basah &mdash; dicetak dari Aplikasi SPP Digital
            pada <?= date('d-m-Y H:i'); ?>
        </div>
    </div>

    <div class="text-center mb-4 no-print">
        <button onclick="window.print()" class="btn text-white" style="background-color:#db2777;">Cetak / Simpan PDF</button>
        <button id="btnKirimWA" onclick="kirimWA()" class="btn btn-success">Kirim ke WhatsApp</button>
        <a href="detail_pembayaran.php?ids=<?= implode(',', $daftar_id); ?>" class="btn btn-secondary">Kembali</a>
    </div>

    <script>
        const daftarIds = "<?= implode(',', $daftar_id); ?>";
        // URL halaman "Transaksi Pembayaran" (daftar siswa) sesuai kelas siswa ini,
        // supaya abis kirim WA bisa langsung balik ke situ (bukan ke halaman detail lagi)
        const urlDaftarTransaksi = "transaksi.php?tingkat=<?= urlencode($d['tingkat']); ?>";

        // Tombol "Kirim ke WhatsApp": sekarang kirim OTOMATIS lewat server (Fonnte),
        // tidak perlu buka WhatsApp lagi.
        function kirimWA() {
            const btn = document.getElementById('btnKirimWA');
            const teksAsli = btn.innerText;
            btn.disabled = true;
            btn.innerText = "Mengirim...";

            fetch("../../backend/kirim_wa.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "ids=" + encodeURIComponent(daftarIds)
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        // Berhasil: langsung balik ke halaman daftar Transaksi Pembayaran, gak perlu klik "Kembali" lagi
                        window.location.href = urlDaftarTransaksi;
                    } else {
                        // Gagal: biarkan di halaman ini supaya bisa dicoba ulang
                        btn.disabled = false;
                        btn.innerText = teksAsli;
                    }
                })
                .catch(err => {
                    alert("Terjadi kesalahan saat mengirim: " + err);
                    btn.disabled = false;
                    btn.innerText = teksAsli;
                });
        }

        // Bunyi "ting" saat kuitansi muncul, lalu auto-buka dialog cetak
        window.addEventListener('load', function() {
            try {
                const bunyi = new Audio('../../media/sukses.wav');
                bunyi.play().catch(function() {
                    /* kalau browser memblokir suara, abaikan saja */ });
            } catch (e) {}
            setTimeout(function() {
                window.print();
            }, 400);
        });
    </script>
</body>

</html>