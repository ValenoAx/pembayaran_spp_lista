<?php
require_once __DIR__ . '/../keamanan.php';
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='../../login.php';</script>";
    exit();
}
include '../../koneksi.php';

$error = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi    = $_POST['konfirmasi_password'] ?? '';
    $username      = $_SESSION['username'];

    // Ambil password saat ini dari database
    $stmt = mysqli_prepare($koneksi, "SELECT password FROM petugas WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($password_lama, $row['password'])) {
        $error = 'Password lama salah.';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($password_baru !== $konfirmasi) {
        $error = 'Konfirmasi password baru tidak cocok.';
    } else {
        $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($koneksi, "UPDATE petugas SET password = ?, password_updated_at = NOW() WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "ss", $hash_baru, $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $sukses = 'Password berhasil diubah. Gunakan password baru saat login berikutnya.';
    }
}

$pageTitle   = 'Pengaturan - Aplikasi Pembayaran SPP';
$currentPage = 'setting.php';
include __DIR__ . '/components/header.php';
include __DIR__ . '/components/sidebar.php';

// ================== TAMBAHAN: inisial dari username, buat avatar di header ==================
$inisial_akun = strtoupper(substr($_SESSION['username'], 0, 2));
?>

    <style>
        /* ================== TAMBAHAN: gaya khusus halaman Pengaturan Akun ================== */
        .header-akun {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            border-radius: 20px;
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .avatar-akun-besar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background-color: #db2777;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(219, 39, 119, 0.35);
        }

        .kartu-ganti-password {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 18px rgba(219, 39, 119, 0.08);
        }

        .input-group-cantik .input-group-text {
            background-color: #fdf2f8;
            border: 1px solid #f3d3e6;
            border-right: none;
            color: #db2777;
        }
        .input-group-cantik .form-control {
            border: 1px solid #f3d3e6;
            border-left: none;
        }
        .input-group-cantik .form-control:focus {
            box-shadow: none;
            border-color: #db2777;
        }
        .input-group-cantik .btn-toggle-mata {
            border: 1px solid #f3d3e6;
            border-left: none;
            background-color: #fff;
            color: #9d174d;
        }

        /* Indikator kekuatan password */
        #meter-kekuatan {
            height: 6px;
            border-radius: 999px;
            background-color: #f3e8ee;
            overflow: hidden;
            margin-top: 0.4rem;
        }
        #meter-kekuatan-bar {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            transition: width .25s ease, background-color .25s ease;
        }
        #label-kekuatan { font-size: 0.78rem; margin-top: 0.25rem; }

        .btn-simpan-cantik {
            background: linear-gradient(135deg, #db2777, #be185d);
            border: none;
            border-radius: 12px;
            padding: 0.6rem 1.6rem;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(219, 39, 119, 0.3);
        }
    </style>

    <!-- ================== TAMBAHAN: header sapaan dengan avatar ================== -->
    <div class="header-akun">
        <div class="avatar-akun-besar"><?= $inisial_akun; ?></div>
        <div>
            <h1 class="h4 fw-bold mb-1" style="color: #db2777;">👋 Halo, <?= htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="text-muted mb-0">Kelola keamanan akunmu, ganti password secara berkala ya.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7 col-lg-6">
            <div class="card kartu-ganti-password">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">🔐 Ganti Password</h5>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($sukses): ?>
                        <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($sukses); ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group input-group-cantik">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($_SESSION['username']); ?>" disabled readonly>
                        </div>
                        <div class="form-text"><i class="bi bi-lock-fill me-1"></i>Username tidak bisa diubah.</div>
                    </div>

                    <form method="POST" action="setting.php">
                        <div class="mb-3">
                            <label for="password_lama" class="form-label fw-semibold">Password Lama</label>
                            <div class="input-group input-group-cantik">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                                <button type="button" class="btn btn-toggle-mata" onclick="toggleMata('password_lama', this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-1">
                            <label for="password_baru" class="form-label fw-semibold">Password Baru</label>
                            <div class="input-group input-group-cantik">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" class="form-control" id="password_baru" name="password_baru" minlength="6" required oninput="cekKekuatanPassword(this.value)">
                                <button type="button" class="btn btn-toggle-mata" onclick="toggleMata('password_baru', this)"><i class="bi bi-eye"></i></button>
                            </div>
                            <div id="meter-kekuatan"><div id="meter-kekuatan-bar"></div></div>
                            <div id="label-kekuatan" class="text-muted">Minimal 6 karakter</div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="konfirmasi_password" class="form-label fw-semibold">Konfirmasi Password Baru</label>
                            <div class="input-group input-group-cantik">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" minlength="6" required>
                                <button type="button" class="btn btn-toggle-mata" onclick="toggleMata('konfirmasi_password', this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" name="ganti_password" class="btn text-white btn-simpan-cantik"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ================== TAMBAHAN: tombol show/hide password (ikon mata) ==================
        function toggleMata(idInput, tombol) {
            var input = document.getElementById(idInput);
            var ikon  = tombol.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                ikon.classList.remove('bi-eye');
                ikon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                ikon.classList.remove('bi-eye-slash');
                ikon.classList.add('bi-eye');
            }
        }

        // ================== TAMBAHAN: indikator kekuatan password (lemah/sedang/kuat) ==================
        // Ini cuma bantuan visual di sisi tampilan; aturan wajib (minimal 6 karakter, dst)
        // tetap dicek di server seperti sebelumnya, jadi tidak mengubah validasi asli.
        function cekKekuatanPassword(nilai) {
            var bar   = document.getElementById('meter-kekuatan-bar');
            var label = document.getElementById('label-kekuatan');
            var skor  = 0;

            if (nilai.length >= 6)  skor++;
            if (nilai.length >= 10) skor++;
            if (/[A-Z]/.test(nilai) && /[a-z]/.test(nilai)) skor++;
            if (/[0-9]/.test(nilai)) skor++;
            if (/[^A-Za-z0-9]/.test(nilai)) skor++;

            if (nilai.length === 0) {
                bar.style.width = '0%';
                label.textContent = 'Minimal 6 karakter';
                label.className = 'text-muted';
                return;
            }

            var persen, warna, teks, kelasTeks;
            if (skor <= 1) {
                persen = 25; warna = '#ef4444'; teks = 'Lemah'; kelasTeks = 'text-danger';
            } else if (skor <= 3) {
                persen = 60; warna = '#f59e0b'; teks = 'Sedang'; kelasTeks = 'text-warning';
            } else {
                persen = 100; warna = '#16a34a'; teks = 'Kuat'; kelasTeks = 'text-success';
            }

            bar.style.width = persen + '%';
            bar.style.backgroundColor = warna;
            label.textContent = 'Kekuatan password: ' + teks;
            label.className = kelasTeks;
        }
    </script>

    <?php include __DIR__ . '/components/footer.php'; ?>