<?php
/**
 * frontend/petugas/components/header.php
 * Shell "gaya frontend" untuk panel Petugas: navbar atas (bukan sidebar admin),
 * pakai asset yang sama dengan frontend/index.php supaya nyatu sama halaman publik.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF']);
$pageTitle   = $pageTitle ?? 'Aplikasi Pembayaran SPP';

function navAktifPetugas($currentPage, $daftar) {
    return in_array($currentPage, $daftar, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle); ?></title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2248%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2230%22%20y%3D%2220%22%20width%3D%2240%22%20height%3D%2260%22%20rx%3D%226%22%20fill%3D%22%23ffffff%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2230%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2240%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2250%22%20width%3D%2218%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Ccircle%20cx%3D%2263%22%20cy%3D%2266%22%20r%3D%2212%22%20fill%3D%22%23DB2777%22/%3E%3Cpath%20d%3D%22M58%2066%20L61.5%2069.5%20L68.5%2062.5%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22/%3E%3C/svg%3E">
<link href="https://fonts.googleapis.com" rel="preconnect">
<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<link href="../assets/css/custom.css" rel="stylesheet">
<style>
html, body{ height:100%; }
  body{
    background:#fdf2f8;
    min-height:100vh;
    display:flex;
    flex-direction:column;
  }
  .petugas-main{ flex:1 0 auto; }
  .petugas-topbar{ flex:0 0 auto; }  .petugas-topbar{ background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.06); }
  .petugas-topbar .brand{ font-weight:800; color:#3b0a2b; text-decoration:none; display:flex; align-items:center; }
  .petugas-topbar .brand small{ font-weight:500; color:#9d174d; margin-left:.4rem; }
  .petugas-topbar .nav-link{ font-weight:600; color:#3b0a2b; border-radius:999px; padding:.5rem 1rem; white-space:nowrap; }
  .petugas-topbar .nav-link.active,
  .petugas-topbar .nav-link:hover{ background:#fce7f3; color:#DB2777; }
  .petugas-main{ min-height: 70vh; }
</style>
</head>
<body>

<header class="petugas-topbar sticky-top">
  <div class="container-fluid container-xl d-flex align-items-center justify-content-between py-2 flex-wrap gap-2">
    <a href="index.php" class="brand">
      <i class="bi bi-file-earmark-text-fill fs-4" style="color:#DB2777"></i>
      <span class="ms-2">SPP Digital</span><small>Panel Petugas</small>
    </a>

    <nav class="d-flex align-items-center flex-wrap gap-1">
      <a href="index.php" class="nav-link <?= navAktifPetugas($currentPage, ['index.php']); ?>">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
      <a href="transaksi.php" class="nav-link <?= navAktifPetugas($currentPage, ['transaksi.php', 'detail_pembayaran.php', 'cetak_pembayaran.php']); ?>">
        <i class="bi bi-wallet2 me-1"></i>Entri Pembayaran
      </a>
      <a href="history.php" class="nav-link <?= navAktifPetugas($currentPage, ['history.php', 'detail_history.php']); ?>">
        <i class="bi bi-clock-history me-1"></i>History
      </a>
      <a href="setting.php" class="nav-link <?= navAktifPetugas($currentPage, ['setting.php']); ?>">
        <i class="bi bi-gear me-1"></i>Pengaturan
      </a>
    </nav>

    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small d-none d-lg-inline">
        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['nama_petugas'] ?? ''); ?>
      </span>
      <a href="../../backend/petugas/index.php" class="btn btn-sm btn-outline-secondary rounded-pill d-none d-md-inline-flex align-items-center" title="Buka tampilan panel lama">
        <i class="bi bi-layout-sidebar-inset me-1"></i>Tampilan Backend
      </a>
      <a href="#" data-bs-toggle="modal" data-bs-target="#modalLogout" class="btn btn-sm btn-outline-danger rounded-pill">
        <i class="bi bi-box-arrow-right me-1"></i>Logout
      </a>
    </div>
  </div>
</header>

<main class="petugas-main container-xl py-4">