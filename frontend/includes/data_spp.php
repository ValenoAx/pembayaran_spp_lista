<?php
/**
 * frontend/includes/data_spp.php — Semua urusan database & perhitungan status SPP
 * LETAKKAN DI: pembayaran_spp/frontend/includes/data_spp.php
 *
 * Isinya cuma fungsi. Tidak ada HTML dan tidak ada redirect di sini.
 */

// Cari siswa lewat NISN saja (dipakai untuk verifikasi NISN + NIS)
function cariSiswaByNisn($koneksi, $nisn)
{
    $stmt = mysqli_prepare($koneksi, "SELECT nisn, nis FROM siswa WHERE nisn = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $nisn);
    mysqli_stmt_execute($stmt);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $baris ?: null;
}

// Data lengkap siswa + kelasnya
function ambilProfilSiswa($koneksi, $nisn)
{
    $stmt = mysqli_prepare($koneksi, "
        SELECT siswa.nisn, siswa.nis, siswa.nama, siswa.tahun_masuk, kelas.tingkat, kelas.jurusan
        FROM siswa JOIN kelas ON siswa.id_kelas = kelas.id_kelas
        WHERE siswa.nisn = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $nisn);
    mysqli_stmt_execute($stmt);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $baris ?: null;
}

// Tarif SPP per tahun -> [2024 => 150000, 2025 => 175000, ...]
function ambilTarifSpp($koneksi)
{
    $tarif = [];
    $q = mysqli_query($koneksi, "SELECT tahun, nominal FROM spp ORDER BY tahun ASC");
    while ($r = mysqli_fetch_assoc($q)) {
        $tarif[(int) $r['tahun']] = (int) $r['nominal'];
    }
    return $tarif;
}

// Semua pembayaran satu siswa.
// Hasil: ['bayar' => [tahun][bulan] => jumlah & tanggal, 'riwayat' => daftar transaksi, 'total' => total rupiah]
function ambilPembayaranSiswa($koneksi, $nisn)
{
    $bayar = [];
    $riwayat = [];
    $total = 0;

    $stmt = mysqli_prepare($koneksi, "
        SELECT id_pembayaran, tgl_bayar, bulan_dibayar, tahun_dibayar, jumlah_bayar
        FROM pembayaran WHERE nisn = ?
        ORDER BY tgl_bayar DESC, id_pembayaran DESC");
    mysqli_stmt_bind_param($stmt, "s", $nisn);
    mysqli_stmt_execute($stmt);
    $q = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($q)) {
        $th = (int) $r['tahun_dibayar'];
        $bl = $r['bulan_dibayar'];
        if (!isset($bayar[$th][$bl])) { $bayar[$th][$bl] = ['jumlah' => 0, 'tgl' => $r['tgl_bayar']]; }
        $bayar[$th][$bl]['jumlah'] += (int) $r['jumlah_bayar'];
        $riwayat[] = $r;
        $total += (int) $r['jumlah_bayar'];
    }
    mysqli_stmt_close($stmt);

    return ['bayar' => $bayar, 'riwayat' => $riwayat, 'total' => $total];
}

// Label, kelas warna CSS, dan ikon untuk tiap status bulan
function labelStatusSpp()
{
    return [
        'lunas'    => ['Lunas', 'bln-lunas', 'bi-check-circle-fill'],
        'tunggak'  => ['Tunggakan', 'bln-tunggak', 'bi-exclamation-circle-fill'],
        'jalan'    => ['Bulan berjalan', 'bln-jalan', 'bi-record-circle'],
        'depan'    => ['Belum tiba', 'bln-depan', 'bi-dash-circle'],
        'sebagian' => ['Terbayar sebagian', 'bln-sebagian', 'bi-hourglass-split'],
    ];
}

// Hitung status 12 bulan untuk setiap tahun ajaran siswa.
// Hasil: daftar_tahun, tab_awal, data_tahun, total_lunas, jml_tunggak, rp_tunggak
function susunStatusSpp($siswa, $tarif, $bayar)
{
    $TAHUN_INI = (int) date('Y');
    $BULAN_INI = (int) date('n');

    // Tahun yang ditampilkan: 3 tahun sejak masuk + tahun yang pernah ada pembayarannya
    $masuk = (int) $siswa['tahun_masuk'];
    $tahun = [];
    for ($t = $masuk; $t <= $masuk + 2; $t++) {
        if (isset($tarif[$t])) { $tahun[$t] = true; }
    }
    foreach (array_keys($bayar) as $t) {
        if (isset($tarif[$t])) { $tahun[$t] = true; }
    }
    $daftar_tahun = array_keys($tahun);
    sort($daftar_tahun);

    // Tab yang terbuka pertama = tahun paling dekat dengan tahun sekarang
    $tab_awal = null;
    $jarak = PHP_INT_MAX;
    foreach ($daftar_tahun as $t) {
        if (abs($t - $TAHUN_INI) < $jarak) { $jarak = abs($t - $TAHUN_INI); $tab_awal = $t; }
    }

    $data_tahun  = [];
    $total_lunas = 0;
    $jml_tunggak = 0;
    $rp_tunggak  = 0;

    foreach ($daftar_tahun as $th) {
        $nominal = $tarif[$th];
        $rows  = [];
        $lunas = 0;
        foreach (DAFTAR_BULAN as $ke => $nama) {
            $sudah = $bayar[$th][$nama]['jumlah'] ?? 0;
            $tgl   = $bayar[$th][$nama]['tgl'] ?? null;
            $lewat = ($th < $TAHUN_INI) || ($th == $TAHUN_INI && $ke < $BULAN_INI);
            $jalan = ($th == $TAHUN_INI && $ke == $BULAN_INI);

            if ($sudah > 0 && $sudah >= $nominal) {
                $st = 'lunas'; $lunas++;
            } elseif ($sudah > 0) {
                $st = 'sebagian';
            } elseif ($lewat) {
                $st = 'tunggak'; $jml_tunggak++; $rp_tunggak += $nominal;
            } elseif ($jalan) {
                $st = 'jalan';
            } else {
                $st = 'depan';
            }
            $rows[] = ['nama' => $nama, 'status' => $st, 'tgl' => $tgl, 'sudah' => $sudah];
        }
        $total_lunas += $lunas;
        $data_tahun[$th] = ['nominal' => $nominal, 'bulan' => $rows, 'lunas' => $lunas];
    }

    return [
        'daftar_tahun' => $daftar_tahun,
        'tab_awal'     => $tab_awal,
        'data_tahun'   => $data_tahun,
        'total_lunas'  => $total_lunas,
        'jml_tunggak'  => $jml_tunggak,
        'rp_tunggak'   => $rp_tunggak,
    ];
}