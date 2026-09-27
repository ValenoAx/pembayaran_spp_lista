<?php
/**
 * frontend/views/status_spp.php — TAMPILAN halaman status SPP siswa
 * LETAKKAN DI: pembayaran_spp/frontend/views/status_spp.php
 *
 * File ini tidak dibuka langsung di browser. Ia dipanggil oleh cek_spp.php,
 * yang sudah menyiapkan variabel: $siswa, $inisial, $daftar_tahun, $tab_awal,
 * $data_tahun, $total_lunas, $total_dibayar, $jml_tunggak, $rp_tunggak,
 * $riwayat, dan $label_status.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Status SPP — <?= e($siswa['nama']); ?></title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2248%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2230%22%20y%3D%2220%22%20width%3D%2240%22%20height%3D%2260%22%20rx%3D%226%22%20fill%3D%22%23ffffff%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2230%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2240%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2250%22%20width%3D%2218%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Ccircle%20cx%3D%2263%22%20cy%3D%2266%22%20r%3D%2212%22%20fill%3D%22%23DB2777%22/%3E%3Cpath%20d%3D%22M58%2066%20L61.5%2069.5%20L68.5%2062.5%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22/%3E%3C/svg%3E">
<link href="https://fonts.googleapis.com" rel="preconnect">
<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
<link href="assets/css/custom.css" rel="stylesheet">
<link href="assets/css/status-spp.css" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width:900px;padding-top:30px;padding-bottom:40px">

  <!-- ===== HERO: brand, aksi, profil, status ringkas ===== -->
  <section class="hero">
    <div class="hero-top">
      <a href="index.php" class="brand">
        <i class="bi bi-file-earmark-text-fill"></i>
        <span>SPP Digital</span>
      </a>
      <div class="hero-actions d-flex gap-2">
        <button type="button" class="btn btn-ghost btn-sm no-print" onclick="window.print()">
          <i class="bi bi-printer"></i> Cetak rekap
        </button>
        <button type="button" class="btn btn-pink btn-sm no-print" data-bs-toggle="modal" data-bs-target="#modalKeluar">Keluar</button>
      </div>
    </div>

    <div class="hero-profile">
      <div class="avatar-circle"><?= e($inisial); ?></div>
      <div>
        <h1><?= e($siswa['nama']); ?></h1>
        <div class="hero-badges">
          <span class="badge">Kelas <?= e($siswa['tingkat'] . ' ' . $siswa['jurusan']); ?></span>
          <span class="badge">NISN <?= e($siswa['nisn']); ?></span>
          <span class="badge">NIS <?= e($siswa['nis']); ?></span>
          <span class="badge">Masuk TA <?= formatTA($siswa['tahun_masuk']); ?></span>
        </div>
      </div>
    </div>

    <?php if (!$daftar_tahun): ?>
      <div class="hero-status">
        <i class="bi bi-info-circle-fill"></i>
        <span>Tarif SPP untuk tahun ajaranmu belum diisi oleh admin, jadi status belum bisa ditampilkan.</span>
      </div>
    <?php elseif ($jml_tunggak === 0): ?>
      <div class="hero-status is-ok">
        <i class="bi bi-check-circle-fill"></i>
        <span><b>Mantap!</b> Tidak ada tunggakan SPP.</span>
      </div>
    <?php else: ?>
      <div class="hero-status is-warn">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>Ada <b><?= $jml_tunggak; ?> bulan</b> yang belum dibayar (<b><?= rp($rp_tunggak); ?></b>). Silakan selesaikan lewat petugas sekolah.</span>
      </div>
    <?php endif; ?>
  </section>

  <main>
    <!-- ===== RINGKASAN ===== -->
    <div class="stat-strip">
      <div class="stat">
        <small class="stat-label">Bulan lunas</small>
        <span class="stat-num"><?= $total_lunas; ?></span>
        <small class="stat-sub">di semua tahun ajaran</small>
      </div>
      <div class="stat">
        <small class="stat-label">Total sudah dibayar</small>
        <span class="stat-num"><?= rp($total_dibayar); ?></span>
        <small class="stat-sub"><?= count($riwayat); ?> transaksi</small>
      </div>
      <div class="stat">
        <small class="stat-label">Menunggak</small>
        <span class="stat-num"><?= $jml_tunggak; ?> bulan</span>
        <small class="stat-sub"><?= $jml_tunggak ? rp($rp_tunggak) : 'Tidak ada'; ?></small>
      </div>
    </div>

    <!-- ===== STATUS PER TAHUN AJARAN ===== -->
    <?php if ($daftar_tahun): ?>
    <section class="card border-0 p-4 mb-4">
      <h2 class="h5 mb-3">Status per bulan</h2>

      <ul class="nav nav-pills mb-3 no-print" role="tablist">
        <?php foreach ($daftar_tahun as $th): $aktif = ($th === $tab_awal); ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aktif ? 'active' : ''; ?>"
                    data-bs-toggle="pill" data-bs-target="#panel-<?= $th; ?>" type="button">
              TA <?= formatTA($th); ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="tab-content">
        <?php foreach ($data_tahun as $th => $d): $persen = (int) round($d['lunas'] / 12 * 100); $aktif = ($th === $tab_awal); ?>
          <div class="tab-pane fade <?= $aktif ? 'show active' : ''; ?>" id="panel-<?= $th; ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h3 class="h6 mb-0">Tahun Ajaran <?= formatTA($th); ?></h3>
              <small class="text-muted"><?= $d['lunas']; ?> dari 12 bulan lunas &middot; <?= rp($d['nominal']); ?> / bulan</small>
            </div>
            <div class="progress mb-3" style="height:8px">
              <div class="progress-bar" style="width:<?= $persen; ?>%"></div>
            </div>

            <div class="bulan-grid">
              <?php foreach ($d['bulan'] as $b): [$teks, $kls, $ikon] = $label_status[$b['status']]; ?>
                <div class="bln <?= $kls; ?>">
                  <span class="n"><i class="bi <?= $ikon; ?>"></i> <?= $b['nama']; ?></span>
                  <span class="s"><?= $teks; ?></span>
                  <span class="k small">
                    <?php if ($b['status'] === 'lunas'): ?>
                      Dibayar <?= tglIndo($b['tgl']); ?>
                    <?php elseif ($b['status'] === 'sebagian'): ?>
                      <?= rp($b['sudah']); ?> dari <?= rp($d['nominal']); ?>
                    <?php elseif ($b['status'] === 'tunggak'): ?>
                      <?= rp($d['nominal']); ?>
                    <?php else: ?>
                      &nbsp;
                    <?php endif; ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="legend mt-3 pt-3 border-top">
        <span><i style="background:var(--green-bg);border:1px solid var(--green-line)"></i>Lunas</span>
        <span><i style="background:var(--red-bg);border:1px solid var(--red-line)"></i>Tunggakan</span>
        <span><i style="background:#fff;border:1.5px solid var(--pink)"></i>Bulan berjalan</span>
        <span><i style="background:#f4f4f5;border:1px solid #e4e4e7"></i>Belum tiba</span>
      </div>
    </section>
    <?php endif; ?>

    <!-- ===== RIWAYAT ===== -->
    <section class="card border-0 p-4">
      <h2 class="h5 mb-3">Riwayat pembayaran</h2>
      <?php if ($riwayat): ?>
        <div class="table-responsive">
          <table class="table riwayat align-middle" id="tabel-riwayat">
            <thead>
              <tr><th>Tanggal</th><th>Untuk</th><th>No. Kuitansi</th><th class="text-end">Jumlah</th></tr>
            </thead>
            <tbody>
              <?php foreach ($riwayat as $i => $r): ?>
                <tr class="<?= ($i >= 5) ? 'baris-lebih' : ''; ?>">
                  <td><?= tglIndo($r['tgl_bayar']); ?></td>
                  <td>SPP <?= e($r['bulan_dibayar']); ?> <?= (int) $r['tahun_dibayar']; ?></td>
                  <td class="font-monospace"><?= str_pad($r['id_pembayaran'], 5, '0', STR_PAD_LEFT); ?></td>
                  <td class="text-end fw-semibold"><?= rp($r['jumlah_bayar']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($riwayat) > 5): ?>
          <button type="button" class="btn btn-outline-secondary no-print w-100 mt-2" id="btn-riwayat" data-total="<?= count($riwayat); ?>" style="display:none">
            Tampilkan semua (<?= count($riwayat); ?>)
          </button>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted mb-0">Belum ada pembayaran yang tercatat.</p>
      <?php endif; ?>
    </section>

    <p class="small text-muted mt-4 no-print">
      <i class="bi bi-info-circle" style="color:#DB2777"></i>
      Data ini bersumber langsung dari catatan petugas. Apabila terdapat ketidaksesuaian dengan kuitansi yang Anda miliki, mohon tunjukkan kuitansi tersebut kepada petugas sekolah. Rekap ini dicetak pada <?= tglIndo(date('Y-m-d')); ?>.
    </p>
  </main>

  <footer class="text-center text-muted small pt-4 mt-4 border-top">
    <span>&copy; <?= date('Y'); ?> SPP Digital</span>
  </footer>
</div>

<!-- ===== MODAL KONFIRMASI KELUAR ===== -->
<div class="modal fade" id="modalKeluar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center border-0 shadow" style="border-radius:20px">
      <div class="modal-body p-4">
        <div style="font-size:48px">👋</div>
        <h5 class="mt-2 mb-1">Yakin mau keluar?</h5>
        <p class="text-muted small mb-4">Kamu perlu masukin NISN &amp; NIS lagi buat cek status SPP nanti.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <a href="cek_spp.php?keluar=1" class="btn btn-pink rounded-pill px-4">Ya, Keluar</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/status-spp.js"></script>
</body>
</html>