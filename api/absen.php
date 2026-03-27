<?php
@ini_set('post_max_size', '16M');
@ini_set('upload_max_filesize', '16M');

require_once __DIR__ . '/../includes/config.php';
requireLogin();

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true);
$type      = $input['type']      ?? '';
$lat       = (float)($input['lat']  ?? 0);
$lng       = (float)($input['lng']  ?? 0);
$foto      = $input['foto']      ?? '';
$accuracy  = (float)($input['accuracy']  ?? 9999);   // akurasi GPS dari browser (meter)
$timestamp = (int)($input['timestamp']   ?? 0);       // timestamp GPS dari browser (ms)
$altitude  = $input['altitude']  ?? null;             // null jika tidak tersedia
$speed     = $input['speed']     ?? null;             // kecepatan (m/s), null jika tidak tersedia

if (!in_array($type, ['masuk', 'keluar']) || !$lat || !$lng) {
    jsonResponse(['success' => false, 'message' => 'Data tidak lengkap']);
}

$db    = getDB();
$user  = currentUser();
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

// ================================================================
// LAYER 1: Validasi akurasi GPS
// Akurasi > 100m = GPS tidak presisi (kemungkinan pakai WiFi/mock)
// ================================================================
$MAX_ACCURACY = 100; // meter
if ($accuracy > $MAX_ACCURACY) {
    jsonResponse([
        'success' => false,
        'message' => "Akurasi GPS terlalu rendah ({$accuracy}m). Pastikan GPS aktif dan berada di area terbuka. Batas akurasi: {$MAX_ACCURACY}m.",
        'flag'    => 'low_accuracy'
    ]);
}

// ================================================================
// LAYER 2: Validasi GPS age (berapa detik sejak posisi diambil)
// Lebih andal dari timestamp karena pakai gpsAge yang dihitung di JS
// ================================================================
$gpsAge = (float)($input['gps_age'] ?? 0); // detik, dihitung di JS
if ($gpsAge > 300) { // > 5 menit = GPS stale
    jsonResponse([
        'success' => false,
        'message' => 'Data GPS terlalu lama (' . round($gpsAge/60) . ' menit). Refresh halaman dan tunggu GPS update.',
        'flag'    => 'gps_stale'
    ]);
}

// ================================================================
// LAYER 3: Deteksi kecepatan tidak wajar
// Speed > 50 m/s (180 km/jam) saat absen = tidak wajar
// ================================================================
if ($speed !== null && $speed > 50) {
    jsonResponse([
        'success' => false,
        'message' => 'Terdeteksi pergerakan tidak wajar. Absensi tidak dapat dilakukan saat bergerak cepat.',
        'flag'    => 'speed_anomaly'
    ]);
}

// ================================================================
// LAYER 4: Validasi koordinat tidak terlalu "sempurna"
// Koordinat mock GPS sering berakhir .000000 atau bulat sempurna
// ================================================================
$latStr = (string)$lat;
$lngStr = (string)$lng;
$latDec = strlen(explode('.', $latStr)[1] ?? '');
$lngDec = strlen(explode('.', $lngStr)[1] ?? '');
if ($latDec < 4 || $lngDec < 4) {
    jsonResponse([
        'success' => false,
        'message' => 'Koordinat GPS tidak valid. Pastikan GPS aktif dan tidak menggunakan lokasi palsu.',
        'flag'    => 'suspicious_coords'
    ]);
}

