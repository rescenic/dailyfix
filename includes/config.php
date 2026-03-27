<?php
// ============================================
// DailyFix - Konfigurasi Aplikasi
// ============================================

define('APP_NAME', 'DailyFix');
define('APP_VERSION', '1.0.0');
// Auto-detect URL — bekerja untuk localhost, 192.168.x.x, maupun domain
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_base     = '/dailyfix'; // ganti jika nama folder berbeda
define('APP_URL', $_protocol . '://' . $_host . $_base);
unset($_protocol, $_host, $_base);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dailyfix');
define('DB_CHARSET', 'utf8mb4');

// Session Configuration
define('SESSION_NAME', 'dailyfix_session');
define('SESSION_LIFETIME', 28800); // 8 jam

// Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ============================================
// Helper: Format Tanggal Bahasa Indonesia
// ============================================
function tglIndonesia($tanggal = null, $format = 'full') {
    $ts = $tanggal ? strtotime($tanggal) : time();
    $hariId  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulanId = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
    $hari  = $hariId[date('w', $ts)];
    $tgl   = date('d', $ts);
    $bln   = $bulanId[(int)date('n', $ts)];
    $tahun = date('Y', $ts);

    switch ($format) {
        case 'full':   return "$hari, $tgl $bln $tahun";         // Rabu, 25 Maret 2026
        case 'short':  return "$tgl $bln $tahun";                 // 25 Maret 2026
        case 'hari':   return $hari;                              // Rabu
        case 'bulan':  return $bln;                               // Maret
        case 'dmy':    return date('d/m/Y', $ts);                  // 25/03/2026
        default:       return "$hari, $tgl $bln $tahun";
    }
}

// Error Reporting (set to 0 di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ============================================
// Database Connection (PDO)
// ============================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ============================================
// Helper Functions
// ============================================

function isLoggedIn() {
    return isset($_SESSION['karyawan_id']) && !empty($_SESSION['karyawan_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
        header('Location: ' . APP_URL . '/index.php?error=akses_ditolak');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'         => $_SESSION['karyawan_id'],
        'nama'       => $_SESSION['nama'],
        'email'      => $_SESSION['email'],
        'role'       => $_SESSION['role'],
        'nik'        => $_SESSION['nik'],
        'foto'       => $_SESSION['foto'] ?? null,
        'perusahaan_id' => $_SESSION['perusahaan_id'],
        'lokasi_id'  => $_SESSION['lokasi_id'],
    ];
}

function formatTerlambat($detik) {
    if ($detik <= 0) return '-';
    $jam    = floor($detik / 3600);
    $menit  = floor(($detik % 3600) / 60);
    $sisa   = $detik % 60;
    $parts  = [];
    if ($jam > 0)   $parts[] = $jam . ' jam';
    if ($menit > 0) $parts[] = $menit . ' menit';
    if ($sisa > 0)  $parts[] = $sisa . ' detik';
    return implode(' ', $parts);
}

function formatDurasi($menit) {
    if (!$menit) return '-';
    $jam   = floor($menit / 60);
    $sisa  = $menit % 60;
    return ($jam > 0 ? $jam . 'j ' : '') . $sisa . 'm';
}

function jarakDuaTitik($lat1, $lng1, $lat2, $lng2) {
    // Haversine formula
    $r = 6371000; // radius bumi dalam meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($r * $c);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . $url);
    exit;
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function logActivity($aksi, $keterangan = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO log_aktivitas (karyawan_id, aksi, keterangan, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['karyawan_id'] ?? null,
            $aksi,
            $keterangan,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {}
}

function getNamaHari($angka) {
    $hari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return $hari[$angka] ?? '';
}


// ============================================
// Helper: Kirim Email via SMTP (dari smtp_settings)
// ============================================
function sendSmtpEmail($db, $perusahaan_id, $to, $subject, $htmlBody) {
    $stmt = $db->prepare("SELECT * FROM smtp_settings WHERE perusahaan_id=? AND is_active=1 LIMIT 1");
    $stmt->execute([$perusahaan_id]);
    $s = $stmt->fetch();
    if (!$s) return 'Konfigurasi SMTP belum diatur atau tidak aktif.';

    $pw = base64_decode($s['password']);

    $paths = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../phpmailer/src/PHPMailer.php',
        __DIR__ . '/../PHPMailer/src/PHPMailer.php',
    ];
    $found = false;
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            require_once dirname($p) . '/SMTP.php';
            require_once dirname($p) . '/Exception.php';
            $found = true; break;
        }
    }

    if ($found) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $s['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $s['username'];
            $mail->Password   = $pw;
            $mail->SMTPSecure = $s['encryption'] === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $s['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($s['from_email'], $s['from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    } else {
        // Fallback mail()
        $headers  = "From: {$s['from_name']} <{$s['from_email']}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $htmlBody, $headers)
            ? true
            : 'Fungsi mail() gagal. Install PHPMailer untuk SMTP Gmail.';
    }
}

function badgeStatus($status) {
    $map = [
        'hadir'    => ['bg-emerald-100 text-emerald-700', 'Hadir'],
        'terlambat'=> ['bg-amber-100 text-amber-700', 'Terlambat'],
        'absen'    => ['bg-red-100 text-red-700', 'Absen'],
        'izin'     => ['bg-blue-100 text-blue-700', 'Izin'],
        'sakit'    => ['bg-purple-100 text-purple-700', 'Sakit'],
        'libur'    => ['bg-slate-100 text-slate-600', 'Libur'],
        'aktif'    => ['bg-emerald-100 text-emerald-700', 'Aktif'],
        'nonaktif' => ['bg-red-100 text-red-700', 'Nonaktif'],
        'pending'  => ['bg-amber-100 text-amber-700', 'Pending'],
        'disetujui'=> ['bg-emerald-100 text-emerald-700', 'Disetujui'],
        'ditolak'  => ['bg-red-100 text-red-700', 'Ditolak'],
        'cuti'     => ['bg-indigo-100 text-indigo-700', 'Cuti'],
    ];
    $d = $map[$status] ?? ['bg-slate-100 text-slate-600', ucfirst($status)];
    return '<span class="badge ' . $d[0] . '">' . $d[1] . '</span>';
}