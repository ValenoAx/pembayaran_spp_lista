<?php
/**
 * frontend/index.php  —  Halaman depan SPP DIGITAL (untuk siswa / wali)
 * LETAKKAN DI: pembayaran_spp/frontend/index.php
 *
 * Isinya: penjelasan singkat + form "Cek Status SPP" (NISN + NIS).
 * Verifikasi & tampilan hasilnya ada di cek_spp.php.
 */
require_once __DIR__ . '/keamanan.php';
include '../koneksi.php';

// Token anti-CSRF untuk form cek status
if (empty($_SESSION['csrf_cek'])) {
    $_SESSION['csrf_cek'] = bin2hex(random_bytes(16));
}

// Pesan error (flash) dari cek_spp.php
$error = $_SESSION['cek_error'] ?? '';
unset($_SESSION['cek_error']);

// Angka ringkas (agregat saja, tidak membuka data pribadi siapa pun)
$total_siswa = 0;
$total_transaksi = 0;
$q1 = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM siswa");
if ($q1) { $total_siswa = (int) mysqli_fetch_assoc($q1)['n']; }
$q2 = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pembayaran");
if ($q2) { $total_transaksi = (int) mysqli_fetch_assoc($q2)['n']; }

// Kalau siswa masih punya sesi cek status yang aktif
$siswa_aktif = !empty($_SESSION['siswa_nisn']);

