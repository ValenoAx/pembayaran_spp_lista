<?php

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Fungsi kecil untuk membalas error dalam format JSON lalu berhenti
function wa_gagal($pesan, $kode = 200)
{
    http_response_code($kode);
    echo json_encode(['success' => false, 'message' => $pesan]);
    exit();
}

// Hanya boleh diakses kalau sudah login sebagai admin atau petugas
if (!isset($_SESSION['level']) || !in_array($_SESSION['level'], ['admin', 'petugas'], true)) {
    wa_gagal('Silakan login terlebih dahulu.');
}

// Hanya menerima POST (dari tombol di halaman kuitansi)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wa_gagal('Permintaan tidak valid.', 405);
}

// =====================================================
// ANTI-CSRF: pastikan permintaan benar-benar datang dari website kita sendiri,
// bukan dari halaman jebakan di website lain
// =====================================================
$sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($sfs !== '') {
    if ($sfs !== 'same-origin') {
        wa_gagal('Permintaan ditolak demi keamanan.', 403);
    }
} else {
    $asal = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($asal !== '') {
        $host_asal = parse_url($asal, PHP_URL_HOST);
        $port_asal = parse_url($asal, PHP_URL_PORT);
        $alamat    = $host_asal . ($port_asal ? ':' . $port_asal : '');
        if (!$host_asal || strcasecmp($alamat, $_SERVER['HTTP_HOST'] ?? '') !== 0) {
            wa_gagal('Permintaan ditolak demi keamanan.', 403);
        }
    }
}

include '../koneksi.php'; // sesuaikan jika lokasi koneksi.php beda
include 'config.php';     // <-- token Fonnte sekarang dari sini, bukan hardcode lagi

// =====================================================
// 1) AMBIL ID PEMBAYARAN YANG DIKIRIM DARI HALAMAN KUITANSI
// =====================================================
$ids_mentah = $_POST['ids'] ?? '';
$daftar_id  = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $ids_mentah)))));

// Maksimal 36 transaksi sekaligus (jauh di atas pemakaian normal: 12 bulan)
if (count($daftar_id) === 0 || count($daftar_id) > 36) {
    wa_gagal('ID pembayaran tidak valid.');
}
$ids_sql = implode(',', $daftar_id);

