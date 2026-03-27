<?php
require_once '../includes/config.php';
requireLogin();

$bulan = intval($_GET['bulan'] ?? date('m'));
$tahun = intval($_GET['tahun'] ?? date('Y'));
$karyawan_id = intval($_GET['karyawan_id'] ?? 0);

// Karyawan hanya bisa export data sendiri
if ($_SESSION['role'] !== 'admin') {
    $karyawan_id = $_SESSION['user_id'];
}

// Build query
if ($karyawan_id > 0) {
    $stmt = $pdo->prepare("SELECT k.nik, k.nama, a.tanggal, a.jam_masuk, a.jam_keluar,
        a.status_kehadiran, a.terlambat_detik, a.durasi_kerja,
        a.lat_masuk, a.lng_masuk, a.jarak_masuk,
        a.lat_keluar, a.lng_keluar, a.jarak_keluar, a.keterangan
        FROM absensi a
        JOIN karyawan k ON a.karyawan_id = k.id
        WHERE a.karyawan_id = ? AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
        ORDER BY a.tanggal ASC");
    $stmt->execute([$karyawan_id, $bulan, $tahun]);
} else {
    // Admin export semua karyawan
    $stmt = $pdo->prepare("SELECT k.nik, k.nama, a.tanggal, a.jam_masuk, a.jam_keluar,
        a.status_kehadiran, a.terlambat_detik, a.durasi_kerja,
        a.lat_masuk, a.lng_masuk, a.jarak_masuk,
        a.lat_keluar, a.lng_keluar, a.jarak_keluar, a.keterangan
        FROM absensi a
        JOIN karyawan k ON a.karyawan_id = k.id
        WHERE MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
        ORDER BY k.nama ASC, a.tanggal ASC");
    $stmt->execute([$bulan, $tahun]);
}

$rows = $stmt->fetchAll();

// Get month name
$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$filename = "rekap_absensi_{$bulanNama[$bulan]}_{$tahun}";
if ($karyawan_id > 0) {
    $stmt2 = $pdo->prepare("SELECT nama FROM karyawan WHERE id = ?");
    $stmt2->execute([$karyawan_id]);
    $k = $stmt2->fetch();
    if ($k) $filename .= '_' . preg_replace('/[^a-z0-9]/i', '_', $k['nama']);
}
$filename .= '.csv';

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

$output = fopen('php://output', 'w');

// BOM for UTF-8 Excel compatibility
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'NIK', 'Nama Karyawan', 'Tanggal', 'Hari',
    'Jam Masuk', 'Jam Keluar',
    'Status', 'Terlambat',
    'Durasi Kerja (Menit)',
    'Lat Masuk', 'Lng Masuk', 'Jarak Masuk (m)',
    'Lat Keluar', 'Lng Keluar', 'Jarak Keluar (m)',
    'Keterangan'
]);

$hariNama = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

foreach ($rows as $row) {
    $ts = strtotime($row['tanggal']);
    $hari = $hariNama[date('w', $ts)];
    
    // Format terlambat
    $terlambat = '';
    if ($row['terlambat_detik'] > 0) {
        $t = $row['terlambat_detik'];
        $jam = floor($t / 3600);
        $menit = floor(($t % 3600) / 60);
        $detik = $t % 60;
        $parts = [];
        if ($jam) $parts[] = "{$jam}j";
        if ($menit) $parts[] = "{$menit}m";
        if ($detik) $parts[] = "{$detik}d";
        $terlambat = implode(' ', $parts);
    }
    
    $statusLabel = [
        'hadir' => 'Hadir',
        'terlambat' => 'Terlambat',
        'alpha' => 'Alpha',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'libur' => 'Libur',
    ][$row['status_kehadiran']] ?? $row['status_kehadiran'];
    
    fputcsv($output, [
        $row['nik'],
        $row['nama'],
        date('d/m/Y', $ts),
        $hari,
        $row['jam_masuk'] ?? '-',
        $row['jam_keluar'] ?? '-',
        $statusLabel,
        $terlambat ?: '-',
        $row['durasi_kerja'] ?? '-',
        $row['lat_masuk'] ?? '',
        $row['lng_masuk'] ?? '',
        $row['jarak_masuk'] ?? '',
        $row['lat_keluar'] ?? '',
        $row['lng_keluar'] ?? '',
        $row['jarak_keluar'] ?? '',
        $row['keterangan'] ?? '',
    ]);
}

fclose($output);
exit;