function formatTA($tahun) { $t = (int) $tahun; return $t . '/' . ($t + 1); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SPP Digital — Cek Status Pembayaran SPP</title>
<meta name="description" content="Cek status pembayaran SPP per bulan, tunggakan, dan riwayat kuitansi dengan NISN dan NIS.">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2248%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2230%22%20y%3D%2220%22%20width%3D%2240%22%20height%3D%2260%22%20rx%3D%226%22%20fill%3D%22%23ffffff%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2230%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2240%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2250%22%20width%3D%2218%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Ccircle%20cx%3D%2263%22%20cy%3D%2266%22%20r%3D%2212%22%20fill%3D%22%23DB2777%22/%3E%3Cpath%20d%3D%22M58%2066%20L61.5%2069.5%20L68.5%2062.5%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22/%3E%3C/svg%3E">
<link href="https://fonts.googleapis.com" rel="preconnect">
<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
<link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="index-page">

  <!-- ===== HEADER ===== -->
  <header class="header d-flex align-items-center sticky-top" style="background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.06)">
    <div class="container-fluid container-xl d-flex align-items-center justify-content-between">
      <a href="index.php" class="logo d-flex align-items-center">
        <i class="bi bi-file-earmark-text-fill fs-3" style="color:#DB2777"></i>
        <h1 class="sitename ms-2 mb-0">SPP Digital</h1>
      </a>
    </div>
  </header>

  <main class="main">

    <!-- ===== HERO ===== -->
    <section class="section" style="background:#fdf2f8;padding-top:60px;padding-bottom:60px">
      <div class="container">
        <div class="row align-items-center gy-5">
          <div class="col-lg-6">
            <span class="badge rounded-pill" style="background:#fce7f3;color:#DB2777">● Untuk siswa &amp; orang tua</span>
            <h1 class="mt-3" style="font-weight:900;color:#3b0a2b">Status SPP-mu, <em style="color:#DB2777">jelas</em> kapan saja.</h1>
            <p class="lead">Lihat bulan mana yang sudah lunas, cek tunggakan, dan buka riwayat kuitansi tanpa perlu antre bertanya ke loket.</p>

            <div class="card border-0 shadow-sm p-4 mt-4" id="cek">
              <h2 class="h4">Cek status SPP</h2>
              <p class="text-muted small">Masukkan NISN dan NIS yang tertera di kartu pelajar.</p>

              <?php if ($error): ?>
                <div class="alert alert-danger py-2" role="alert"><?= htmlspecialchars($error); ?></div>
              <?php endif; ?>

              <?php if ($siswa_aktif): ?>
                <div class="alert alert-info py-2">
                  Kamu masih masuk di perangkat ini.
                  <a href="cek_spp.php">Lanjut lihat status</a> &middot;
                  <a href="cek_spp.php?keluar=1">Keluar</a>
                </div>
              <?php endif; ?>

              <form method="POST" action="cek_spp.php" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cek']); ?>">
                <div class="mb-3">
                  <label for="nisn" class="form-label fw-semibold">NISN</label>
                  <input type="text" class="form-control" id="nisn" name="nisn" inputmode="numeric" maxlength="20" placeholder="Contoh: 0051234567" required>
                </div>
                <div class="mb-2">
                  <label for="nis" class="form-label fw-semibold">NIS</label>
                  <input type="text" class="form-control" id="nis" name="nis" inputmode="numeric" maxlength="20" placeholder="Nomor induk sekolah" required>
                  <div class="form-text">Dua-duanya harus cocok, demi menjaga privasi data pembayaran.</div>
                </div>
                <button type="submit" class="btn btn-pink btn-lg w-100 mt-2 rounded-pill">Lihat status SPP</button>
              </form>

              <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-shield-check" style="color:#DB2777"></i>
                Sesi otomatis berakhir setelah 15 menit. Jangan lupa tekan "Keluar" kalau memakai perangkat bersama.
              </p>
            </div>
          </div>

          <!-- kartu dekoratif -->
          <div class="col-lg-5 offset-lg-1">
            <div class="kartu-spp">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <b class="small">SPP DIGITAL</b>
                <span class="tag">KARTU SPP</span>
              </div>
              <h3 class="mb-0">Tahun Ajaran <?= formatTA(date('Y')); ?></h3>
              <small class="opacity-75">Contoh tampilan status per bulan</small>
              <div class="grid">
                <?php
                $singk = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                $bln_ini = (int) date('n');
                foreach ($singk as $i => $s) {
                    $ke = $i + 1;
                    $kelas = ($ke < $bln_ini) ? 'on' : (($ke === $bln_ini) ? 'now' : '');
                    echo '<span class="' . $kelas . '">' . $s . '</span>';
                }
                ?>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="opacity-75">Lunas &middot; bulan berjalan &middot; belum</small>
                <b>SPP</b>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== FITUR ===== -->
    <section id="fitur" class="section">
      <div class="container text-center" data-aos="fade-up">
        <h2>Semua yang perlu kamu tahu, di satu halaman</h2>
        <p class="text-muted">Data langsung dari catatan petugas sekolah, jadi selalu sama dengan yang ada di loket.</p>
      </div>
      <div class="container">
        <div class="row gy-4 mt-2">
          <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4 text-center">
              <i class="bi bi-calendar2-check fs-1" style="color:#DB2777"></i>
              <h3 class="h5 mt-3">Status per bulan</h3>
              <p class="text-muted small mb-0">Dua belas bulan ditampilkan sekaligus: lunas, bulan berjalan, atau menunggak, lengkap per tahun ajaran.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4 text-center">
              <i class="bi bi-exclamation-triangle fs-1" style="color:#DB2777"></i>
              <h3 class="h5 mt-3">Tunggakan yang transparan</h3>
              <p class="text-muted small mb-0">Jumlah bulan dan total rupiah yang belum dibayar dihitung otomatis, tidak ada yang tertinggal.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4 text-center">
              <i class="bi bi-receipt fs-1" style="color:#DB2777"></i>
              <h3 class="h5 mt-3">Riwayat &amp; nomor kuitansi</h3>
              <p class="text-muted small mb-0">Setiap pembayaran punya nomor kuitansi dan tanggal. Halaman status juga bisa dicetak sebagai rekap.</p>
            </div>
          </div>
        </div>

        <div class="row text-center mt-5">
          <div class="col-6">
            <h3 style="color:#DB2777;font-weight:900"><?= number_format($total_siswa, 0, ',', '.'); ?></h3>
            <p class="text-muted mb-0">siswa terdaftar</p>
          </div>
          <div class="col-6">
            <h3 style="color:#DB2777;font-weight:900"><?= number_format($total_transaksi, 0, ',', '.'); ?></h3>
            <p class="text-muted mb-0">pembayaran tercatat</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== ALUR ===== -->
    <section id="alur" class="section light-background">
      <div class="container text-center">
        <h2>Bagaimana cara kerjanya?</h2>
      </div>
      <div class="container">
        <div class="row gy-4 text-center">
          <div class="col-md-4">
            <div class="fs-1 fw-bold" style="color:#DB2777">1</div>
            <h3 class="h5">Bayar ke petugas</h3>
            <p class="text-muted small">Pembayaran SPP dilakukan langsung lewat petugas sekolah, per bulan.</p>
          </div>
          <div class="col-md-4">
            <div class="fs-1 fw-bold" style="color:#DB2777">2</div>
            <h3 class="h5">Tercatat otomatis</h3>
            <p class="text-muted small">Petugas menginput pembayaran, status bulan itu langsung berubah jadi lunas.</p>
          </div>
          <div class="col-md-4">
            <div class="fs-1 fw-bold" style="color:#DB2777">3</div>
            <h3 class="h5">Cek &amp; simpan bukti</h3>
            <p class="text-muted small">Buka halaman ini kapan pun untuk memastikan, atau cetak rekapnya.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== FAQ ===== -->
    <section id="faq" class="section">
      <div class="container">
        <h2 class="text-center mb-4">Pertanyaan yang sering muncul</h2>
        <div class="accordion" id="faqAcc">
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f1">Di mana saya bisa menemukan NISN dan NIS?</button></h3>
            <div id="f1" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Keduanya biasanya tercetak di kartu pelajar atau rapor. Kalau belum tahu, tanyakan ke wali kelas atau tata usaha.</div></div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f2">Saya sudah bayar tapi statusnya belum lunas?</button></h3>
            <div id="f2" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Status diperbarui setelah petugas mencatat pembayaran. Kalau sudah lewat satu hari kerja dan belum berubah, bawa bukti kuitansi ke petugas sekolah.</div></div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f3">Apa arti "Menunggak" dan "Bulan berjalan"?</button></h3>
            <div id="f3" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">"Menunggak" berarti bulan tersebut sudah lewat dan belum tercatat dibayar. "Bulan berjalan" adalah bulan saat ini. Bulan setelahnya ditandai abu-abu sampai waktunya tiba.</div></div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f4">Apakah data saya bisa dilihat orang lain?</button></h3>
            <div id="f4" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Tidak bisa tanpa NISN dan NIS yang cocok. Setelah beberapa kali salah input, pengecekan dikunci sementara.</div></div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer text-center py-4" style="background:#3b0a2b;color:#fff">
    <div class="container d-flex flex-column flex-md-row justify-content-center align-items-center gap-2 gap-md-3">
      <span>&copy; <?= date('Y'); ?> SPP Digital &middot; Aplikasi Pembayaran SPP Sekolah</span>
      <span class="d-none d-md-inline text-white-50">&middot;</span>
      <a href="../login.php" class="text-white-50">Login admin / petugas</a>
    </div>
  </footer>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>