// =====================================================
// 2) AMBIL DATA PEMBAYARAN + NOMOR HP SISWA
//    (query ini sama persis dengan yang dipakai di cetak_pembayaran.php,
//     ditambah kolom siswa.no_telp)
// =====================================================
$q = mysqli_query($koneksi, "
    SELECT pembayaran.*, siswa.nama, siswa.nis, siswa.no_telp, kelas.tingkat, kelas.jurusan
    FROM pembayaran
    JOIN siswa ON pembayaran.nisn = siswa.nisn
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    WHERE pembayaran.id_pembayaran IN ($ids_sql)
    ORDER BY pembayaran.tahun_dibayar ASC, pembayaran.id_pembayaran ASC
");

$daftar      = [];
$total_bayar = 0;
while ($row = mysqli_fetch_assoc($q)) {
    $daftar[] = $row;
    $total_bayar += (int) $row['jumlah_bayar'];
}

if (count($daftar) === 0) {
    wa_gagal('Data pembayaran tidak ditemukan.');
}

$d = $daftar[0];

// =====================================================
// 3) HITUNG PROGRESS TAHUN AJARAN
//    (berapa bulan sudah lunas dari 12 bulan, di tahun ajaran
//    yang sama dengan transaksi ini), supaya kata "LUNAS" di
//    pesan WA tidak disalahartikan sebagai "lunas 1 tahun penuh"
// =====================================================
$nisn_esc = mysqli_real_escape_string($koneksi, $d['nisn']);
$thn_esc  = mysqli_real_escape_string($koneksi, $d['tahun_dibayar']);
$q_progress = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM pembayaran WHERE nisn = '$nisn_esc' AND tahun_dibayar = '$thn_esc'");
$jml_lunas_tahun = (int) (mysqli_fetch_assoc($q_progress)['jml'] ?? 0);
$progress_penuh  = ($jml_lunas_tahun >= 12);

// =====================================================
// 4) VALIDASI NOMOR HP
// =====================================================
if (empty($d['no_telp'])) {
    wa_gagal('Nomor HP siswa ini belum diisi di database, jadi tidak bisa dikirim otomatis.');
}

// Rapikan format nomor: hilangkan spasi/strip, pastikan mulai dengan 62 (bukan 0)
$nomor_wa = preg_replace('/[^0-9]/', '', $d['no_telp']);
if (substr($nomor_wa, 0, 1) === '0') {
    $nomor_wa = '62' . substr($nomor_wa, 1);
}

// =====================================================
// 5) SUSUN ISI PESAN
// =====================================================
$satu_transaksi = (count($daftar) === 1);

if ($satu_transaksi) {
    $keterangan_bulan = "SPP Bulan " . $d['bulan_dibayar'] . " " . $d['tahun_dibayar'];
} else {
    $list_bulan = array_map(function ($t) {
        return $t['bulan_dibayar'] . ' (Rp ' . number_format($t['jumlah_bayar'], 0, ',', '.') . ')';
    }, $daftar);
    $keterangan_bulan = "SPP " . count($daftar) . " bulan sekaligus: " . implode(', ', $list_bulan);
}

// Baris "Status" dibuat beda tergantung apakah transaksi ini
// menggenapkan 12/12 bulan tahun ajaran atau belum, supaya kata
// "LUNAS" tidak disalahartikan sebagai "lunas 1 tahun penuh"
// padahal baru sebagian bulan yang dibayar.
if ($progress_penuh) {
    $baris_status = "Status : LUNAS (12 dari 12 bulan tahun ajaran ini sudah lunas semua)";
} else {
    $baris_status = "Status : LUNAS untuk pembayaran ini\n"
                  . "Progress: " . $jml_lunas_tahun . " dari 12 bulan tahun ajaran ini sudah lunas";
}

$pesan  = "Assalamu'alaikum,\n\n";
$pesan .= "Berikut kami sampaikan bukti pembayaran SPP:\n\n";
$pesan .= "Nama   : " . $d['nama'] . "\n";
$pesan .= "Kelas  : " . $d['tingkat'] . " " . $d['jurusan'] . "\n";
$pesan .= "Untuk  : " . $keterangan_bulan . "\n";
$pesan .= "Total  : Rp " . number_format($total_bayar, 0, ',', '.') . "\n";
$pesan .= $baris_status . "\n\n";
$pesan .= "Terima kasih.\n- SPP Digital -";

// =====================================================
// 5b) BATAS PENGIRIMAN: maksimal 10 pesan per menit per login,
//     supaya tombol ini tidak bisa dipakai untuk spam / menghabiskan kuota Fonnte
// =====================================================
$sekarang = time();
$_SESSION['wa_log'] = array_values(array_filter($_SESSION['wa_log'] ?? [], function ($t) use ($sekarang) {
    return $t > $sekarang - 60;
}));
if (count($_SESSION['wa_log']) >= 10) {
    wa_gagal('Terlalu sering mengirim pesan. Tunggu sebentar lalu coba lagi.', 429);
}
$_SESSION['wa_log'][] = $sekarang;

// =====================================================
// 6) KIRIM KE FONNTE
// =====================================================
$ch = curl_init(FONNTE_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);   // jangan menunggu selamanya
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'target'      => $nomor_wa,
    'message'     => $pesan,
    'countryCode' => '62',
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . FONNTE_TOKEN]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    // Detail teknis hanya dicatat di log server, tidak ditampilkan ke browser
    error_log('Fonnte curl error: ' . $curlErr);
    wa_gagal('Koneksi ke layanan WhatsApp gagal. Coba lagi beberapa saat lagi.');
}

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['status']) && $result['status'] === true) {
    echo json_encode(['success' => true, 'message' => 'Kuitansi berhasil dikirim ke WhatsApp (' . $nomor_wa . ').']);
} else {
    error_log('Fonnte gagal (HTTP ' . $httpCode . '): ' . $response);
    wa_gagal('Gagal mengirim pesan ke layanan WhatsApp.');
}