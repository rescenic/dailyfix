<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$errors  = [];
$success = false;
$db      = getDB();

// Ambil daftar jabatan & departemen (perusahaan_id = 1 default)
$jabatanList    = $db->query("SELECT id, nama FROM jabatan    WHERE perusahaan_id = 1 ORDER BY nama ASC")->fetchAll();
$departemenList = $db->query("SELECT id, nama FROM departemen WHERE perusahaan_id = 1 ORDER BY nama ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama         = sanitize($_POST['nama']         ?? '');
    $nik          = sanitize($_POST['nik']          ?? '');
    $email        = sanitize($_POST['email']        ?? '');
    $telepon      = sanitize($_POST['telepon']      ?? '');
    $jabatan_id   = (int)($_POST['jabatan_id']      ?? 0);
    $departemen_id= (int)($_POST['departemen_id']   ?? 0);

    if (!$nama)   $errors[] = 'Nama lengkap wajib diisi.';
    if (!$nik)    $errors[] = 'NIK wajib diisi.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (!$jabatan_id)    $errors[] = 'Pilih jabatan Anda.';
    if (!$departemen_id) $errors[] = 'Pilih departemen Anda.';

    if (empty($errors)) {
        $cek = $db->prepare("SELECT id FROM karyawan WHERE email=? OR nik=?");
        $cek->execute([$email, $nik]);
        if ($cek->fetch()) $errors[] = 'Email atau NIK sudah terdaftar.';
    }

    if (empty($errors)) {
        $perusahaan_id = 1;
        try {
            $cols = $db->query("SHOW COLUMNS FROM karyawan")->fetchAll(PDO::FETCH_COLUMN);

            // Status = nonaktif — menunggu aktivasi admin
            $fields = ['perusahaan_id', 'nik', 'nama', 'email', 'telepon', 'role', 'status', 'tanggal_bergabung'];
            $values = [$perusahaan_id, $nik, $nama, $email, $telepon, 'karyawan', 'nonaktif', date('Y-m-d')];

            if (in_array('jabatan_id', $cols) && $jabatan_id) {
                $fields[] = 'jabatan_id'; $values[] = $jabatan_id;
            }
            if (in_array('departemen_id', $cols) && $departemen_id) {
                $fields[] = 'departemen_id'; $values[] = $departemen_id;
            }
            if (in_array('password', $cols)) {
                $fields[] = 'password';
                $values[] = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            }

            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO karyawan (" . implode(',', $fields) . ") VALUES ($placeholders)")
               ->execute($values);

            // ── Kirim email notifikasi pendaftaran ke user ──
            $jabatanNama    = '';
            $departemenNama = '';
            foreach ($jabatanList    as $j) { if ($j['id'] == $jabatan_id)    $jabatanNama    = $j['nama']; }
            foreach ($departemenList as $d) { if ($d['id'] == $departemen_id) $departemenNama = $d['nama']; }

            $emailBody = '
            <div style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;max-width:520px;margin:0 auto">
                <div style="background:linear-gradient(135deg,#0f4c81,#0a2d55);padding:28px;border-radius:12px 12px 0 0;text-align:center">
                    <div style="display:inline-block;background:linear-gradient(135deg,#00c9a7,#0ea5e9);width:52px;height:52px;border-radius:12px;line-height:52px;font-size:24px;font-weight:900;color:#fff;text-align:center">D</div>
                    <h1 style="color:#fff;font-size:20px;margin:10px 0 0;font-weight:800">DailyFix</h1>
                    <p style="color:rgba(255,255,255,.65);font-size:13px;margin:4px 0 0">Sistem Absensi Digital</p>
                </div>
                <div style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none">
                    <h2 style="color:#0f172a;font-size:18px;margin:0 0 6px">Pendaftaran Berhasil! 🎉</h2>
                    <p style="color:#64748b;font-size:14px;margin:0 0 24px">Halo <strong style="color:#0f172a">' . htmlspecialchars($nama) . '</strong>, akun Anda telah terdaftar di DailyFix.</p>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin-bottom:20px">
                        <div style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px">Detail Akun</div>
                        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
                            <tr><td style="padding:5px 0;color:#64748b;width:130px">NIK</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($nik) . '</td></tr>
                            <tr><td style="padding:5px 0;color:#64748b">Nama</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($nama) . '</td></tr>
                            <tr><td style="padding:5px 0;color:#64748b">Email</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($email) . '</td></tr>
                            <tr><td style="padding:5px 0;color:#64748b">Jabatan</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($jabatanNama ?: '-') . '</td></tr>
                            <tr><td style="padding:5px 0;color:#64748b">Departemen</td><td style="padding:5px 0;font-weight:600;color:#0f172a">' . htmlspecialchars($departemenNama ?: '-') . '</td></tr>
                        </table>
                    </div>

                    <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 16px;border-radius:0 8px 8px 0;margin-bottom:20px">
                        <div style="font-weight:700;color:#92400e;font-size:13px;margin-bottom:4px">⏳ Menunggu Aktivasi Admin</div>
                        <div style="color:#92400e;font-size:13px;line-height:1.6">Akun Anda sedang dalam proses verifikasi. Anda akan menerima email konfirmasi begitu admin mengaktifkan akun Anda. Proses ini biasanya memakan waktu 1×24 jam.</div>
                    </div>

                    <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0">Setelah akun diaktifkan, Anda dapat login menggunakan <strong>kode OTP</strong> yang dikirim ke email ini — tanpa perlu password.</p>
                </div>
                <div style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;text-align:center;font-size:11.5px;color:#94a3b8">
                    © ' . date('Y') . ' DailyFix — Sistem Absensi Digital
                </div>
            </div>';

            // Kirim jika SMTP aktif
            $smtpStmt = $db->prepare("SELECT * FROM smtp_settings WHERE perusahaan_id=? AND is_active=1 LIMIT 1");
            $smtpStmt->execute([$perusahaan_id]);
            if ($smtpStmt->fetch()) {
                sendSmtpEmail($db, $perusahaan_id, $email, 'Pendaftaran DailyFix Berhasil — Menunggu Aktivasi', $emailBody);
            }

            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — DailyFix</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #0a1628;
        }

        /* ── Left panel ── */
        .left-panel {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; padding: 60px;
            position: relative;
            background: linear-gradient(145deg, #0f4c81 0%, #0a2d55 60%, #061a33 100%);
            overflow: hidden;
        }
        .left-panel::before {
            content: ''; position: absolute; top: -120px; right: -120px;
            width: 420px; height: 420px; border-radius: 50%;
            background: radial-gradient(circle, rgba(0,201,167,.25) 0%, transparent 70%);
        }
        .left-panel::after {
            content: ''; position: absolute; bottom: -80px; left: -80px;
            width: 320px; height: 320px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);
        }
        .dots-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .brand-content { position: relative; z-index: 1; }
        .brand-logo {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #00c9a7, #0ea5e9);
            border-radius: 14px; display: flex; align-items: center;
            justify-content: center; font-size: 24px; font-weight: 900; color: #fff;
            margin-bottom: 28px; box-shadow: 0 8px 32px rgba(0,201,167,.4);
        }
        .brand-tagline {
            display: inline-block; font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase; color: #00c9a7;
            background: rgba(0,201,167,.12); border: 1px solid rgba(0,201,167,.3);
            padding: 4px 12px; border-radius: 20px; margin-bottom: 20px;
        }
        .brand-title {
            font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: 800; color: #fff;
            line-height: 1.25; margin-bottom: 16px;
        }
        .brand-title span { color: #00c9a7; }
        .brand-desc {
            font-size: 14px; color: rgba(255,255,255,.55);
            line-height: 1.75; max-width: 380px; margin-bottom: 36px;
        }
        .feature-list { display: flex; flex-direction: column; gap: 12px; }
        .feature-item {
            display: flex; align-items: center; gap: 12px;
            color: rgba(255,255,255,.7); font-size: 13px;
        }
        .feature-icon {
            width: 30px; height: 30px; border-radius: 8px;
            background: rgba(255,255,255,.08);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; color: #00c9a7; flex-shrink: 0;
        }

        /* ── Right panel ── */
        .right-panel {
            width: 480px; min-width: 480px; background: #fff;
            display: flex; flex-direction: column; justify-content: center;
            padding: 44px 48px; overflow-y: auto;
        }

        .form-header { margin-bottom: 24px; }
        .form-header h2 { font-size: 1.55rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .form-header p  { font-size: 13.5px; color: #64748b; }

        /* OTP info banner */
        .otp-info-banner {
            display: flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #eff6ff, #f0fdf4);
            border: 1px solid #bfdbfe;
            border-radius: 10px; padding: 10px 14px;
            font-size: 12.5px; color: #1e40af;
            margin-bottom: 20px;
        }
        .otp-info-banner i { color: #0ea5e9; font-size: 15px; flex-shrink: 0; }
        .otp-info-banner strong { color: #0f172a; }

        /* Input */
        .input-group { margin-bottom: 14px; }
        .input-group label {
            display: block; font-size: 12.5px; font-weight: 600;
            color: #374151; margin-bottom: 5px;
        }
        .input-group label .req { color: #ef4444; margin-left: 2px; }
        .input-field { position: relative; }
        .input-field .icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 13px; pointer-events: none; z-index: 1;
        }
        .input-field input,
        .input-field select {
            width: 100%; padding: 10px 14px 10px 40px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 13.5px; font-family: inherit; color: #0f172a;
            background: #f8fafc; outline: none;
            transition: border-color .2s, background .2s, box-shadow .2s;
            appearance: none;
        }
        .input-field input:focus,
        .input-field select:focus {
            border-color: #0f4c81; background: #fff;
            box-shadow: 0 0 0 3px rgba(15,76,129,.1);
        }
        .input-field input::placeholder { color: #94a3b8; }
        .input-field select { cursor: pointer; }
        .input-field .select-arrow {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 11px; pointer-events: none;
        }

        /* Grid 2 col */
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Divider */
        .section-divider {
            display: flex; align-items: center; gap: 10px;
            margin: 6px 0 14px;
            font-size: 11.5px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .section-divider::before,
        .section-divider::after {
            content: ''; flex: 1; height: 1px; background: #e2e8f0;
        }

        /* Alert */
        .alert-error {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 14px; background: #fef2f2;
            border: 1px solid #fecaca; border-left: 4px solid #ef4444;
            border-radius: 10px; margin-bottom: 16px;
            font-size: 13px; color: #991b1b;
            animation: shake .35s ease;
        }
        .alert-error ul { margin: 0; padding-left: 1rem; }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(4px)}
        }

        /* Success */
        .success-wrap { text-align: center; padding: 20px 0; }
        .success-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #00c9a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #fff; margin: 0 auto 20px;
            box-shadow: 0 8px 28px rgba(16,185,129,.35);
            animation: popIn .5s cubic-bezier(.68,-.55,.265,1.55);
        }
        @keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
        .success-wrap h2 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .success-wrap p  { font-size: 13.5px; color: #64748b; margin-bottom: 24px; line-height: 1.65; }

        /* Button */
        .btn-submit {
            width: 100%; padding: 12px 20px;
            background: linear-gradient(135deg, #0f4c81, #0a2d55);
            color: #fff; border: none; border-radius: 10px;
            font-size: 15px; font-weight: 700; font-family: inherit;
            cursor: pointer; margin-top: 6px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: transform .2s, box-shadow .2s;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(15,76,129,.4); }

        /* Footer */
        .form-footer { margin-top: 18px; text-align: center; font-size: 12px; color: #94a3b8; }
        .form-footer a { color: #0f4c81; font-weight: 600; text-decoration: none; }

        /* Mobile */
        @media(max-width:820px) {
            .left-panel { display: none; }
            body {
                background: linear-gradient(160deg, #0f4c81 0%, #0a2d55 50%, #061a33 100%);
                align-items: center; justify-content: center;
                padding: 20px; overflow: auto;
            }
            .right-panel {
                width: 100%; min-width: 0; max-width: 440px;
                padding: 36px 28px; border-radius: 20px;
                box-shadow: 0 24px 60px rgba(0,0,0,.35);
            }
            .form-grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Left panel -->
<div class="left-panel">
    <div class="dots-grid"></div>
    <div class="brand-content">
        <div class="brand-logo">D</div>
        <div class="brand-tagline">Daftar Akun Baru</div>
        <h1 class="brand-title">Gabung dan mulai<br><span>absen digital.</span></h1>
        <p class="brand-desc">
            Buat akun DailyFix Anda sekarang. Absensi berbasis GPS, verifikasi wajah, dan laporan otomatis — semua dalam satu sistem.
        </p>
        <div class="feature-list">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-key"></i></div>
                Login aman tanpa password — cukup OTP Email
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-map-location-dot"></i></div>
                Absensi GPS — validasi lokasi real-time
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-camera"></i></div>
                Verifikasi foto wajah setiap absen
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-shield-halved"></i></div>
                Anti fake GPS — 9 lapisan keamanan
            </div>
        </div>
    </div>
</div>

<!-- Right panel -->
<div class="right-panel">

<?php if ($success): ?>
    <!-- ══ SUCCESS STATE ══ -->
    <div class="success-wrap">
        <div class="success-icon"><i class="fas fa-envelope-circle-check"></i></div>
        <h2>Pendaftaran Berhasil! 🎉</h2>
        <p>
            Akun Anda telah terdaftar.<br>
            Email konfirmasi telah dikirim ke <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong>.
        </p>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:16px 18px;margin-bottom:24px;text-align:left">
            <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#92400e;font-size:13px;margin-bottom:6px">
                <i class="fas fa-clock"></i> Menunggu Aktivasi Admin
            </div>
            <p style="font-size:13px;color:#92400e;margin:0;line-height:1.65">
                Akun Anda perlu diaktifkan oleh admin terlebih dahulu sebelum bisa login. Anda akan menerima email pemberitahuan begitu akun diaktifkan.
            </p>
        </div>
        <a href="<?= APP_URL ?>/login.php"
           style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;
                  padding:12px 32px;background:linear-gradient(135deg,#0f4c81,#0a2d55);
                  color:#fff;border-radius:10px;font-weight:700;font-size:15px;
                  transition:transform .2s,box-shadow .2s"
           onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 24px rgba(15,76,129,.4)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <i class="fas fa-right-to-bracket"></i> Ke Halaman Login
        </a>
    </div>

<?php else: ?>

    <div class="form-header">
        <h2>Buat akun baru ✨</h2>
        <p>Isi data diri Anda untuk mendaftar ke DailyFix</p>
    </div>

    <!-- Info OTP -->
    <div class="otp-info-banner">
        <i class="fas fa-circle-info"></i>
        <span>Login menggunakan <strong>kode OTP</strong> yang dikirim ke email — tidak perlu password.</span>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <i class="fas fa-circle-exclamation" style="flex-shrink:0;margin-top:1px"></i>
        <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <!-- ── Data Diri ── -->
        <div class="section-divider">Data Diri</div>

        <div class="input-group">
            <label>Nama Lengkap <span class="req">*</span></label>
            <div class="input-field">
                <span class="icon"><i class="fas fa-user"></i></span>
                <input type="text" name="nama" placeholder="Nama lengkap sesuai KTP"
                    value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required autofocus>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="input-group">
                <label>NIK / No. Karyawan <span class="req">*</span></label>
                <div class="input-field">
                    <span class="icon"><i class="fas fa-id-card"></i></span>
                    <input type="text" name="nik" placeholder="Contoh: EMP001"
                        value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" required>
                </div>
            </div>
            <div class="input-group">
                <label>No. Telepon</label>
                <div class="input-field">
                    <span class="icon"><i class="fas fa-phone"></i></span>
                    <input type="tel" name="telepon" placeholder="08xxxxxxxxxx"
                        value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="input-group">
            <label>Alamat Email <span class="req">*</span></label>
            <div class="input-field">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" placeholder="nama@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div style="display:flex;align-items:center;gap:5px;margin-top:5px;font-size:11.5px;color:#0ea5e9">
                <i class="fas fa-circle-info" style="font-size:10px"></i>
                <span>Gunakan email aktif — kode OTP login akan dikirim ke sini setiap kali masuk.</span>
            </div>
        </div>

        <!-- ── Posisi ── -->
        <div class="section-divider">Posisi & Departemen</div>

        <div class="form-grid-2">
            <div class="input-group">
                <label>Jabatan <span class="req">*</span></label>
                <div class="input-field">
                    <span class="icon"><i class="fas fa-briefcase"></i></span>
                    <select name="jabatan_id" required>
                        <option value="">— Pilih Jabatan —</option>
                        <?php foreach ($jabatanList as $j): ?>
                        <option value="<?= $j['id'] ?>"
                            <?= ($_POST['jabatan_id'] ?? '') == $j['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($j['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($jabatanList)): ?>
                        <option disabled>Belum ada data jabatan</option>
                        <?php endif; ?>
                    </select>
                    <span class="select-arrow"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
            <div class="input-group">
                <label>Departemen <span class="req">*</span></label>
                <div class="input-field">
                    <span class="icon"><i class="fas fa-building"></i></span>
                    <select name="departemen_id" required>
                        <option value="">— Pilih Departemen —</option>
                        <?php foreach ($departemenList as $d): ?>
                        <option value="<?= $d['id'] ?>"
                            <?= ($_POST['departemen_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($departemenList)): ?>
                        <option disabled>Belum ada data departemen</option>
                        <?php endif; ?>
                    </select>
                    <span class="select-arrow"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-user-plus"></i> Buat Akun
        </button>
    </form>

    <div class="form-footer">
        Sudah punya akun? <a href="<?= APP_URL ?>/login.php">Masuk di sini</a>
        &nbsp;&mdash;&nbsp; &copy; <?= date('Y') ?> DailyFix
        <br>Develop M. Wira Sb. S. Kom
    </div>

<?php endif; ?>
</div>

</body>
</html>