// ================================================================
// LAYER 5: Rate limiting — cegah spam request absen
// Maksimal 3 percobaan per 10 menit
// ================================================================
$windowStart = date('Y-m-d H:i:s', strtotime('-10 minutes'));
$stmtRate = $db->prepare("SELECT COUNT(*) as cnt FROM log_aktivitas 
    WHERE karyawan_id=? AND aksi LIKE 'ABSEN%' AND created_at >= ?");
$stmtRate->execute([$user['id'], $windowStart]);
$rateCount = $stmtRate->fetch()['cnt'];
if ($rateCount >= 5) {
    jsonResponse([
        'success' => false,
        'message' => 'Terlalu banyak percobaan absen. Coba lagi dalam beberapa menit.',
        'flag'    => 'rate_limit'
    ]);
}

// ================================================================
// LAYER 6: Cek apakah IP/device sudah digunakan akun lain hari ini
// Mencegah 1 HP untuk absen banyak orang
// ================================================================
$ipAddr    = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmtIP = $db->prepare("SELECT COUNT(DISTINCT karyawan_id) as cnt FROM absensi 
    WHERE tanggal=? AND ip_address=? AND karyawan_id != ?");
$stmtIP->execute([$today, $ipAddr, $user['id']]);
$ipCount = $stmtIP->fetch()['cnt'];
if ($ipCount >= 3) {
    // Lebih dari 3 orang berbeda absen dari IP yang sama — flag sebagai suspicious
    // Tapi tetap lanjutkan, hanya simpan flag (tidak block, karena bisa WiFi kantor)
    $suspiciousIP = true;
} else {
    $suspiciousIP = false;
}

// ================================================================
// LAYER 7: Simpan foto (wajib)
// ================================================================
$fotoPath = null;
if ($foto && strpos($foto, 'data:image/') === 0) {
    $fotoPath = $foto;
} else {
    jsonResponse(['success' => false, 'message' => 'Foto wajah tidak ditemukan. Harap ulangi proses absensi.']);
}

// ================================================================
// Ambil jadwal aktif
// ================================================================
$stmtJadwal = $db->prepare("SELECT jk.*, j.hari_kerja, j.id as jadwal_real_id,
    s.id as shift_id, s.jam_masuk, s.jam_keluar, s.toleransi_terlambat_detik,
    l.id as lokasi_id, l.latitude, l.longitude, l.radius_meter
    FROM jadwal_karyawan jk 
    JOIN jadwal j ON j.id = jk.jadwal_id 
    JOIN shift s ON s.id = j.shift_id 
    JOIN lokasi l ON l.id = j.lokasi_id 
    WHERE jk.karyawan_id = ? 
    AND jk.berlaku_dari <= CURDATE() 
    AND (jk.berlaku_sampai IS NULL OR jk.berlaku_sampai >= CURDATE())
    ORDER BY s.jam_masuk ASC");
$stmtJadwal->execute([$user['id']]);
$semuaJadwal = $stmtJadwal->fetchAll();

if (empty($semuaJadwal)) {
    jsonResponse(['success' => false, 'message' => 'Tidak ada jadwal aktif']);
}

$hariIni = (int)date('N');
$jadwal  = null;
foreach ($semuaJadwal as $j) {
    $hariKerja = json_decode($j['hari_kerja'], true) ?? [];
    if (in_array($hariIni, $hariKerja)) { $jadwal = $j; break; }
}

if (!$jadwal) {
    $namaHari = ['','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][$hariIni];
    jsonResponse(['success' => false, 'message' => "Tidak ada jadwal untuk hari {$namaHari}."]);
}

// ================================================================
// LAYER 8: Validasi jarak server-side (utama)
// ================================================================
$jarak = jarakDuaTitik($lat, $lng, $jadwal['latitude'], $jadwal['longitude']);
if ($jarak > $jadwal['radius_meter']) {
    logActivity('ABSEN_GAGAL', "Diluar radius: {$jarak}m (batas {$jadwal['radius_meter']}m), acc:{$accuracy}m, flag:out_of_radius");
    jsonResponse([
        'success' => false,
        'message' => "Anda berada {$jarak}m dari kantor. Batas radius: {$jadwal['radius_meter']}m.",
        'flag'    => 'out_of_radius'
    ]);
}

// ================================================================
// LAYER 9: Bandingkan lokasi dengan riwayat absen sebelumnya
// Jika sebelumnya selalu dari koordinat X, lalu tiba-tiba beda jauh = suspicious
// ================================================================
$stmtHistory = $db->prepare("SELECT lat_masuk, lng_masuk FROM absensi 
    WHERE karyawan_id=? AND lat_masuk IS NOT NULL 
    ORDER BY tanggal DESC LIMIT 10");
$stmtHistory->execute([$user['id']]);
$history = $stmtHistory->fetchAll();
$locationSuspicious = false;
if (count($history) >= 3) {
    // Hitung rata-rata koordinat historis
    $avgLat = array_sum(array_column($history, 'lat_masuk')) / count($history);
    $avgLng = array_sum(array_column($history, 'lng_masuk')) / count($history);
    $jarakDariHistori = jarakDuaTitik($lat, $lng, $avgLat, $avgLng);
    // Jika absen dari lokasi > 500m dari rata-rata historis = flag
    if ($jarakDariHistori > 500) {
        $locationSuspicious = true;
    }
}

// ================================================================
// Tentukan flag kecurigaan keseluruhan
// ================================================================
$fraudFlag = null;
if ($suspiciousIP)       $fraudFlag = 'shared_ip';
if ($locationSuspicious) $fraudFlag = $fraudFlag ? $fraudFlag.',location_anomaly' : 'location_anomaly';
if ($accuracy > 50)      $fraudFlag = $fraudFlag ? $fraudFlag.',low_accuracy' : 'low_accuracy';

// ================================================================
// Cek absensi hari ini
// ================================================================
$stmtCek = $db->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
$stmtCek->execute([$user['id'], $today]);
$absenToday = $stmtCek->fetch();

// ================================================================
// Simpan absensi
// ================================================================
if ($type === 'masuk') {
    if ($absenToday && $absenToday['waktu_masuk']) {
        jsonResponse(['success' => false, 'message' => 'Anda sudah absen masuk hari ini']);
    }

    $jamMasuk        = strtotime(date('Y-m-d') . ' ' . $jadwal['jam_masuk']);
    $waktuSekarang   = strtotime($now);
    $toleransi       = (int)$jadwal['toleransi_terlambat_detik'];
    $selisih         = $waktuSekarang - $jamMasuk;
    $terlambatDetik  = max(0, $selisih - $toleransi);
    $statusKehadiran = $terlambatDetik > 0 ? 'terlambat' : 'hadir';

    $keterangan = $fraudFlag ? "[FLAG:{$fraudFlag}] acc:{$accuracy}m" : "acc:{$accuracy}m";

    if ($absenToday) {
        $stmt = $db->prepare("UPDATE absensi SET waktu_masuk=?, lat_masuk=?, lng_masuk=?, jarak_masuk=?, status_kehadiran=?, terlambat_detik=?, foto_masuk=?, device_info=?, ip_address=?, keterangan=? WHERE id=?");
        $stmt->execute([$now, $lat, $lng, $jarak, $statusKehadiran, $terlambatDetik, $fotoPath, $userAgent, $ipAddr, $keterangan, $absenToday['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO absensi (karyawan_id, jadwal_id, shift_id, tanggal, waktu_masuk, lat_masuk, lng_masuk, jarak_masuk, status_kehadiran, terlambat_detik, foto_masuk, device_info, ip_address, keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user['id'], $jadwal['jadwal_real_id'], $jadwal['shift_id'], $today, $now, $lat, $lng, $jarak, $statusKehadiran, $terlambatDetik, $fotoPath, $userAgent, $ipAddr, $keterangan]);
    }

    $msg = 'Absen masuk berhasil!';
    if ($terlambatDetik > 0) $msg .= ' Terlambat: ' . formatTerlambat($terlambatDetik);
    logActivity('ABSEN_MASUK', "Masuk {$now}, jarak:{$jarak}m, acc:{$accuracy}m" . ($fraudFlag ? ", FLAG:{$fraudFlag}" : ''));

    $response = ['success' => true, 'message' => $msg, 'terlambat' => $terlambatDetik];
    if ($fraudFlag) {
        $response['warning'] = 'Absensi tercatat namun terdeteksi anomali lokasi. Admin akan diberitahu.';
    }
    jsonResponse($response);

} else {
    if (!$absenToday || !$absenToday['waktu_masuk']) {
        jsonResponse(['success' => false, 'message' => 'Anda belum melakukan absen masuk']);
    }
    if ($absenToday['waktu_keluar']) {
        jsonResponse(['success' => false, 'message' => 'Anda sudah absen keluar hari ini']);
    }

    $durasi     = round((strtotime($now) - strtotime($absenToday['waktu_masuk'])) / 60);
    $keterangan = $fraudFlag ? "[FLAG:{$fraudFlag}] acc:{$accuracy}m" : "acc:{$accuracy}m";

    $stmt = $db->prepare("UPDATE absensi SET waktu_keluar=?, lat_keluar=?, lng_keluar=?, jarak_keluar=?, durasi_kerja=?, foto_keluar=?, keterangan=CONCAT(IFNULL(keterangan,''),' | keluar ',?) WHERE id=?");
    $stmt->execute([$now, $lat, $lng, $jarak, $durasi, $fotoPath, $keterangan, $absenToday['id']]);

    logActivity('ABSEN_KELUAR', "Keluar {$now}, durasi:{$durasi}m, acc:{$accuracy}m" . ($fraudFlag ? ", FLAG:{$fraudFlag}" : ''));
    jsonResponse(['success' => true, 'message' => "Absen keluar berhasil! Durasi kerja: " . formatDurasi($durasi)]);
}