<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ─── Tentukan perusahaan_id secara dinamis & aman ────────────────────────────
$perusahaan_id = 1;
try {
    $dbTemp = getDB();
    $rowP = $dbTemp->query("
        SELECT perusahaan_id, COUNT(*) as total
        FROM jabatan
        GROUP BY perusahaan_id
        ORDER BY total DESC
        LIMIT 1
    ")->fetch();
    if ($rowP && (int)$rowP['perusahaan_id'] > 0) {
        $perusahaan_id = (int)$rowP['perusahaan_id'];
    }
} catch (Exception $e) {
    $perusahaan_id = 1;
}

$jabatanList = [];
try {
    $stmt = $dbTemp->prepare("SELECT id, nama FROM jabatan WHERE perusahaan_id = ? ORDER BY nama ASC");
    $stmt->execute([$perusahaan_id]);
    $jabatanList = $stmt->fetchAll();
} catch (Exception $e) { $jabatanList = []; }

$departemenList = [];
try {
    $stmt = $dbTemp->prepare("SELECT id, nama FROM departemen WHERE perusahaan_id = ? ORDER BY nama ASC");
    $stmt->execute([$perusahaan_id]);
    $departemenList = $stmt->fetchAll();
} catch (Exception $e) { $departemenList = []; }

// ─── Baca flash session register (PRG) ──────────────────────────────────────
$regErrors  = [];
$regSuccess = false;
$regPosted  = false;
$regOldPost = [];

if (isset($_SESSION['reg_success'])) {
    $regSuccess              = true;
    $regPosted               = true;
    $regOldPost['email_reg'] = $_SESSION['reg_email'] ?? '';
    unset($_SESSION['reg_success'], $_SESSION['reg_email']);
}
if (isset($_SESSION['reg_errors'])) {
    $regErrors  = $_SESSION['reg_errors'];
    $regPosted  = true;
    $regOldPost = $_SESSION['reg_old'] ?? [];
    unset($_SESSION['reg_errors'], $_SESSION['reg_old']);
}

// ─── Handle POST: Register (PRG Pattern) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $db            = getDB();
    $nama          = sanitize($_POST['nama']          ?? '');
    $nik           = sanitize($_POST['nik']           ?? '');
    $email_reg     = sanitize($_POST['email_reg']     ?? '');
    $telepon       = sanitize($_POST['telepon']       ?? '');
    $jabatan_id    = (int)($_POST['jabatan_id']       ?? 0);
    $departemen_id = (int)($_POST['departemen_id']    ?? 0);

    $errors = [];
    if (!$nama)   $errors[] = 'Nama lengkap wajib diisi.';
    if (!$nik)    $errors[] = 'NIK wajib diisi.';
    if (!$email_reg || !filter_var($email_reg, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (!$jabatan_id)    $errors[] = 'Pilih jabatan Anda.';
    if (!$departemen_id) $errors[] = 'Pilih departemen Anda.';

    if (empty($errors)) {
        try {
            $cek = $db->prepare("SELECT id FROM karyawan WHERE email = ? OR nik = ?");
            $cek->execute([$email_reg, $nik]);
            if ($cek->fetch()) $errors[] = 'Email atau NIK sudah terdaftar.';
        } catch (Exception $e) {
            $errors[] = 'Gagal memverifikasi data.';
        }
    }

    if (empty($errors)) {
        try {
            $cols = $db->query("SHOW COLUMNS FROM karyawan")->fetchAll(PDO::FETCH_COLUMN);
            $fields = ['perusahaan_id','nik','nama','email','telepon','role','status','tanggal_bergabung'];
            $values = [$perusahaan_id, $nik, $nama, $email_reg, $telepon, 'karyawan', 'nonaktif', date('Y-m-d')];
            if (in_array('jabatan_id',    $cols) && $jabatan_id)    { $fields[] = 'jabatan_id';    $values[] = $jabatan_id; }
            if (in_array('departemen_id', $cols) && $departemen_id) { $fields[] = 'departemen_id'; $values[] = $departemen_id; }
            if (in_array('password',      $cols)) { $fields[] = 'password'; $values[] = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); }
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO karyawan (" . implode(',', $fields) . ") VALUES ($placeholders)")->execute($values);

            // Kirim email notifikasi (gagal email tidak cancel registrasi)
            try {
                $jabatanNama = $departemenNama = '';
                foreach ($jabatanList    as $j) { if ($j['id'] == $jabatan_id)    $jabatanNama    = $j['nama']; }
                foreach ($departemenList as $d) { if ($d['id'] == $departemen_id) $departemenNama = $d['nama']; }

                $emailBody = '
                <div style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;max-width:520px;margin:0 auto">
                    <div style="background:linear-gradient(135deg,#0f4c81,#0a2d55);padding:28px;border-radius:12px 12px 0 0;text-align:center">
                        <div style="display:inline-block;background:linear-gradient(135deg,#00c9a7,#0ea5e9);width:52px;height:52px;border-radius:12px;line-height:52px;font-size:24px;font-weight:900;color:#fff;text-align:center">D</div>
                        <h1 style="color:#fff;font-size:20px;margin:10px 0 0;font-weight:800">DailyFix</h1>
                    </div>
                    <div style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none">
                        <h2 style="color:#0f172a;font-size:18px;margin:0 0 6px">Pendaftaran Berhasil! 🎉</h2>
                        <p style="color:#64748b;font-size:14px;margin:0 0 24px">Halo <strong style="color:#0f172a">' . htmlspecialchars($nama) . '</strong>, akun Anda telah terdaftar di DailyFix.</p>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin-bottom:20px">
                            <table style="width:100%;border-collapse:collapse;font-size:13.5px">
                                <tr><td style="padding:5px 0;color:#64748b;width:130px">NIK</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($nik) . '</td></tr>
                                <tr><td style="padding:5px 0;color:#64748b">Nama</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($nama) . '</td></tr>
                                <tr><td style="padding:5px 0;color:#64748b">Email</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($email_reg) . '</td></tr>
                                <tr><td style="padding:5px 0;color:#64748b">Jabatan</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($jabatanNama ?: '-') . '</td></tr>
                                <tr><td style="padding:5px 0;color:#64748b">Departemen</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($departemenNama ?: '-') . '</td></tr>
                            </table>
                        </div>
                        <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 16px;border-radius:0 8px 8px 0;margin-bottom:20px">
                            <div style="font-weight:700;color:#92400e;font-size:13px;margin-bottom:4px">⏳ Menunggu Aktivasi Admin</div>
                            <div style="color:#92400e;font-size:13px;line-height:1.6">Akun Anda sedang dalam proses verifikasi. Proses ini biasanya memakan waktu 1×24 jam.</div>
                        </div>
                        <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0">Setelah diaktifkan, login menggunakan <strong>kode OTP</strong> yang dikirim ke email ini.</p>
                    </div>
                    <div style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;text-align:center;font-size:11.5px;color:#94a3b8">
                        © ' . date('Y') . ' DailyFix — Sistem Absensi Digital
                    </div>
                </div>';

                $smtpStmt = $db->prepare("SELECT id FROM smtp_settings WHERE perusahaan_id = ? AND is_active = 1 LIMIT 1");
                $smtpStmt->execute([$perusahaan_id]);
                if ($smtpStmt->fetch()) {
                    sendSmtpEmail($db, $perusahaan_id, $email_reg, 'Pendaftaran DailyFix Berhasil — Menunggu Aktivasi', $emailBody);
                }
            } catch (Exception $e) { /* email gagal tidak membatalkan registrasi */ }

            // ✅ PRG: simpan sukses ke session → redirect GET
            $_SESSION['reg_success'] = true;
            $_SESSION['reg_email']   = $email_reg;
            header('Location: ' . APP_URL . '/login.php?registered=1');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }

    // ✅ PRG: ada error → simpan ke session → redirect GET
    if (!empty($errors)) {
        $_SESSION['reg_errors'] = $errors;
        $_SESSION['reg_old']    = $_POST;
        header('Location: ' . APP_URL . '/login.php?reg_error=1');
        exit;
    }
}

// ─── Handle POST: Send OTP ───────────────────────────────────────────────────
$step         = $_SESSION['otp_step']  ?? 'email';
$otpEmail     = $_SESSION['otp_email'] ?? '';
$error        = '';
$info         = '';
$pendingEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $email = sanitize($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan alamat email yang valid.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT k.*, p.nama as perusahaan_nama FROM karyawan k
                              LEFT JOIN perusahaan p ON p.id = k.perusahaan_id
                              WHERE k.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Email tidak terdaftar di sistem.';
        } elseif ($user['status'] === 'nonaktif') {
            $error = 'PENDING';
            $pendingEmail = $email;
        } elseif ($user['status'] === 'cuti') {
            $error = 'Akun Anda sedang dalam status cuti. Hubungi admin.';
        } elseif ($user['status'] !== 'aktif') {
            $error = 'Akun Anda tidak aktif. Hubungi admin perusahaan Anda.';
        } else {
            $rateStmt = $db->prepare("SELECT COUNT(*) FROM otp_login WHERE email=? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $rateStmt->execute([$email]);
            if ($rateStmt->fetchColumn() >= 3) {
                $error = 'Terlalu banyak permintaan OTP. Coba lagi dalam 10 menit.';
            } else {
                $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
                $db->prepare("DELETE FROM otp_login WHERE email=? AND used=0")->execute([$email]);
                $db->prepare("INSERT INTO otp_login (email, otp, expires_at, ip_address) VALUES (?,?,?,?)")
                   ->execute([$email, $otp, $expires, $ip]);

                $smtpStmt = $db->prepare("SELECT * FROM smtp_settings WHERE perusahaan_id=? AND is_active=1 LIMIT 1");
                $smtpStmt->execute([$user['perusahaan_id']]);
                $smtpOk = $smtpStmt->fetch();

                $emailBody = '
                <div style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;max-width:480px;margin:0 auto">
                    <div style="background:linear-gradient(135deg,#0f4c81,#0a2d55);padding:24px 28px;border-radius:12px 12px 0 0;text-align:center">
                        <div style="display:inline-block;background:linear-gradient(135deg,#00c9a7,#0ea5e9);width:48px;height:48px;border-radius:12px;line-height:48px;font-size:22px;font-weight:900;color:#fff;text-align:center">D</div>
                        <h1 style="color:#fff;font-size:20px;margin:10px 0 0;font-weight:800">DailyFix</h1>
                    </div>
                    <div style="background:#fff;padding:28px;border:1px solid #e2e8f0;border-top:none">
                        <p style="color:#374151;font-size:15px;margin-bottom:6px">Halo, <strong>' . htmlspecialchars($user['nama']) . '</strong> 👋</p>
                        <p style="color:#64748b;font-size:13.5px;margin-bottom:24px">Gunakan kode OTP berikut untuk masuk ke DailyFix. Kode berlaku selama <strong>5 menit</strong>.</p>
                        <div style="background:#f0f4f8;border:2px dashed #0f4c81;border-radius:12px;padding:20px;text-align:center;margin-bottom:20px">
                            <div style="font-family:\'JetBrains Mono\',monospace;font-size:36px;font-weight:900;letter-spacing:8px;color:#0f4c81">' . implode(' ', str_split($otp)) . '</div>
                            <div style="color:#94a3b8;font-size:12px;margin-top:8px">Berlaku hingga ' . date('H:i', strtotime($expires)) . ' WIB</div>
                        </div>
                        <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:10px 14px;border-radius:0 6px 6px 0;font-size:12.5px;color:#92400e">
                            ⚠️ Jangan bagikan kode ini kepada siapapun.
                        </div>
                    </div>
                    <div style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;text-align:center;font-size:11.5px;color:#94a3b8">
                        © ' . date('Y') . ' DailyFix — Sistem Absensi Digital · M. Wira Satria Buana · 082177846209
                    </div>
                </div>';

                $sent = false;
                if ($smtpOk) {
                    $result = sendSmtpEmail($db, $user['perusahaan_id'], $email, 'Kode OTP Login DailyFix — ' . $otp, $emailBody);
                    $sent   = ($result === true);
                }
                if (!$sent) $_SESSION['dev_otp'] = $otp;

                $_SESSION['otp_step']    = 'otp';
                $_SESSION['otp_email']   = $email;
                $_SESSION['otp_user_id'] = $user['id'];
                logActivity('OTP_SENT', "OTP dikirim ke $email" . (!$sent ? ' (dev mode)' : ''));
                header('Location: ' . APP_URL . '/login.php');
                exit;
            }
        }
    }
}

// ─── Handle POST: Verify OTP ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $inputOtp = trim(implode('', $_POST['otp'] ?? []));
    $email    = $_SESSION['otp_email'] ?? '';

    if (strlen($inputOtp) !== 6 || !ctype_digit($inputOtp)) {
        $error = 'Masukkan 6 digit kode OTP.';
    } elseif (!$email) {
        $error = 'Sesi habis. Silakan ulangi dari awal.';
        unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id']);
        $step = 'email';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM otp_login WHERE email=? AND otp=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $inputOtp]);
        $otpRow = $stmt->fetch();

        if (!$otpRow) {
            $error = 'Kode OTP salah atau sudah kadaluarsa.';
        } else {
            $db->prepare("UPDATE otp_login SET used=1 WHERE id=?")->execute([$otpRow['id']]);
            $userStmt = $db->prepare("SELECT k.*, p.nama as perusahaan_nama FROM karyawan k
                LEFT JOIN perusahaan p ON p.id=k.perusahaan_id
                WHERE k.email=? AND k.status='aktif' LIMIT 1");
            $userStmt->execute([$email]);
            $user = $userStmt->fetch();

            if ($user) {
                $_SESSION['karyawan_id']   = $user['id'];
                $_SESSION['nama']          = $user['nama'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['nik']           = $user['nik'];
                $_SESSION['foto']          = $user['foto'];
                $_SESSION['perusahaan_id'] = $user['perusahaan_id'];
                $_SESSION['lokasi_id']     = $user['lokasi_id'] ?? null;
                unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id'], $_SESSION['dev_otp']);
                logActivity('LOGIN', 'Login berhasil via OTP dari ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                redirect(APP_URL . '/index.php');
            }
        }
    }
}

// ─── Reset OTP session ───────────────────────────────────────────────────────
if (isset($_GET['reset'])) {
    unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id'], $_SESSION['dev_otp']);
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$step     = $_SESSION['otp_step']  ?? 'email';
$otpEmail = $_SESSION['otp_email'] ?? '';
$devOtp   = $_SESSION['dev_otp']   ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — DailyFix</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; min-height:100vh; display:flex; background:#0a1628; overflow:hidden; }

/* ══ LEFT PANEL ══ */
.left-panel { flex:1; display:flex; flex-direction:column; justify-content:center; padding:60px; position:relative; background:linear-gradient(145deg,#0f4c81 0%,#0a2d55 60%,#061a33 100%); overflow:hidden; }
.left-panel::before { content:''; position:absolute; top:-120px; right:-120px; width:420px; height:420px; border-radius:50%; background:radial-gradient(circle,rgba(0,201,167,.25) 0%,transparent 70%); }
.left-panel::after  { content:''; position:absolute; bottom:-80px; left:-80px; width:320px; height:320px; border-radius:50%; background:radial-gradient(circle,rgba(255,255,255,.06) 0%,transparent 70%); }
.dots-grid { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.07) 1px,transparent 1px); background-size:32px 32px; }
.brand-content { position:relative; z-index:1; }
.brand-logo    { width:56px; height:56px; background:linear-gradient(135deg,#00c9a7,#0ea5e9); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:900; color:#fff; margin-bottom:28px; box-shadow:0 8px 32px rgba(0,201,167,.4); }
.brand-tagline { display:inline-block; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#00c9a7; background:rgba(0,201,167,.12); border:1px solid rgba(0,201,167,.3); padding:4px 12px; border-radius:20px; margin-bottom:20px; }
.brand-title   { font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; color:#fff; line-height:1.25; margin-bottom:16px; }
.brand-title span { color:#00c9a7; }
.brand-desc    { font-size:14px; color:rgba(255,255,255,.55); line-height:1.75; max-width:380px; margin-bottom:36px; }
.feature-list  { display:flex; flex-direction:column; gap:12px; margin-bottom:40px; }
.feature-item  { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,.7); font-size:13px; }
.feature-icon  { width:30px; height:30px; border-radius:8px; background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; font-size:12px; color:#00c9a7; flex-shrink:0; }
.left-btn-row  { display:flex; gap:10px; flex-wrap:wrap; }
.btn-left-action { display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,.08); border:1.5px solid rgba(255,255,255,.18); color:rgba(255,255,255,.75); padding:8px 18px; border-radius:20px; font-size:12.5px; font-weight:600; font-family:inherit; cursor:pointer; transition:all .2s; }
.btn-left-action:hover { background:rgba(0,201,167,.2); border-color:#00c9a7; color:#00c9a7; }
.btn-left-action.register { background:rgba(0,201,167,.15); border-color:rgba(0,201,167,.5); color:#00c9a7; }
.btn-left-action.register:hover { background:rgba(0,201,167,.3); }

/* ══ RIGHT PANEL ══ */
.right-panel { width:460px; min-width:460px; background:#fff; display:flex; flex-direction:column; justify-content:center; padding:52px 48px; overflow-y:auto; }
.form-header { margin-bottom:28px; }
.form-header h2 { font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:5px; }
.form-header p  { font-size:13.5px; color:#64748b; }
.input-group { margin-bottom:16px; }
.input-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
.input-field { position:relative; }
.input-field .icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px; pointer-events:none; z-index:1; }
.input-field input { width:100%; padding:11px 14px 11px 40px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; color:#0f172a; background:#f8fafc; outline:none; transition:all .2s; }
.input-field input:focus { border-color:#0f4c81; background:#fff; box-shadow:0 0 0 3px rgba(15,76,129,.1); }
.input-field input::placeholder { color:#94a3b8; }

.otp-wrap { display:flex; gap:10px; justify-content:center; margin:20px 0; }
.otp-box { width:48px; height:56px; border:2px solid #e2e8f0; border-radius:12px; font-size:22px; font-weight:800; font-family:'JetBrains Mono',monospace; text-align:center; color:#0f172a; background:#f8fafc; outline:none; transition:all .2s; caret-color:#0f4c81; }
.otp-box:focus  { border-color:#0f4c81; box-shadow:0 0 0 3px rgba(15,76,129,.12); background:#fff; }
.otp-box.filled { border-color:#00c9a7; background:#f0fdf9; }

.email-badge { display:flex; align-items:center; gap:8px; background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:10px; font-size:13px; color:#1e40af; margin-bottom:16px; }
.otp-timer { text-align:center; font-size:12.5px; color:#94a3b8; margin-bottom:12px; }
.otp-timer span { color:#0f4c81; font-weight:700; }
.otp-timer.expired span { color:#ef4444; }
.dev-otp-box { background:#fef3c7; border:2px dashed #f59e0b; border-radius:12px; padding:14px 16px; margin-bottom:16px; text-align:center; }
.dev-otp-box .dev-label { font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:1px; }

.alert-pending { background:#fffbeb; border:1px solid #fcd34d; border-left:4px solid #f59e0b; border-radius:10px; padding:14px 16px; margin-bottom:16px; animation:shake .35s ease; }
.alert-pending-title { display:flex; align-items:center; gap:8px; font-weight:700; color:#92400e; font-size:14px; margin-bottom:6px; }
.alert-pending p { font-size:13px; color:#92400e; line-height:1.65; margin:0; }
.alert-error { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:10px; margin-bottom:16px; font-size:13px; color:#991b1b; animation:shake .35s ease; }
.alert-info  { display:flex; align-items:flex-start; gap:10px; padding:11px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #3b82f6; border-radius:10px; margin-bottom:16px; font-size:13px; color:#1e40af; }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(4px)} }

.btn-submit { width:100%; padding:12px 20px; background:linear-gradient(135deg,#0f4c81,#0a2d55); color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; margin-top:4px; display:flex; align-items:center; justify-content:center; gap:8px; transition:transform .2s,box-shadow .2s; }
.btn-submit:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(15,76,129,.4); }
.btn-resend { background:none; border:none; color:#0f4c81; font-weight:700; cursor:pointer; font-size:13px; font-family:inherit; padding:0; text-decoration:underline; }
.btn-resend:disabled { color:#94a3b8; cursor:not-allowed; text-decoration:none; }

.steps { display:flex; align-items:center; margin-bottom:28px; }
.step-item { flex:1; text-align:center; position:relative; }
.step-circle { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; margin:0 auto 6px; position:relative; z-index:1; }
.step-circle.done   { background:#10b981; color:#fff; }
.step-circle.active { background:#0f4c81; color:#fff; }
.step-circle.wait   { background:#e2e8f0; color:#94a3b8; }
.step-label { font-size:11px; font-weight:600; color:#94a3b8; }
.step-label.active { color:#0f4c81; }
.step-line { position:absolute; top:16px; left:50%; width:100%; height:2px; background:#e2e8f0; z-index:0; }
.step-line.done { background:#10b981; }
.step-item:last-child .step-line { display:none; }

.form-footer { margin-top:16px; text-align:center; font-size:12px; color:#94a3b8; }
.btn-mobile-row { display:none; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
.btn-mobile-action { display:inline-flex; align-items:center; gap:5px; background:none; border:1.5px solid #e2e8f0; color:#64748b; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; font-family:inherit; cursor:pointer; transition:all .2s; }
.btn-mobile-action:hover { border-color:#0f4c81; color:#0f4c81; background:#eff6ff; }
.btn-mobile-action.reg { border-color:#bbf7d0; color:#15803d; background:#f0fdf4; }
.btn-mobile-action.reg:hover { background:#dcfce7; }

/* ══ MODAL BASE ══ */
.modal-overlay { position:fixed; inset:0; background:rgba(10,22,40,.75); backdrop-filter:blur(6px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; opacity:0; pointer-events:none; transition:opacity .3s; }
.modal-overlay.open { opacity:1; pointer-events:all; }
.modal-box { background:#fff; border-radius:20px; width:100%; box-shadow:0 32px 80px rgba(0,0,0,.35); overflow:hidden; transform:scale(.96) translateY(16px); transition:transform .3s cubic-bezier(.34,1.56,.64,1); }
.modal-overlay.open .modal-box { transform:scale(1) translateY(0); }
.modal-x { background:#f1f5f9; border:none; color:#64748b; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:all .2s; flex-shrink:0; }
.modal-x:hover { background:#ef4444; color:#fff; }

/* ══ MODAL REGISTER ══ */
#modalRegister .modal-box { max-width:860px; max-height:92vh; display:flex; }
.modal-reg-sidebar { width:220px; min-width:220px; background:linear-gradient(160deg,#0f4c81 0%,#0a2d55 60%,#061a33 100%); padding:28px 20px; display:flex; flex-direction:column; align-items:center; text-align:center; position:relative; overflow:hidden; }
.sidebar-dots2 { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px); background-size:24px 24px; }
.reg-sidebar-inner { position:relative; z-index:1; width:100%; }
.reg-sidebar-logo  { width:52px; height:52px; background:linear-gradient(135deg,#00c9a7,#0ea5e9); border-radius:14px; display:inline-flex; align-items:center; justify-content:center; font-size:22px; font-weight:900; color:#fff; margin-bottom:12px; box-shadow:0 8px 24px rgba(0,201,167,.4); }
.reg-sidebar-title { color:#fff; font-size:16px; font-weight:800; margin:0 0 3px; }
.reg-sidebar-sub   { color:rgba(255,255,255,.5); font-size:11px; margin:0 0 18px; }
.reg-sidebar-feats { display:flex; flex-direction:column; gap:8px; }
.reg-sidebar-feat  { display:flex; align-items:center; gap:8px; background:rgba(255,255,255,.07); border-radius:8px; padding:7px 10px; font-size:11.5px; color:rgba(255,255,255,.8); text-align:left; }
.reg-sidebar-feat i { color:#00c9a7; width:13px; text-align:center; flex-shrink:0; }
.reg-sidebar-copy  { margin-top:auto; padding-top:18px; font-size:10.5px; color:rgba(255,255,255,.3); line-height:1.6; }
.modal-reg-main { flex:1; overflow-y:auto; display:flex; flex-direction:column; min-width:0; }
.modal-reg-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid #f1f5f9; flex-shrink:0; }
.modal-reg-header h4 { font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.modal-reg-body { padding:22px 26px; flex:1; }

.m-input-group { margin-bottom:14px; }
.m-input-group label { display:block; font-size:12.5px; font-weight:600; color:#374151; margin-bottom:5px; }
.m-input-group label .req { color:#ef4444; margin-left:2px; }
.m-input-field { position:relative; }
.m-input-field .icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px; pointer-events:none; z-index:1; }
.m-input-field input,
.m-input-field select { width:100%; padding:9px 12px 9px 36px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; color:#0f172a; background:#f8fafc; outline:none; transition:all .2s; appearance:none; }
.m-input-field input:focus,
.m-input-field select:focus { border-color:#0f4c81; background:#fff; box-shadow:0 0 0 3px rgba(15,76,129,.1); }
.m-input-field input::placeholder { color:#94a3b8; }
.m-input-field .select-arrow { position:absolute; right:11px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:10px; pointer-events:none; }
.m-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.m-divider { display:flex; align-items:center; gap:8px; margin:6px 0 12px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; }
.m-divider::before, .m-divider::after { content:''; flex:1; height:1px; background:#e2e8f0; }
.m-otp-info { display:flex; align-items:center; gap:8px; background:linear-gradient(135deg,#eff6ff,#f0fdf4); border:1px solid #bfdbfe; border-radius:8px; padding:9px 12px; font-size:12px; color:#1e40af; margin-bottom:14px; }
.m-alert-error { display:flex; align-items:flex-start; gap:9px; padding:10px 13px; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:9px; margin-bottom:12px; font-size:12.5px; color:#991b1b; animation:shake .35s ease; }
.m-alert-error ul { margin:0; padding-left:1rem; }
.m-no-data { display:flex; align-items:center; gap:8px; background:#fff7ed; border:1px solid #fed7aa; border-left:4px solid #f97316; border-radius:9px; padding:9px 13px; font-size:12px; color:#9a3412; margin-bottom:12px; }

.reg-success-wrap { text-align:center; padding:28px 20px; }
.reg-success-icon { width:68px; height:68px; border-radius:50%; background:linear-gradient(135deg,#10b981,#00c9a7); display:flex; align-items:center; justify-content:center; font-size:1.9rem; color:#fff; margin:0 auto 18px; box-shadow:0 8px 28px rgba(16,185,129,.35); animation:popIn .5s cubic-bezier(.68,-.55,.265,1.55); }
@keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
.reg-success-wrap h3 { font-size:1.25rem; font-weight:800; color:#0f172a; margin-bottom:8px; }
.reg-success-wrap p  { font-size:13px; color:#64748b; margin-bottom:18px; line-height:1.65; }

.modal-reg-footer { padding:14px 24px; border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; flex-wrap:wrap; gap:10px; }
.modal-reg-footer span { font-size:11.5px; color:#94a3b8; }
.btn-reg-submit { background:linear-gradient(135deg,#0f4c81,#0a2d55); color:#fff; border:none; padding:10px 24px; border-radius:10px; font-size:14px; font-weight:700; font-family:inherit; cursor:pointer; display:flex; align-items:center; gap:7px; transition:all .2s; }
.btn-reg-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(15,76,129,.4); }
.btn-reg-submit:disabled { opacity:.6; cursor:not-allowed; transform:none; box-shadow:none; }
.btn-close-modal { background:#f1f5f9; border:none; color:#64748b; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:6px; transition:all .2s; }
.btn-close-modal:hover { background:#e2e8f0; }

/* ══ MODAL TENTANG ══ */
#modalAbout .modal-box { max-width:860px; max-height:92vh; display:flex; }
.modal-sidebar { width:230px; min-width:230px; background:linear-gradient(160deg,#0f4c81 0%,#0a2d55 60%,#061a33 100%); padding:28px 22px; display:flex; flex-direction:column; align-items:center; text-align:center; position:relative; overflow:hidden; }
.modal-sidebar::before { content:''; position:absolute; top:-60px; right:-60px; width:180px; height:180px; border-radius:50%; background:radial-gradient(circle,rgba(0,201,167,.2) 0%,transparent 70%); }
.modal-sidebar::after  { content:''; position:absolute; bottom:-40px; left:-40px; width:140px; height:140px; border-radius:50%; background:radial-gradient(circle,rgba(255,255,255,.05) 0%,transparent 70%); }
.sidebar-dots { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px); background-size:24px 24px; }
.sidebar-content { position:relative; z-index:1; width:100%; }
.modal-about-logo { width:56px; height:56px; background:linear-gradient(135deg,#00c9a7,#0ea5e9); border-radius:14px; display:inline-flex; align-items:center; justify-content:center; font-size:26px; font-weight:900; color:#fff; margin-bottom:12px; box-shadow:0 8px 24px rgba(0,201,167,.45); }
.sidebar-title  { color:#fff; font-size:18px; font-weight:800; margin:0 0 3px; }
.sidebar-sub    { color:rgba(255,255,255,.55); font-size:11.5px; margin:0 0 20px; }
.sidebar-badge  { display:inline-flex; align-items:center; gap:6px; background:rgba(0,201,167,.15); border:1px solid rgba(0,201,167,.35); color:#00c9a7; padding:6px 12px; border-radius:20px; font-size:11.5px; font-weight:700; margin-bottom:20px; }
.sidebar-features { width:100%; display:flex; flex-direction:column; gap:8px; }
.sidebar-feat { display:flex; align-items:center; gap:9px; background:rgba(255,255,255,.07); border-radius:9px; padding:8px 10px; font-size:12px; color:rgba(255,255,255,.8); text-align:left; }
.sidebar-feat i { color:#00c9a7; width:14px; text-align:center; flex-shrink:0; }
.sidebar-copy { margin-top:auto; padding-top:20px; font-size:11px; color:rgba(255,255,255,.3); line-height:1.6; }
.modal-main { flex:1; overflow-y:auto; display:flex; flex-direction:column; min-width:0; }
.modal-main-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #f1f5f9; flex-shrink:0; }
.modal-main-header h4 { font-size:15px; font-weight:800; color:#0f172a; margin:0; }
.modal-main-body { padding:20px 24px; flex:1; }
.about-sec-title { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#94a3b8; margin-bottom:10px; display:flex; align-items:center; gap:7px; }
.about-sec-title::after { content:''; flex:1; height:1px; background:#f1f5f9; }
.about-free-badge { display:inline-flex; align-items:center; gap:7px; background:#f0fdf4; border:1.5px solid #bbf7d0; color:#15803d; padding:7px 14px; border-radius:10px; font-size:12.5px; font-weight:700; margin-bottom:10px; }
.about-warning { background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:8px; padding:10px 14px; font-size:12.5px; color:#991b1b; display:flex; gap:8px; align-items:flex-start; margin-bottom:18px; }
.menu-grid-modal { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:18px; }
.menu-item-modal { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:12.5px; color:#374151; font-weight:500; }
.menu-item-modal i { color:#0f4c81; width:16px; text-align:center; flex-shrink:0; }
.donasi-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.donasi-card { border:1.5px solid #e2e8f0; border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:10px; transition:all .2s; }
.donasi-card:hover { border-color:#0f4c81; box-shadow:0 3px 16px rgba(15,76,129,.12); }
.donasi-card-top { display:flex; align-items:center; gap:10px; }
.donasi-icon { width:40px; height:40px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.donasi-icon.gopay { background:linear-gradient(135deg,#00aed6,#0070ba); }
.donasi-icon.bsi   { background:linear-gradient(135deg,#00a650,#006633); }
.donasi-info { flex:1; min-width:0; }
.donasi-label { font-size:10.5px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.donasi-value { font-size:15px; font-weight:900; color:#0f172a; font-family:'JetBrains Mono',monospace; }
.donasi-name  { font-size:11.5px; color:#64748b; margin-top:1px; }
.copy-btn { width:100%; background:#f1f5f9; border:none; color:#64748b; padding:8px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:6px; }
.copy-btn:hover  { background:#0f4c81; color:#fff; }
.copy-btn.copied { background:#10b981; color:#fff; }
.modal-main-footer { padding:14px 24px; border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; flex-wrap:wrap; gap:10px; }
.modal-main-footer span { font-size:11.5px; color:#94a3b8; }
.close-about { background:#0f4c81; border:none; color:#fff; padding:9px 22px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:6px; transition:all .2s; }
.close-about:hover { background:#0a2d55; }

/* ── Responsive ── */
@media(max-width:640px) {
    #modalRegister .modal-box, #modalAbout .modal-box { flex-direction:column; max-width:100%; max-height:95vh; border-radius:16px; }
    .modal-reg-sidebar, .modal-sidebar { width:100%; min-width:0; padding:14px 18px; flex-direction:row; text-align:left; align-items:center; gap:12px; }
    .modal-reg-sidebar::before, .sidebar-dots2,
    .modal-sidebar::before, .modal-sidebar::after, .sidebar-dots { display:none; }
    .reg-sidebar-feats, .reg-sidebar-copy, .sidebar-features, .sidebar-copy { display:none; }
    .reg-sidebar-inner, .sidebar-content { display:flex; flex-direction:column; }
    .reg-sidebar-logo, .modal-about-logo { width:38px; height:38px; font-size:16px; margin-bottom:0; flex-shrink:0; }
    .donasi-grid, .menu-grid-modal, .m-grid-2 { grid-template-columns:1fr; }
    .modal-reg-body, .modal-main-body { padding:16px 18px; }
}
@media(max-width:820px) {
    .left-panel { display:none; }
    body { background:linear-gradient(160deg,#0f4c81 0%,#0a2d55 50%,#061a33 100%); align-items:center; justify-content:center; padding:20px; overflow:auto; }
    .right-panel { width:100%; min-width:0; max-width:440px; padding:36px 28px; border-radius:20px; box-shadow:0 24px 60px rgba(0,0,0,.35); }
    .btn-mobile-row { display:flex; }
}
@media(min-width:821px) { .btn-mobile-row { display:none; } }
</style>
</head>
<body>

<!-- ══ LEFT PANEL ══ -->
<div class="left-panel">
    <div class="dots-grid"></div>
    <div class="brand-content">
        <div class="brand-logo">D</div>
        <div class="brand-tagline">Sistem Absensi Digital</div>
        <h1 class="brand-title">Hadir tepat waktu,<br><span>lebih mudah.</span></h1>
        <p class="brand-desc">DailyFix membantu perusahaan mengelola kehadiran karyawan secara akurat dengan GPS dan verifikasi foto wajah.</p>
        <div class="feature-list">
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-map-location-dot"></i></div> Absensi GPS multi-lokasi real-time</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-camera"></i></div> Verifikasi foto wajah setiap absen</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-shield-halved"></i></div> Anti fake GPS — 9 lapisan keamanan</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-key"></i></div> Login OTP email, tanpa password</div>
        </div>
        <div class="left-btn-row">
            <button class="btn-left-action register" onclick="openRegister()">
                <i class="fas fa-user-plus"></i> Daftar Akun
            </button>
            <button class="btn-left-action" onclick="openAbout()">
                <i class="fas fa-circle-info"></i> Tentang Aplikasi
            </button>
        </div>
    </div>
</div>

<!-- ══ RIGHT PANEL ══ -->
<div class="right-panel">
    <div class="steps">
        <div class="step-item">
            <div class="step-line <?= $step==='otp'?'done':'' ?>"></div>
            <div class="step-circle <?= $step==='email'?'active':'done' ?>">
                <?= $step==='email'?'1':'<i class="fas fa-check" style="font-size:11px"></i>' ?>
            </div>
            <div class="step-label <?= $step==='email'?'active':'' ?>">Alamat Email</div>
        </div>
        <div class="step-item">
            <div class="step-line"></div>
            <div class="step-circle <?= $step==='otp'?'active':'wait' ?>">2</div>
            <div class="step-label <?= $step==='otp'?'active':'' ?>">Kode OTP</div>
        </div>
    </div>

    <?php if ($step === 'email'): ?>
    <div class="form-header">
        <h2>Selamat datang 👋</h2>
        <p>Masukkan email Anda, kami kirim kode OTP untuk masuk</p>
    </div>
    <?php if ($error === 'PENDING'): ?>
    <div class="alert-pending">
        <div class="alert-pending-title"><i class="fas fa-clock"></i> Akun Menunggu Aktivasi</div>
        <p>Akun dengan email <strong><?= htmlspecialchars($pendingEmail) ?></strong> sudah terdaftar namun belum diaktifkan oleh admin.<br><br>Anda akan menerima email notifikasi begitu akun diaktifkan.</p>
    </div>
    <?php elseif ($error): ?>
    <div class="alert-error"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <?php if ($info): ?>
    <div class="alert-info"><i class="fas fa-circle-info"></i><span><?= htmlspecialchars($info) ?></span></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="send_otp">
        <div class="input-group">
            <label>Alamat Email</label>
            <div class="input-field">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" placeholder="nama@perusahaan.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autocomplete="email" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i> Kirim Kode OTP
        </button>
    </form>

    <?php else: ?>
    <div class="form-header">
        <h2>Masukkan Kode OTP 🔐</h2>
        <p>Kode 6 digit telah dikirim ke email Anda</p>
    </div>
    <?php if ($error): ?>
    <div class="alert-error"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <div class="email-badge">
        <i class="fas fa-envelope-circle-check"></i>
        <span>OTP dikirim ke <strong><?= htmlspecialchars($otpEmail) ?></strong></span>
    </div>
    <?php if ($devOtp): ?>
    <div class="dev-otp-box">
        <div class="dev-label">⚠️ SMTP belum dikonfigurasi — Mode Dev</div>
    </div>
    <?php endif; ?>
    <form method="POST" id="formOtp" autocomplete="off">
        <input type="hidden" name="action" value="verify_otp">
        <div class="otp-wrap">
            <?php for($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-box" id="otp<?=$i?>" name="otp[]" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            <?php endfor; ?>
        </div>
        <div class="otp-timer" id="timerWrap">Kode berlaku: <span id="timerVal">5:00</span></div>
        <button type="submit" class="btn-submit" id="btnVerify">
            <i class="fas fa-shield-check"></i> Verifikasi &amp; Masuk
        </button>
    </form>
    <div style="text-align:center;margin-top:14px">
        <span style="font-size:13px;color:#94a3b8">Tidak menerima kode? </span>
        <button class="btn-resend" id="btnResend" disabled onclick="resendOtp()">
            Kirim Ulang (<span id="resendCount">60</span>s)
        </button>
    </div>
    <div style="text-align:center;margin-top:10px">
        <a href="<?= APP_URL ?>/login.php?reset=1" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-size:13px;font-weight:600;text-decoration:none">
            <i class="fas fa-arrow-left"></i> Ganti akun
        </a>
    </div>
    <?php endif; ?>

    <div class="form-footer">
        &copy; <?= date('Y') ?> DailyFix &mdash; Develop M. Wira Sb. S.Kom
        <div class="btn-mobile-row">
            <button class="btn-mobile-action reg" onclick="openRegister()">
                <i class="fas fa-user-plus"></i> Daftar Akun
            </button>
            <button class="btn-mobile-action" onclick="openAbout()">
                <i class="fas fa-circle-info"></i> Tentang
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL REGISTER
═════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalRegister" onclick="if(event.target===this)closeRegister()">
    <div class="modal-box">

        <div class="modal-reg-sidebar">
            <div class="sidebar-dots2"></div>
            <div class="reg-sidebar-inner">
                <div class="reg-sidebar-logo">D</div>
                <div class="reg-sidebar-title">DailyFix</div>
                <div class="reg-sidebar-sub">Buat akun baru</div>
                <div class="reg-sidebar-feats">
                    <div class="reg-sidebar-feat"><i class="fas fa-key"></i> Login tanpa password</div>
                    <div class="reg-sidebar-feat"><i class="fas fa-map-location-dot"></i> Absensi GPS</div>
                    <div class="reg-sidebar-feat"><i class="fas fa-camera"></i> Verifikasi foto</div>
                    <div class="reg-sidebar-feat"><i class="fas fa-shield-halved"></i> Anti fake GPS</div>
                    <div class="reg-sidebar-feat"><i class="fas fa-clock"></i> Aktivasi oleh admin</div>
                </div>
                <div class="reg-sidebar-copy">© <?= date('Y') ?> DailyFix</div>
            </div>
        </div>

        <div class="modal-reg-main">
            <?php if ($regSuccess): ?>
            <!-- ── Sukses ── -->
            <div class="modal-reg-header">
                <h4><i class="fas fa-circle-check" style="color:#10b981;margin-right:6px"></i> Pendaftaran Berhasil</h4>
                <button class="modal-x" onclick="closeRegister()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-reg-body">
                <div class="reg-success-wrap">
                    <div class="reg-success-icon"><i class="fas fa-envelope-circle-check"></i></div>
                    <h3>Pendaftaran Berhasil! 🎉</h3>
                    <p>Email konfirmasi telah dikirim ke<br><strong><?= htmlspecialchars($regOldPost['email_reg'] ?? '') ?></strong></p>
                    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:14px 16px;text-align:left">
                        <div style="display:flex;align-items:center;gap:7px;font-weight:700;color:#92400e;font-size:13px;margin-bottom:5px">
                            <i class="fas fa-clock"></i> Menunggu Aktivasi Admin
                        </div>
                        <p style="font-size:12.5px;color:#92400e;margin:0;line-height:1.65">Akun Anda perlu diaktifkan oleh admin sebelum bisa login. Biasanya proses ini memakan waktu 1×24 jam.</p>
                    </div>
                </div>
            </div>
            <div class="modal-reg-footer">
                <span>© <?= date('Y') ?> DailyFix</span>
                <button class="close-about" onclick="closeRegister()"><i class="fas fa-xmark"></i> Tutup</button>
            </div>

            <?php else: ?>
            <!-- ── Form ── -->
            <div class="modal-reg-header">
                <h4><i class="fas fa-user-plus" style="color:#0ea5e9;margin-right:6px"></i> Buat Akun Baru</h4>
                <button class="modal-x" onclick="closeRegister()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-reg-body">
                <div class="m-otp-info">
                    <i class="fas fa-circle-info" style="color:#0ea5e9;flex-shrink:0"></i>
                    <span>Login menggunakan <strong>kode OTP</strong> yang dikirim ke email — tidak perlu password.</span>
                </div>

                <?php if (!empty($regErrors)): ?>
                <div class="m-alert-error">
                    <i class="fas fa-circle-exclamation" style="flex-shrink:0;margin-top:1px"></i>
                    <ul><?php foreach ($regErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <?php if (empty($jabatanList) || empty($departemenList)): ?>
                <div class="m-no-data">
                    <i class="fas fa-triangle-exclamation" style="flex-shrink:0"></i>
                    <span>Belum ada data <?= empty($jabatanList)?'jabatan':'' ?><?= (empty($jabatanList)&&empty($departemenList))?' &amp; ':'' ?><?= empty($departemenList)?'departemen':'' ?>. Hubungi admin.</span>
                </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off" id="formRegModal">
                    <input type="hidden" name="action" value="register">

                    <div class="m-divider">Data Diri</div>

                    <div class="m-input-group">
                        <label>Nama Lengkap <span class="req">*</span></label>
                        <div class="m-input-field">
                            <span class="icon"><i class="fas fa-user"></i></span>
                            <input type="text" name="nama" placeholder="Nama lengkap sesuai KTP"
                                value="<?= htmlspecialchars($regOldPost['nama'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="m-grid-2">
                        <div class="m-input-group">
                            <label>NIK / No. Karyawan <span class="req">*</span></label>
                            <div class="m-input-field">
                                <span class="icon"><i class="fas fa-id-card"></i></span>
                                <input type="text" name="nik" placeholder="Contoh: EMP001"
                                    value="<?= htmlspecialchars($regOldPost['nik'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="m-input-group">
                            <label>No. Telepon</label>
                            <div class="m-input-field">
                                <span class="icon"><i class="fas fa-phone"></i></span>
                                <input type="tel" name="telepon" placeholder="08xxxxxxxxxx"
                                    value="<?= htmlspecialchars($regOldPost['telepon'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="m-input-group">
                        <label>Alamat Email <span class="req">*</span></label>
                        <div class="m-input-field">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email_reg" placeholder="nama@email.com"
                                value="<?= htmlspecialchars($regOldPost['email_reg'] ?? '') ?>" required>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:#0ea5e9">
                            <i class="fas fa-circle-info" style="font-size:10px"></i>
                            <span>Gunakan email aktif — kode OTP login dikirim ke sini.</span>
                        </div>
                    </div>

                    <div class="m-divider">Posisi &amp; Departemen</div>

                    <div class="m-grid-2">
                        <div class="m-input-group">
                            <label>Jabatan <span class="req">*</span></label>
                            <div class="m-input-field">
                                <span class="icon"><i class="fas fa-briefcase"></i></span>
                                <select name="jabatan_id" required <?= empty($jabatanList)?'disabled':'' ?>>
                                    <option value="">— Pilih Jabatan —</option>
                                    <?php foreach ($jabatanList as $j): ?>
                                    <option value="<?= $j['id'] ?>" <?= (($regOldPost['jabatan_id']??'')==$j['id'])?'selected':'' ?>>
                                        <?= htmlspecialchars($j['nama']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($jabatanList)): ?><option disabled>Belum ada data</option><?php endif; ?>
                                </select>
                                <span class="select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="m-input-group">
                            <label>Departemen <span class="req">*</span></label>
                            <div class="m-input-field">
                                <span class="icon"><i class="fas fa-building"></i></span>
                                <select name="departemen_id" required <?= empty($departemenList)?'disabled':'' ?>>
                                    <option value="">— Pilih Departemen —</option>
                                    <?php foreach ($departemenList as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (($regOldPost['departemen_id']??'')==$d['id'])?'selected':'' ?>>
                                        <?= htmlspecialchars($d['nama']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($departemenList)): ?><option disabled>Belum ada data</option><?php endif; ?>
                                </select>
                                <span class="select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-reg-footer">
                <button class="btn-close-modal" onclick="closeRegister()"><i class="fas fa-xmark"></i> Batal</button>
                <button class="btn-reg-submit" id="btnRegSubmit"
                    onclick="submitReg()"
                    <?= (empty($jabatanList)||empty($departemenList))?'disabled':'' ?>>
                    <i class="fas fa-user-plus"></i> Buat Akun
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL TENTANG APLIKASI
═════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalAbout" onclick="if(event.target===this)closeAbout()">
    <div class="modal-box">
        <div class="modal-sidebar">
            <div class="sidebar-dots"></div>
            <div class="sidebar-content">
                <div class="modal-about-logo">D</div>
                <div class="sidebar-title">DailyFix</div>
                <div class="sidebar-sub">Sistem Absensi Digital v<?= APP_VERSION ?? '1.0' ?></div>
                <div class="sidebar-badge"><i class="fas fa-heart" style="color:#ef4444"></i> Gratis &amp; Bebas</div>
                <div class="sidebar-features">
                    <div class="sidebar-feat"><i class="fas fa-map-location-dot"></i> Absensi GPS Real-time</div>
                    <div class="sidebar-feat"><i class="fas fa-map-pin"></i> Multi Lokasi Absen</div>
                    <div class="sidebar-feat"><i class="fas fa-camera"></i> Verifikasi Foto Wajah</div>
                    <div class="sidebar-feat"><i class="fas fa-shield-halved"></i> Anti Fake GPS (9 Layer)</div>
                    <div class="sidebar-feat"><i class="fas fa-key"></i> Login OTP Email</div>
                    <div class="sidebar-feat"><i class="fas fa-clock"></i> Terlambat &amp; Pulang Cepat</div>
                    <div class="sidebar-feat"><i class="fas fa-chart-bar"></i> Rekap &amp; Laporan PDF</div>
                    <div class="sidebar-feat"><i class="fas fa-building"></i> Multi Perusahaan</div>
                </div>
                <div class="sidebar-copy">© <?= date('Y') ?> DailyFix<br>Hak Cipta Dilindungi</div>
            </div>
        </div>
        <div class="modal-main">
            <div class="modal-main-header">
                <h4><i class="fas fa-circle-info" style="color:#0ea5e9;margin-right:6px"></i> Tentang Aplikasi</h4>
                <button class="modal-x" onclick="closeAbout()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-main-body">
                <div class="about-sec-title"><i class="fas fa-info" style="color:#0ea5e9"></i> Informasi</div>
                <div class="about-free-badge"><i class="fas fa-heart" style="color:#ef4444"></i> Aplikasi Gratis &amp; Open Source</div>
                <p style="font-size:13px;color:#475569;line-height:1.75;margin-bottom:10px">
                    <strong>DailyFix</strong> dikembangkan secara independen oleh <strong>M. Wira Satria Buana, S.Kom</strong> dan dibagikan secara gratis untuk membantu perusahaan mengelola kehadiran karyawan secara akurat dan modern.
                </p>
                <div class="about-warning">
                    <i class="fas fa-ban" style="flex-shrink:0;margin-top:1px"></i>
                    <div><strong>Larangan Keras:</strong> Aplikasi ini <strong>tidak boleh diperjualbelikan</strong> dalam bentuk apapun. Redistribusi komersial tanpa izin adalah pelanggaran hak cipta.</div>
                </div>
                <div class="about-sec-title"><i class="fas fa-list-check" style="color:#10b981"></i> Menu yang Tersedia</div>
                <div class="menu-grid-modal">
                    <div class="menu-item-modal"><i class="fas fa-gauge-high"></i> Dashboard</div>
                    <div class="menu-item-modal"><i class="fas fa-fingerprint"></i> Absensi GPS + Foto</div>
                    <div class="menu-item-modal"><i class="fas fa-calendar-check"></i> Rekap Absensi Saya</div>
                    <div class="menu-item-modal"><i class="fas fa-building"></i> Master Perusahaan</div>
                    <div class="menu-item-modal"><i class="fas fa-map-marker-alt"></i> Master Lokasi (Multi)</div>
                    <div class="menu-item-modal"><i class="fas fa-clock"></i> Master Shift</div>
                    <div class="menu-item-modal"><i class="fas fa-calendar-days"></i> Master Jadwal</div>
                    <div class="menu-item-modal"><i class="fas fa-briefcase"></i> Master Jabatan</div>
                    <div class="menu-item-modal"><i class="fas fa-sitemap"></i> Master Departemen</div>
                    <div class="menu-item-modal"><i class="fas fa-chart-bar"></i> Rekap Kehadiran Admin</div>
                    <div class="menu-item-modal"><i class="fas fa-shield-halved"></i> Fraud Alert GPS</div>
                    <div class="menu-item-modal"><i class="fas fa-envelope"></i> Pengaturan SMTP Gmail</div>
                </div>
                <div class="about-sec-title"><i class="fas fa-hand-holding-heart" style="color:#ef4444"></i> Dukung Pengembang</div>
                <p style="font-size:13px;color:#475569;margin-bottom:12px">Jika aplikasi ini bermanfaat, dukung pengembangan melalui donasi sukarela 🙏</p>
                <div class="donasi-grid">
                    <div class="donasi-card">
                        <div class="donasi-card-top">
                            <div class="donasi-icon gopay"><span style="color:#fff;font-weight:900;font-size:13px">GP</span></div>
                            <div class="donasi-info">
                                <div class="donasi-label">GoPay / WhatsApp</div>
                                <div class="donasi-value">082177846209</div>
                                <div class="donasi-name">M. Wira Satria Buana</div>
                            </div>
                        </div>
                        <button class="copy-btn" onclick="copyText('082177846209',this)"><i class="fas fa-copy"></i> Salin Nomor</button>
                    </div>
                    <div class="donasi-card">
                        <div class="donasi-card-top">
                            <div class="donasi-icon bsi"><span style="color:#fff;font-weight:900;font-size:11px;letter-spacing:-1px">BSI</span></div>
                            <div class="donasi-info">
                                <div class="donasi-label">Bank BSI</div>
                                <div class="donasi-value">7134197557</div>
                                <div class="donasi-name">M. Wira Satria Buana</div>
                            </div>
                        </div>
                        <button class="copy-btn" onclick="copyText('7134197557',this)"><i class="fas fa-copy"></i> Salin Rekening</button>
                    </div>
                </div>
            </div>
            <div class="modal-main-footer">
                <span>© <?= date('Y') ?> DailyFix — Develop M. Wira Sb. S.Kom</span>
                <button class="close-about" onclick="closeAbout()"><i class="fas fa-xmark"></i> Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── OTP Logic ────────────────────────────────────────────────────────────────
<?php if ($step === 'otp'): ?>
const boxes = document.querySelectorAll('.otp-box');
boxes[0]?.focus();
boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        box.value = box.value.replace(/[^0-9]/g,'');
        if (box.value) {
            box.classList.add('filled');
            if (i < 5) boxes[i+1].focus();
            if ([...boxes].every(b => b.value)) setTimeout(() => document.getElementById('formOtp').submit(), 200);
        } else { box.classList.remove('filled'); }
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) {
            boxes[i-1].focus(); boxes[i-1].value = ''; boxes[i-1].classList.remove('filled');
        }
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        [...text].forEach((ch,j) => { if(boxes[j]){boxes[j].value=ch;boxes[j].classList.add('filled');} });
        if (text.length===6) setTimeout(()=>document.getElementById('formOtp').submit(),200);
        else if (boxes[text.length]) boxes[text.length].focus();
    });
});
let total=300;
const timerEl=document.getElementById('timerVal'),timerWrap=document.getElementById('timerWrap');
const iv=setInterval(()=>{total--;timerEl.textContent=Math.floor(total/60)+':'+String(total%60).padStart(2,'0');if(total<=0){clearInterval(iv);timerEl.textContent='Kadaluarsa';timerWrap.classList.add('expired');document.getElementById('btnVerify').disabled=true;}},1000);
let resend=60;
const rv=setInterval(()=>{resend--;document.getElementById('resendCount').textContent=resend;if(resend<=0){clearInterval(rv);const b=document.getElementById('btnResend');b.disabled=false;b.textContent='Kirim Ulang OTP';}},1000);
function resendOtp(){window.location.href='<?= APP_URL ?>/login.php?reset=1';}
<?php endif; ?>

// ── Modal Register ───────────────────────────────────────────────────────────
function openRegister(){
    document.getElementById('modalRegister').classList.add('open');
    document.body.style.overflow='hidden';
}
function closeRegister(){
    document.getElementById('modalRegister').classList.remove('open');
    document.body.style.overflow='';
    // Bersihkan URL agar refresh tidak auto-buka modal
    if (window.location.search) {
        history.replaceState(null,'','<?= APP_URL ?>/login.php');
    }
}
function submitReg(){
    const btn=document.getElementById('btnRegSubmit');
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Memproses...';
    document.getElementById('formRegModal').submit();
}

// ── Modal Tentang ────────────────────────────────────────────────────────────
function openAbout() {document.getElementById('modalAbout').classList.add('open'); document.body.style.overflow='hidden';}
function closeAbout(){document.getElementById('modalAbout').classList.remove('open'); document.body.style.overflow='';}

// ── Copy text ────────────────────────────────────────────────────────────────
function copyText(text,btn){
    navigator.clipboard.writeText(text).then(()=>{
        btn.classList.add('copied'); btn.innerHTML='<i class="fas fa-check"></i> Disalin!';
        setTimeout(()=>{btn.classList.remove('copied');btn.innerHTML='<i class="fas fa-copy"></i> Salin';},2200);
    });
}

// ── Auto-buka modal jika dari PRG redirect ───────────────────────────────────
<?php if ($regPosted): ?>
window.addEventListener('DOMContentLoaded', () => openRegister());
<?php endif; ?>
</script>

</body>
</html>