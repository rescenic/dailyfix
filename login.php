<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$step     = $_SESSION['otp_step']  ?? 'email';
$otpEmail = $_SESSION['otp_email'] ?? '';
$error    = '';
$info     = '';
$pendingEmail = ''; // untuk tampil pesan pending

// ── STEP 1: Kirim OTP ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $email = sanitize($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan alamat email yang valid.';
    } else {
        $db   = getDB();

        // Cek user tanpa filter status dulu
        $stmt = $db->prepare("SELECT k.*, p.nama as perusahaan_nama FROM karyawan k
                              LEFT JOIN perusahaan p ON p.id = k.perusahaan_id
                              WHERE k.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Email tidak terdaftar di sistem.';
        } elseif ($user['status'] === 'nonaktif') {
            // Akun ada tapi belum diaktifkan admin
            $error = 'PENDING';
            $pendingEmail = $email;
        } elseif ($user['status'] === 'cuti') {
            $error = 'Akun Anda sedang dalam status cuti. Hubungi admin untuk informasi lebih lanjut.';
        } elseif ($user['status'] !== 'aktif') {
            $error = 'Akun Anda tidak aktif. Hubungi admin perusahaan Anda.';
        } else {
            // Status aktif — lanjut kirim OTP
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
                            ⚠️ Jangan bagikan kode ini kepada siapapun. Tim DailyFix tidak pernah meminta kode OTP Anda.
                        </div>
                    </div>
                    <div style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;text-align:center;font-size:11.5px;color:#94a3b8">
                        © ' . date('Y') . ' DailyFix — Sistem Absensi Digital
                    </div>
                </div>';

                $sent = false;
                if ($smtpOk) {
                    $result = sendSmtpEmail($db, $user['perusahaan_id'], $email, 'Kode OTP Login DailyFix — ' . $otp, $emailBody);
                    $sent   = ($result === true);
                }

                if (!$sent) {
                    $_SESSION['dev_otp'] = $otp;
                }

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

// ── STEP 2: Verifikasi OTP ────────────────────────────
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
                $_SESSION['lokasi_id']     = $user['lokasi_id'];

                unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id'], $_SESSION['dev_otp']);

                logActivity('LOGIN', 'Login berhasil via OTP dari ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                redirect(APP_URL . '/index.php');
            }
        }
    }
}

