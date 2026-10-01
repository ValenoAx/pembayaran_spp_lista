<?php
// login.php membuat cookie session, jadi pengamanan cookie-nya diatur DI SINI:
//  httponly : cookie nggak bisa dibaca JavaScript (melawan pencurian session lewat XSS)
//  samesite : cookie nggak ikut terkirim dari website lain
//  strict   : PHP menolak session id karangan orang lain
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
    'use_strict_mode' => true,
]);
header('X-Frame-Options: SAMEORIGIN');      // halaman login nggak bisa dimasukin iframe web lain
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

include 'koneksi.php';

// =====================================================
// PEMBATAS PERCOBAAN LOGIN (anti brute force / password spraying)
// Percobaan GAGAL dicatat di file sementara server.
//  - maksimal 5x gagal untuk kombinasi (perangkat + username yang sama)
//  - maksimal 20x gagal dari satu perangkat (menahan tebak-tebakan banyak username)
// Setelah itu dikunci 15 menit. Admin asli dari perangkat lain tetap bisa login.
// =====================================================
const LOGIN_MAKS_AKUN = 5;
const LOGIN_MAKS_IP   = 20;
const LOGIN_JENDELA   = 900; // detik (15 menit)

function login_file($kunci)
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spp_login_' . md5($kunci) . '.txt';
}

function login_baca($kunci)
{
    $f = login_file($kunci);
    if (!is_file($f)) {
        return [];
    }
    $batas = time() - LOGIN_JENDELA;
    $waktu = array_map('intval', file($f, FILE_IGNORE_NEW_LINES) ?: []);
    return array_values(array_filter($waktu, function ($t) use ($batas) { return $t > $batas; }));
}

function login_catat($kunci)
{
    $waktu   = login_baca($kunci);
    $waktu[] = time();
    @file_put_contents(login_file($kunci), implode("\n", $waktu), LOCK_EX);
}

function login_hapus($kunci)
{
    @unlink(login_file($kunci));
}

// Mengembalikan sisa detik terkunci (0 = tidak terkunci)
function login_sisa_kunci($ip, $user)
{
    $sisa = 0;
    $cek  = [['ip:' . $ip, LOGIN_MAKS_IP], ['akun:' . $ip . '|' . $user, LOGIN_MAKS_AKUN]];
    foreach ($cek as $c) {
        $waktu = login_baca($c[0]);
        if (count($waktu) >= $c[1]) {
            $sisa = max($sisa, min($waktu) + LOGIN_JENDELA - time());
        }
    }
    return $sisa;
}

$error = "";

// Token anti-CSRF untuk form login (dikirim lewat input tersembunyi)
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if (isset($_POST['login'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'x';
    $user_key = strtolower($username);
    $kunci_ip   = 'ip:' . $ip;
    $kunci_akun = 'akun:' . $ip . '|' . $user_key;

    if (!hash_equals($_SESSION['csrf_login'], (string) ($_POST['csrf'] ?? ''))) {
        $error = "Sesi login kedaluwarsa. Muat ulang halaman lalu coba lagi.";
    } elseif (($sisa = login_sisa_kunci($ip, $user_key)) > 0) {
        $error = "Terlalu banyak percobaan login. Coba lagi sekitar " . max(1, (int) ceil($sisa / 60)) . " menit lagi.";
    } else {
        // Prepared statement -> aman dari SQL Injection
        $stmt = mysqli_prepare($koneksi, "SELECT * FROM petugas WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = mysqli_fetch_assoc($result);

        $berhasil = false;
        if ($row && strlen($password) <= 1000) {
            // Cocokkan password dengan hash yang tersimpan di DB
            $berhasil = password_verify($password, $row['password']);
        } else {
            // Username tidak ada: tetap hitung hash supaya waktu respons mirip,
            // jadi orang luar tidak bisa menebak username mana yang terdaftar.
            password_hash(substr($password, 0, 72), PASSWORD_DEFAULT);
        }

        if ($berhasil && in_array($row['level'], ['admin', 'petugas'], true)) {
            login_hapus($kunci_akun);

            // Regenerate session ID -> mencegah session fixation
            session_regenerate_id(true);
            unset($_SESSION['csrf_login']);

            $_SESSION['username']     = $row['username'];
            $_SESSION['nama_petugas'] = $row['nama_petugas'];
            $_SESSION['level']        = $row['level'];

            if ($row['level'] === 'admin') {
                header("location: backend/admin/index.php");
                exit();
            } else {
                header("location: frontend/petugas/index.php");
                exit();
            }
        } else {
            login_catat($kunci_ip);
            login_catat($kunci_akun);
            $error = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Aplikasi Pembayaran SPP</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2248%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2230%22%20y%3D%2220%22%20width%3D%2240%22%20height%3D%2260%22%20rx%3D%226%22%20fill%3D%22%23ffffff%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2230%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2240%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2250%22%20width%3D%2218%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Ccircle%20cx%3D%2263%22%20cy%3D%2266%22%20r%3D%2212%22%20fill%3D%22%23DB2777%22/%3E%3Cpath%20d%3D%22M58%2066%20L61.5%2069.5%20L68.5%2062.5%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

  <div class="stage">
    <div class="card">

      <div class="brand">
        <div class="mark">
          <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
            <rect x="2" y="2" width="22" height="22" rx="6" stroke="#FBEAF4" stroke-width="1.6"/>
            <path d="M7 9.5H19M7 13H16M7 16.5H13" stroke="#FBEAF4" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          SPP Digital
        </div>

        <h1>Pembayaran SPP,<br>tercatat rapi.</h1>
        <p>Masuk untuk mengelola transaksi, mencetak kuitansi, dan memantau status pembayaran siswa.</p>

        <div class="stub-wrap">
          <div class="stub">
            <div class="stub-top">
              <span class="stub-brand">SPP DIGITAL</span>
              <span class="stub-tag">LUNAS</span>
            </div>
            <div class="stub-row"><span>Bulan</span><span>September</span></div>
            <div class="stub-row"><span>Kelas</span><span>12 PPLG 1</span></div>
            <div class="stub-total">Rp 900.000</div>
          </div>
        </div>
      </div>

      <div class="form-panel">
        <h2>Masuk ke akun</h2>
        <p class="sub">Khusus untuk admin dan petugas sekolah.</p>

        <?php if ($error): ?>
          <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_login'] ?? '') ?>">
          <div class="field">
            <label for="username">Username</label>
            <div class="control">
              <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
            </div>
          </div>

          <div class="field">
            <label for="password">Kata sandi</label>
            <div class="control">
              <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
              <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan kata sandi" aria-pressed="false">
                <svg id="eyeIcon" width="19" height="19" viewBox="0 0 24 24" fill="none">
                  <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/>
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" name="login" class="submit">Masuk</button>
        </form>

        <p class="hint">Lupa kata sandi? Hubungi admin sekolah.</p>
      </div>

    </div>
  </div>

  <script>
    const toggle = document.getElementById('togglePass');
    const passInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    toggle.addEventListener('click', () => {
      const showing = passInput.type === 'text';
      passInput.type = showing ? 'password' : 'text';
      toggle.setAttribute('aria-pressed', String(!showing));
      toggle.setAttribute('aria-label', showing ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
      eyeIcon.innerHTML = showing
        ? '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/>'
        : '<path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.2A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.9 17.9 0 0 1-3.2 4.1M6.2 6.2A17.7 17.7 0 0 0 2 12s3.6 7 10 7c1.2 0 2.3-.2 3.3-.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>';
    });
  </script>

</body>
</html>