if (isset($_GET['reset'])) {
    unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id'], $_SESSION['dev_otp']);
    redirect(APP_URL . '/login.php');
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
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; background: #0a1628; overflow: hidden; }

        .left-panel { flex:1; display:flex; flex-direction:column; justify-content:center; padding:60px; position:relative; background:linear-gradient(145deg,#0f4c81 0%,#0a2d55 60%,#061a33 100%); overflow:hidden; }
        .left-panel::before { content:''; position:absolute; top:-120px; right:-120px; width:420px; height:420px; border-radius:50%; background:radial-gradient(circle,rgba(0,201,167,.25) 0%,transparent 70%); }
        .left-panel::after  { content:''; position:absolute; bottom:-80px; left:-80px; width:320px; height:320px; border-radius:50%; background:radial-gradient(circle,rgba(255,255,255,.06) 0%,transparent 70%); }
        .dots-grid { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.07) 1px,transparent 1px); background-size:32px 32px; }
        .brand-content { position:relative; z-index:1; }
        .brand-logo { width:56px; height:56px; background:linear-gradient(135deg,#00c9a7,#0ea5e9); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:900; color:#fff; margin-bottom:28px; box-shadow:0 8px 32px rgba(0,201,167,.4); }
        .brand-tagline { display:inline-block; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#00c9a7; background:rgba(0,201,167,.12); border:1px solid rgba(0,201,167,.3); padding:4px 12px; border-radius:20px; margin-bottom:20px; }
        .brand-title { font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; color:#fff; line-height:1.25; margin-bottom:16px; }
        .brand-title span { color:#00c9a7; }
        .brand-desc { font-size:14px; color:rgba(255,255,255,.55); line-height:1.75; max-width:380px; margin-bottom:36px; }
        .feature-list { display:flex; flex-direction:column; gap:12px; }
        .feature-item { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,.7); font-size:13px; }
        .feature-icon { width:30px; height:30px; border-radius:8px; background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; font-size:12px; color:#00c9a7; flex-shrink:0; }

        .right-panel { width:460px; min-width:460px; background:#fff; display:flex; flex-direction:column; justify-content:center; padding:52px 48px; overflow-y:auto; }
        .form-header { margin-bottom:28px; }
        .form-header h2 { font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:5px; }
        .form-header p  { font-size:13.5px; color:#64748b; }

        .input-group { margin-bottom:16px; }
        .input-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
        .input-field { position:relative; }
        .input-field .icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px; pointer-events:none; z-index:1; }
        .input-field input { width:100%; padding:11px 14px 11px 40px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; color:#0f172a; background:#f8fafc; outline:none; transition:border-color .2s,background .2s,box-shadow .2s; }
        .input-field input:focus { border-color:#0f4c81; background:#fff; box-shadow:0 0 0 3px rgba(15,76,129,.1); }
        .input-field input::placeholder { color:#94a3b8; }

        /* OTP boxes */
        .otp-wrap { display:flex; gap:10px; justify-content:center; margin:20px 0; }
        .otp-box { width:48px; height:56px; border:2px solid #e2e8f0; border-radius:12px; font-size:22px; font-weight:800; font-family:'JetBrains Mono',monospace; text-align:center; color:#0f172a; background:#f8fafc; outline:none; transition:border-color .2s,box-shadow .2s,background .2s; caret-color:#0f4c81; }
        .otp-box:focus { border-color:#0f4c81; box-shadow:0 0 0 3px rgba(15,76,129,.12); background:#fff; }
        .otp-box.filled { border-color:#00c9a7; background:#f0fdf9; }

        .email-badge { display:flex; align-items:center; gap:8px; background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:10px; font-size:13px; color:#1e40af; margin-bottom:16px; }
        .otp-timer { text-align:center; font-size:12.5px; color:#94a3b8; margin-bottom:12px; }
        .otp-timer span { color:#0f4c81; font-weight:700; }
        .otp-timer.expired span { color:#ef4444; }

        .dev-otp-box { background:#fef3c7; border:2px dashed #f59e0b; border-radius:12px; padding:14px 16px; margin-bottom:16px; text-align:center; }
        .dev-otp-box .dev-label { font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .dev-otp-code { font-family:'JetBrains Mono',monospace; font-size:28px; font-weight:900; letter-spacing:6px; color:#0f4c81; }

        /* ── Alert pending khusus ── */
        .alert-pending {
            background:#fffbeb; border:1px solid #fcd34d;
            border-left:4px solid #f59e0b; border-radius:10px;
            padding:14px 16px; margin-bottom:16px;
            animation: shake .35s ease;
        }
        .alert-pending-title { display:flex; align-items:center; gap:8px; font-weight:700; color:#92400e; font-size:14px; margin-bottom:6px; }
        .alert-pending p { font-size:13px; color:#92400e; line-height:1.65; margin:0; }

        .alert-error { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:10px; margin-bottom:16px; font-size:13px; color:#991b1b; animation:shake .35s ease; }
        .alert-info  { display:flex; align-items:flex-start; gap:10px; padding:11px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #3b82f6; border-radius:10px; margin-bottom:16px; font-size:13px; color:#1e40af; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(4px)} }

        .btn-submit { width:100%; padding:12px 20px; background:linear-gradient(135deg,#0f4c81,#0a2d55); color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; margin-top:4px; display:flex; align-items:center; justify-content:center; gap:8px; transition:transform .2s,box-shadow .2s; }
        .btn-submit:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(15,76,129,.4); }
        .btn-resend { background:none; border:none; color:#0f4c81; font-weight:700; cursor:pointer; font-size:13px; font-family:inherit; padding:0; text-decoration:underline; }
        .btn-resend:disabled { color:#94a3b8; cursor:not-allowed; text-decoration:none; }
        .btn-back { display:inline-flex; align-items:center; gap:6px; color:#64748b; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none; font-family:inherit; padding:0; margin-top:12px; }
        .btn-back:hover { color:#0f4c81; }

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
        .form-footer a { color:#0f4c81; font-weight:600; text-decoration:none; }

        /* Button tentang */
        .btn-tentang {
            display:inline-flex; align-items:center; gap:5px;
            background:none; border:1.5px solid #e2e8f0; color:#64748b;
            padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600;
            font-family:inherit; cursor:pointer; margin-top:12px;
            transition:border-color .2s, color .2s, background .2s;
        }
        .btn-tentang:hover { border-color:#0f4c81; color:#0f4c81; background:#eff6ff; }

        /* Modal tentang — landscape 2 kolom */
        .modal-about-overlay {
            position:fixed; inset:0; background:rgba(10,22,40,.7);
            backdrop-filter:blur(6px); z-index:9999;
            display:flex; align-items:center; justify-content:center;
            padding:16px; opacity:0; pointer-events:none;
            transition:opacity .3s;
        }
        .modal-about-overlay.open { opacity:1; pointer-events:all; }

        /* Container landscape */
        .modal-about {
            background:#fff; border-radius:20px;
            width:100%; max-width:860px;
            max-height:92vh;
            display:flex; flex-direction:row;
            box-shadow:0 32px 80px rgba(0,0,0,.35);
            overflow:hidden;
            transform:scale(.96) translateY(16px);
            transition:transform .3s cubic-bezier(.34,1.56,.64,1);
        }
        .modal-about-overlay.open .modal-about { transform:scale(1) translateY(0); }

        /* Sidebar kiri */
        .modal-sidebar {
            width:230px; min-width:230px;
            background:linear-gradient(160deg,#0f4c81 0%,#0a2d55 60%,#061a33 100%);
            padding:28px 22px; display:flex; flex-direction:column;
            align-items:center; text-align:center; position:relative; overflow:hidden;
        }
        .modal-sidebar::before {
            content:''; position:absolute; top:-60px; right:-60px;
            width:180px; height:180px; border-radius:50%;
            background:radial-gradient(circle,rgba(0,201,167,.2) 0%,transparent 70%);
        }
        .modal-sidebar::after {
            content:''; position:absolute; bottom:-40px; left:-40px;
            width:140px; height:140px; border-radius:50%;
            background:radial-gradient(circle,rgba(255,255,255,.05) 0%,transparent 70%);
        }
        .sidebar-dots {
            position:absolute; inset:0;
            background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px);
            background-size:24px 24px;
        }
        .sidebar-content { position:relative; z-index:1; width:100%; }
        .modal-about-logo {
            width:56px; height:56px; background:linear-gradient(135deg,#00c9a7,#0ea5e9);
            border-radius:14px; display:inline-flex; align-items:center; justify-content:center;
            font-size:26px; font-weight:900; color:#fff; margin-bottom:12px;
            box-shadow:0 8px 24px rgba(0,201,167,.45);
        }
        .sidebar-title { color:#fff; font-size:18px; font-weight:800; margin:0 0 3px; }
        .sidebar-sub   { color:rgba(255,255,255,.55); font-size:11.5px; margin:0 0 20px; }

        .sidebar-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(0,201,167,.15); border:1px solid rgba(0,201,167,.35);
            color:#00c9a7; padding:6px 12px; border-radius:20px;
            font-size:11.5px; font-weight:700; margin-bottom:20px;
        }
        .sidebar-features { width:100%; display:flex; flex-direction:column; gap:8px; }
        .sidebar-feat {
            display:flex; align-items:center; gap:9px;
            background:rgba(255,255,255,.07); border-radius:9px;
            padding:8px 10px; font-size:12px; color:rgba(255,255,255,.8); text-align:left;
        }
        .sidebar-feat i { color:#00c9a7; width:14px; text-align:center; flex-shrink:0; }

        .sidebar-copy {
            margin-top:auto; padding-top:20px;
            font-size:11px; color:rgba(255,255,255,.3); line-height:1.6;
        }

        /* Konten kanan */
        .modal-main {
            flex:1; overflow-y:auto; display:flex; flex-direction:column;
            min-width:0;
        }
        .modal-main-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:16px 24px; border-bottom:1px solid #f1f5f9; flex-shrink:0;
        }
        .modal-main-header h4 { font-size:15px; font-weight:800; color:#0f172a; margin:0; }
        .modal-x {
            background:#f1f5f9; border:none; color:#64748b;
            width:30px; height:30px; border-radius:8px; cursor:pointer;
            font-size:13px; display:flex; align-items:center; justify-content:center;
            transition:background .2s, color .2s; flex-shrink:0;
        }
        .modal-x:hover { background:#ef4444; color:#fff; }

        .modal-main-body { padding:20px 24px; flex:1; }

        .about-sec-title {
            font-size:10.5px; font-weight:800; text-transform:uppercase;
            letter-spacing:1.5px; color:#94a3b8; margin-bottom:10px;
            display:flex; align-items:center; gap:7px;
        }
        .about-sec-title::after { content:''; flex:1; height:1px; background:#f1f5f9; }

        .about-free-badge {
            display:inline-flex; align-items:center; gap:7px;
            background:#f0fdf4; border:1.5px solid #bbf7d0;
            color:#15803d; padding:7px 14px; border-radius:10px;
            font-size:12.5px; font-weight:700; margin-bottom:10px;
        }
        .about-text { font-size:13px; color:#475569; line-height:1.75; margin:0 0 8px; }
        .about-warning {
            background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444;
            border-radius:8px; padding:10px 14px;
            font-size:12.5px; color:#991b1b;
            display:flex; gap:8px; align-items:flex-start; margin-bottom:18px;
        }

        /* Donasi cards 2 kolom di landscape */
        .donasi-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .donasi-card {
            border:1.5px solid #e2e8f0; border-radius:12px; padding:14px 16px;
            display:flex; flex-direction:column; gap:10px;
            transition:border-color .2s, box-shadow .2s;
        }
        .donasi-card:hover { border-color:#0f4c81; box-shadow:0 3px 16px rgba(15,76,129,.12); }
        .donasi-card-top { display:flex; align-items:center; gap:10px; }
        .donasi-icon {
            width:40px; height:40px; border-radius:10px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; font-size:16px;
        }
        .donasi-icon.gopay { background:linear-gradient(135deg,#00aed6,#0070ba); }
        .donasi-icon.bsi   { background:linear-gradient(135deg,#00a650,#006633); }
        .donasi-info { flex:1; min-width:0; }
        .donasi-label { font-size:10.5px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .donasi-value { font-size:15px; font-weight:900; color:#0f172a; font-family:'JetBrains Mono',monospace; }
        .donasi-name  { font-size:11.5px; color:#64748b; margin-top:1px; }
        .copy-btn {
            width:100%; background:#f1f5f9; border:none; color:#64748b;
            padding:8px; border-radius:8px; font-size:12px; font-weight:700;
            cursor:pointer; font-family:inherit;
            transition:background .2s, color .2s;
            display:flex; align-items:center; justify-content:center; gap:6px;
        }
        .copy-btn:hover  { background:#0f4c81; color:#fff; }
        .copy-btn.copied { background:#10b981; color:#fff; }

        .modal-main-footer {
            padding:14px 24px; border-top:1px solid #f1f5f9;
            display:flex; align-items:center; justify-content:space-between;
            flex-shrink:0; flex-wrap:wrap; gap:10px;
        }
        .modal-main-footer span { font-size:11.5px; color:#94a3b8; }
        .close-about {
            background:#0f4c81; border:none; color:#fff;
            padding:9px 22px; border-radius:10px; font-size:13px; font-weight:700;
            cursor:pointer; font-family:inherit; transition:background .2s, transform .2s;
            display:flex; align-items:center; gap:6px;
        }
        .close-about:hover { background:#0a2d55; transform:translateY(-1px); }

        /* ── Responsive HP (portrait) ── */
        @media(max-width:640px) {
            .modal-about {
                flex-direction:column;
                max-width:100%; max-height:95vh;
                border-radius:16px;
            }
            .modal-sidebar {
                width:100%; min-width:0;
                padding:20px 20px 16px;
                flex-direction:row; text-align:left; align-items:center; gap:14px;
            }
            .modal-sidebar::before, .modal-sidebar::after { display:none; }
            .sidebar-features, .sidebar-copy { display:none; }
            .sidebar-badge { margin-bottom:0; }
            .modal-about-logo { width:42px; height:42px; font-size:18px; margin-bottom:0; flex-shrink:0; }
            .sidebar-title { font-size:15px; }
            .sidebar-sub   { margin-bottom:6px; }
            .sidebar-content { display:flex; flex-direction:column; }
            .donasi-grid { grid-template-columns:1fr; }
        }

        /* HP landscape (horizontal) */
        @media(max-height:500px) and (orientation:landscape) {
            .modal-about { max-height:96vh; }
            .modal-sidebar { width:180px; min-width:180px; padding:16px 14px; }
            .modal-about-logo { width:40px; height:40px; font-size:18px; }
            .sidebar-title { font-size:15px; }
            .sidebar-feat  { padding:6px 8px; font-size:11px; }
            .modal-main-body { padding:14px 18px; }
        }

        @media(max-width:820px) {
            .left-panel { display:none; }
            body { background:linear-gradient(160deg,#0f4c81 0%,#0a2d55 50%,#061a33 100%); align-items:center; justify-content:center; padding:20px; overflow:auto; }
            .right-panel { width:100%; min-width:0; max-width:440px; padding:36px 28px; border-radius:20px; box-shadow:0 24px 60px rgba(0,0,0,.35); }
        }
    </style>
</head>
<body>

<div class="left-panel">
    <div class="dots-grid"></div>
    <div class="brand-content">
        <div class="brand-logo">D</div>
        <div class="brand-tagline">Sistem Absensi Digital</div>
        <h1 class="brand-title">Hadir tepat waktu,<br><span>lebih mudah.</span></h1>
        <p class="brand-desc">DailyFix membantu perusahaan mengelola kehadiran karyawan secara akurat dengan GPS dan verifikasi foto wajah.</p>
        <div class="feature-list">
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-map-location-dot"></i></div> Absensi GPS — validasi lokasi real-time</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-camera"></i></div> Verifikasi foto wajah setiap absen</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-shield-halved"></i></div> Anti fake GPS — 9 lapisan keamanan</div>
            <div class="feature-item"><div class="feature-icon"><i class="fas fa-key"></i></div> Login tanpa password — cukup OTP Email</div>
        </div>
    </div>
</div>

<div class="right-panel">
    <div class="steps">
        <div class="step-item">
            <div class="step-line <?= $step==='otp' ? 'done' : '' ?>"></div>
            <div class="step-circle <?= $step==='email' ? 'active' : 'done' ?>">
                <?= $step==='email' ? '1' : '<i class="fas fa-check" style="font-size:11px"></i>' ?>
            </div>
            <div class="step-label <?= $step==='email' ? 'active' : '' ?>">Alamat Email</div>
        </div>
        <div class="step-item">
            <div class="step-line"></div>
            <div class="step-circle <?= $step==='otp' ? 'active' : 'wait' ?>">2</div>
            <div class="step-label <?= $step==='otp' ? 'active' : '' ?>">Kode OTP</div>
        </div>
    </div>

    <?php if ($step === 'email'): ?>
    <div class="form-header">
        <h2>Selamat datang 👋</h2>
        <p>Masukkan email Anda, kami kirim kode OTP untuk masuk</p>
    </div>

    <?php if ($error === 'PENDING'): ?>
    <!-- Alert khusus akun menunggu aktivasi -->
    <div class="alert-pending">
        <div class="alert-pending-title">
            <i class="fas fa-clock"></i> Akun Menunggu Aktivasi
        </div>
        <p>
            Akun dengan email <strong><?= htmlspecialchars($pendingEmail) ?></strong> sudah terdaftar namun belum diaktifkan oleh admin.<br><br>
            Anda akan menerima email notifikasi begitu akun diaktifkan. Jika sudah lama menunggu, hubungi admin perusahaan Anda.
        </p>
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
        <div class="dev-label">⚠️ Development Mode — SMTP belum dikonfigurasi</div>
        <div class="dev-otp-code"><?= implode(' ', str_split($devOtp)) ?></div>
        <div style="font-size:11px;color:#92400e;margin-top:6px">Kode ini hanya tampil karena SMTP belum diatur. <a href="<?= APP_URL ?>/pages/smtp_gmail.php" style="color:#0f4c81">Atur SMTP →</a></div>
    </div>
    <?php endif; ?>

    <form method="POST" id="formOtp" autocomplete="off">
        <input type="hidden" name="action" value="verify_otp">
        <div class="otp-wrap">
            <?php for($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-box" id="otp<?= $i ?>"
                name="otp[]" maxlength="1" pattern="[0-9]"
                inputmode="numeric" autocomplete="off">
            <?php endfor; ?>
        </div>
        <div class="otp-timer" id="timerWrap">
            Kode berlaku: <span id="timerVal">5:00</span>
        </div>
        <button type="submit" class="btn-submit" id="btnVerify">
            <i class="fas fa-shield-check"></i> Verifikasi & Masuk
        </button>
    </form>

    <div style="text-align:center;margin-top:14px">
        <span style="font-size:13px;color:#94a3b8">Tidak menerima kode? </span>
        <button class="btn-resend" id="btnResend" disabled onclick="resendOtp()">
            Kirim Ulang (<span id="resendCount">60</span>s)
        </button>
    </div>
    <div style="text-align:center">
        <a href="<?= APP_URL ?>/login.php?reset=1" class="btn-back">
            <i class="fas fa-arrow-left"></i> Ganti akun
        </a>
    </div>
    <?php endif; ?>

    <div class="form-footer">
        Belum punya akun? <a href="<?= APP_URL ?>/register.php">Buat Akun</a>
        &nbsp;&mdash;&nbsp; &copy; <?= date('Y') ?> DailyFix
        <br>Develop M. Wira Sb. S. Kom
        <br>
        <button class="btn-tentang" onclick="openAbout()">
            <i class="fas fa-circle-info"></i> Tentang Aplikasi
        </button>
    </div>
</div>

<!-- ══ Modal Tentang Aplikasi — Landscape ══ -->
<div class="modal-about-overlay" id="modalAbout" onclick="if(event.target===this)closeAbout()">
    <div class="modal-about">

        <!-- Sidebar Kiri -->
        <div class="modal-sidebar">
            <div class="sidebar-dots"></div>
            <div class="sidebar-content">
                <div class="modal-about-logo">D</div>
                <div class="sidebar-title">DailyFix</div>
                <div class="sidebar-sub">Sistem Absensi Digital v1.0</div>
                <div class="sidebar-badge">
                    <i class="fas fa-heart" style="color:#ef4444"></i> Gratis & Bebas
                </div>
                <div class="sidebar-features">
                    <div class="sidebar-feat"><i class="fas fa-map-location-dot"></i> Absensi GPS</div>
                    <div class="sidebar-feat"><i class="fas fa-camera"></i> Verifikasi Foto</div>
                    <div class="sidebar-feat"><i class="fas fa-shield-halved"></i> Anti Fake GPS</div>
                    <div class="sidebar-feat"><i class="fas fa-key"></i> Login OTP</div>
                    <div class="sidebar-feat"><i class="fas fa-chart-bar"></i> Laporan Otomatis</div>
                    <div class="sidebar-feat"><i class="fas fa-users"></i> Multi Pengguna</div>
                </div>
                <div class="sidebar-copy">
                    © <?= date('Y') ?> DailyFix<br>Hak Cipta Dilindungi
                </div>
            </div>
        </div>

        <!-- Konten Kanan -->
        <div class="modal-main">
            <div class="modal-main-header">
                <h4><i class="fas fa-circle-info" style="color:#0ea5e9;margin-right:6px"></i> Tentang Aplikasi</h4>
                <button class="modal-x" onclick="closeAbout()"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="modal-main-body">

                <!-- Info bebas -->
                <div class="about-sec-title"><i class="fas fa-info" style="color:#0ea5e9"></i> Informasi</div>
                <div class="about-free-badge">
                    <i class="fas fa-heart" style="color:#ef4444"></i> Aplikasi Gratis & Open Source
                </div>
                <p class="about-text">
                    DailyFix dikembangkan secara independen oleh <strong>M. Wira Satria Buana, S.Kom</strong> dan dibagikan secara <strong>gratis</strong> untuk membantu perusahaan dan organisasi mengelola kehadiran karyawan secara akurat dan modern.
                </p>
                <div class="about-warning">
                    <i class="fas fa-ban" style="flex-shrink:0;margin-top:1px"></i>
                    <div><strong>Larangan Keras:</strong> Aplikasi ini <strong>tidak boleh diperjualbelikan</strong> dalam bentuk apapun. Redistribusi komersial tanpa izin tertulis dari pengembang adalah pelanggaran hak cipta.</div>
                </div>

                <!-- Donasi -->
                <div class="about-sec-title"><i class="fas fa-hand-holding-heart" style="color:#ef4444"></i> Dukung Pengembang</div>
                <p class="about-text" style="margin-bottom:12px">Jika aplikasi ini bermanfaat, Anda bisa mendukung pengembangan melalui donasi sukarela 🙏</p>

                <div class="donasi-grid">
                    <!-- GoPay -->
                    <div class="donasi-card">
                        <div class="donasi-card-top">
                            <div class="donasi-icon gopay">
                                <span style="color:#fff;font-weight:900;font-size:13px">GP</span>
                            </div>
                            <div class="donasi-info">
                                <div class="donasi-label">GoPay</div>
                                <div class="donasi-value">082177846209</div>
                                <div class="donasi-name">M. Wira Satria Buana</div>
                            </div>
                        </div>
                        <button class="copy-btn" onclick="copyText('082177846209', this)">
                            <i class="fas fa-copy"></i> Salin Nomor
                        </button>
                    </div>

                    <!-- BSI -->
                    <div class="donasi-card">
                        <div class="donasi-card-top">
                            <div class="donasi-icon bsi">
                                <span style="color:#fff;font-weight:900;font-size:11px;letter-spacing:-1px">BSI</span>
                            </div>
                            <div class="donasi-info">
                                <div class="donasi-label">Bank BSI</div>
                                <div class="donasi-value">7134197557</div>
                                <div class="donasi-name">M. Wira Satria Buana</div>
                            </div>
                        </div>
                        <button class="copy-btn" onclick="copyText('7134197557', this)">
                            <i class="fas fa-copy"></i> Salin Rekening
                        </button>
                    </div>
                </div>

            </div>

            <div class="modal-main-footer">
                <span>© <?= date('Y') ?> DailyFix — Develop M. Wira Sb. S.Kom</span>
                <button class="close-about" onclick="closeAbout()">
                    <i class="fas fa-xmark"></i> Tutup
                </button>
            </div>
        </div>

    </div>
</div>

<script>
<?php if ($step === 'otp'): ?>
const boxes = document.querySelectorAll('.otp-box');
boxes[0]?.focus();
boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        box.value = box.value.replace(/[^0-9]/g, '');
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
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        [...text].forEach((ch, j) => { if (boxes[j]) { boxes[j].value = ch; boxes[j].classList.add('filled'); } });
        if (text.length === 6) setTimeout(() => document.getElementById('formOtp').submit(), 200);
        else if (boxes[text.length]) boxes[text.length].focus();
    });
});

let total = 300;
const timerEl = document.getElementById('timerVal');
const timerWrap = document.getElementById('timerWrap');
const iv = setInterval(() => {
    total--;
    const m = Math.floor(total/60), s = total%60;
    timerEl.textContent = m + ':' + String(s).padStart(2,'0');
    if (total <= 0) {
        clearInterval(iv);
        timerEl.textContent = 'Kadaluarsa';
        timerWrap.classList.add('expired');
        document.getElementById('btnVerify').disabled = true;
    }
}, 1000);

let resend = 60;
const resendBtn = document.getElementById('btnResend');
const resendEl  = document.getElementById('resendCount');
const rv = setInterval(() => {
    resend--;
    resendEl.textContent = resend;
    if (resend <= 0) {
        clearInterval(rv);
        resendBtn.disabled = false;
        resendBtn.textContent = 'Kirim Ulang OTP';
    }
}, 1000);

function resendOtp() { window.location.href = '<?= APP_URL ?>/login.php?reset=1'; }
<?php endif; ?>

function openAbout()  { document.getElementById('modalAbout').classList.add('open'); document.body.style.overflow='hidden'; }
function closeAbout() { document.getElementById('modalAbout').classList.remove('open'); document.body.style.overflow=''; }

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fas fa-check"></i> Disalin!';
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="fas fa-copy"></i> Salin';
        }, 2000);
    });
}
</script>
</body>
</html>