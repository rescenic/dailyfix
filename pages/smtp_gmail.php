<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Pengaturan SMTP Gmail';
$activePage = 'smtp_gmail';
$user       = currentUser();
$db         = getDB();

// Pastikan tabel ada
$db->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perusahaan_id INT NOT NULL,
    host VARCHAR(100) DEFAULT 'smtp.gmail.com',
    port INT DEFAULT 587,
    encryption ENUM('tls','ssl','none') DEFAULT 'tls',
    username VARCHAR(150),
    password VARCHAR(255),
    from_email VARCHAR(150),
    from_name VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_perusahaan (perusahaan_id),
    FOREIGN KEY (perusahaan_id) REFERENCES perusahaan(id) ON DELETE CASCADE
)");

$errors  = [];
$success = false;

// Ambil setting yang sudah ada
$stmtGet = $db->prepare("SELECT * FROM smtp_settings WHERE perusahaan_id=? LIMIT 1");
$stmtGet->execute([$user['perusahaan_id']]);
$smtp = $stmtGet->fetch();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $host       = sanitize($_POST['host']       ?? 'smtp.gmail.com');
        $port       = (int)($_POST['port']          ?? 587);
        $encryption = $_POST['encryption']           ?? 'tls';
        $username   = sanitize($_POST['username']   ?? '');
        $password   = $_POST['password']             ?? '';
        $from_email = sanitize($_POST['from_email'] ?? '');
        $from_name  = sanitize($_POST['from_name']  ?? '');
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if (!$username) $errors[] = 'Email Gmail wajib diisi.';
        if (!$from_email) $errors[] = 'Email pengirim wajib diisi.';

        // Kalau password dikosongkan, pakai yang lama
        if (empty($password) && $smtp) {
            $password = $smtp['password']; // tetap pakai yg lama
        } elseif (!empty($password)) {
            // Enkripsi sederhana password app (bukan produksi — pakai env di produksi)
            $password = base64_encode($password);
        }

        if (empty($errors)) {
            if ($smtp) {
                $db->prepare("UPDATE smtp_settings SET host=?,port=?,encryption=?,username=?,password=?,from_email=?,from_name=?,is_active=? WHERE perusahaan_id=?")
                   ->execute([$host,$port,$encryption,$username,$password,$from_email,$from_name,$is_active,$user['perusahaan_id']]);
            } else {
                $db->prepare("INSERT INTO smtp_settings (perusahaan_id,host,port,encryption,username,password,from_email,from_name,is_active) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$user['perusahaan_id'],$host,$port,$encryption,$username,$password,$from_email,$from_name,$is_active]);
            }
            // Reload
            $stmtGet->execute([$user['perusahaan_id']]);
            $smtp = $stmtGet->fetch();
            $success = true;
        }
    }

    // Test kirim email
    if ($action === 'test') {
        $to = sanitize($_POST['test_email'] ?? '');
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tujuan test tidak valid.';
        } else {
            $result = sendTestEmail($db, $user['perusahaan_id'], $to);
            if ($result === true) {
                redirect(APP_URL.'/pages/smtp_gmail.php', "Email test berhasil dikirim ke $to!", 'success');
            } else {
                $errors[] = 'Gagal kirim email: ' . $result;
            }
        }
    }
}

// Fungsi test email menggunakan PHPMailer jika ada, fallback ke mail()
function sendTestEmail($db, $perusahaan_id, $to) {
    $stmt = $db->prepare("SELECT * FROM smtp_settings WHERE perusahaan_id=? AND is_active=1");
    $stmt->execute([$perusahaan_id]);
    $s = $stmt->fetch();
    if (!$s) return 'Konfigurasi SMTP belum disimpan.';

    $pw = base64_decode($s['password']);

    // Cek apakah PHPMailer tersedia
    $phpmailerPaths = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../phpmailer/src/PHPMailer.php',
        __DIR__ . '/../PHPMailer/src/PHPMailer.php',
    ];
    $phpmailerFound = false;
    foreach ($phpmailerPaths as $p) {
        if (file_exists($p)) {
            require_once $p;
            require_once dirname($p) . '/SMTP.php';
            require_once dirname($p) . '/Exception.php';
            $phpmailerFound = true;
            break;
        }
    }

    if ($phpmailerFound) {
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
            $mail->Subject    = 'Test Email — DailyFix';
            $mail->isHTML(true);
            $mail->Body = '
                <div style="font-family:sans-serif;max-width:480px;margin:0 auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px">
                    <div style="background:#0f4c81;color:#fff;padding:16px 20px;border-radius:8px;margin-bottom:16px">
                        <strong style="font-size:20px">D</strong> &nbsp; <strong>DailyFix</strong>
                    </div>
                    <h2 style="color:#0f4c81">✅ Konfigurasi SMTP Berhasil!</h2>
                    <p style="color:#64748b;margin-top:8px">Email ini dikirim sebagai konfirmasi bahwa konfigurasi SMTP Gmail di DailyFix berjalan dengan baik.</p>
                    <hr style="margin:16px 0;border-color:#e2e8f0">
                    <p style="font-size:12px;color:#94a3b8">Dikirim otomatis oleh sistem DailyFix &mdash; ' . date('d/m/Y H:i:s') . '</p>
                </div>';
            $mail->send();
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    } else {
        // Fallback: PHP mail() biasa
        $headers  = "From: {$s['from_name']} <{$s['from_email']}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $ok = mail($to, 'Test Email — DailyFix',
            "Email test dari DailyFix.\nJika Anda menerima ini, konfigurasi email berjalan.\n\nDikirim: " . date('d/m/Y H:i:s'),
            $headers);
        return $ok ? true : 'Fungsi mail() gagal. Pastikan server mendukung pengiriman email.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.smtp-grid { display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start; }
@media(max-width:900px) { .smtp-grid { grid-template-columns:1fr; } }

.setting-section { background:#fff; border-radius:12px; border:1px solid var(--border); overflow:hidden; margin-bottom:16px; }
.setting-section-header {
    padding:14px 20px; background:var(--surface2);
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
}
.setting-section-header h3 { font-size:14px; font-weight:700; }
.setting-section-icon {
    width:32px; height:32px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; flex-shrink:0;
}
.setting-body { padding:20px; display:flex; flex-direction:column; gap:14px; }

.port-enc-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

.status-badge {
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;
}
.status-badge.active   { background:#dcfce7; color:#16a34a; }
.status-badge.inactive { background:#fee2e2; color:#dc2626; }

.toggle-switch {
    position:relative; display:inline-block; width:44px; height:24px;
}
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider {
    position:absolute; cursor:pointer; inset:0;
    background:#e2e8f0; border-radius:24px; transition:.3s;
}
.toggle-slider::before {
    content:''; position:absolute;
    height:18px; width:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.3s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
input:checked + .toggle-slider { background:var(--success); }
input:checked + .toggle-slider::before { transform:translateX(20px); }

.panduan-item {
    display:flex; gap:10px; align-items:flex-start;
    padding:10px 0; border-bottom:1px solid var(--border);
    font-size:13px;
}
.panduan-item:last-child { border:none; padding-bottom:0; }
.panduan-num {
    width:22px; height:22px; border-radius:50%;
    background:var(--primary); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:800; flex-shrink:0; margin-top:1px;
}
.panduan-item a { color:var(--primary); font-weight:600; }

.pw-show-wrap { position:relative; }
.pw-show-wrap input { padding-right:40px; }
.pw-show-btn {
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:var(--text-muted);
    cursor:pointer; padding:4px; font-size:13px;
}

.info-box {
    background:#eff6ff; border:1px solid #bfdbfe;
    border-radius:8px; padding:12px 14px;
    font-size:13px; color:#1e40af;
    display:flex; gap:8px; align-items:flex-start;
}
.info-box i { flex-shrink:0; margin-top:1px; }
</style>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h2><i class="fas fa-envelope-circle-check" style="color:var(--primary)"></i> Pengaturan SMTP Gmail</h2>
        <p>Konfigurasi email untuk notifikasi dan pengiriman laporan otomatis</p>
    </div>
    <?php if ($smtp): ?>
    <span class="status-badge <?= $smtp['is_active'] ? 'active' : 'inactive' ?>">
        <i class="fas fa-circle" style="font-size:8px"></i>
        <?= $smtp['is_active'] ? 'SMTP Aktif' : 'SMTP Nonaktif' ?>
    </span>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="fas fa-triangle-exclamation"></i>
    <div><ul style="margin:0;padding-left:1rem"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> Konfigurasi SMTP berhasil disimpan!
</div>
<?php endif; ?>

<div class="smtp-grid">

    <!-- Kiri: Form konfigurasi -->
    <div>
        <form method="POST" id="formSmtp">
        <input type="hidden" name="action" value="save">

        <!-- Server Settings -->
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#dbeafe;color:#2563eb">
                    <i class="fas fa-server"></i>
                </div>
                <h3>Konfigurasi Server SMTP</h3>
            </div>
            <div class="setting-body">
                <div class="form-group" style="margin:0">
                    <label class="form-label">SMTP Host <span class="req">*</span></label>
                    <input type="text" name="host" class="form-control"
                        value="<?= htmlspecialchars($smtp['host'] ?? 'smtp.gmail.com') ?>"
                        placeholder="smtp.gmail.com">
                    <div class="form-hint">Untuk Gmail gunakan: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px">smtp.gmail.com</code></div>
                </div>
                <div class="port-enc-grid">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Port</label>
                        <select name="port" class="form-select" id="portSelect">
                            <option value="587" <?= ($smtp['port']??587)==587?'selected':'' ?>>587 (TLS — Rekomendasi)</option>
                            <option value="465" <?= ($smtp['port']??0)==465?'selected':'' ?>>465 (SSL)</option>
                            <option value="25"  <?= ($smtp['port']??0)==25?'selected':''  ?>>25 (Tidak Aman)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Enkripsi</label>
                        <select name="encryption" class="form-select" id="encSelect">
                            <option value="tls" <?= ($smtp['encryption']??'tls')==='tls'?'selected':'' ?>>TLS (Rekomendasi)</option>
                            <option value="ssl" <?= ($smtp['encryption']??'')==='ssl'?'selected':'' ?>>SSL</option>
                            <option value="none"<?= ($smtp['encryption']??'')==='none'?'selected':'' ?>>None</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Akun Gmail -->
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fab fa-google"></i>
                </div>
                <h3>Akun Gmail</h3>
            </div>
            <div class="setting-body">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Email Gmail <span class="req">*</span></label>
                    <input type="email" name="username" class="form-control"
                        value="<?= htmlspecialchars($smtp['username'] ?? '') ?>"
                        placeholder="nama@gmail.com">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">
                        App Password Google
                        <?php if ($smtp && $smtp['password']): ?>
                        <span style="font-size:11px;color:var(--success);font-weight:400"><i class="fas fa-check-circle"></i> Sudah tersimpan</span>
                        <?php endif; ?>
                    </label>
                    <div class="pw-show-wrap">
                        <input type="password" name="password" id="pwInput" class="form-control"
                            placeholder="<?= $smtp && $smtp['password'] ? '••••••••••••••••' : 'xxxx xxxx xxxx xxxx' ?>">
                        <button type="button" class="pw-show-btn" onclick="togglePw()">
                            <i class="fas fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                    <div class="form-hint">
                        <?php if ($smtp && $smtp['password']): ?>
                        Kosongkan jika tidak ingin mengubah password.
                        <?php else: ?>
                        Bukan password Gmail biasa. Buat di: Google Account → Keamanan → App Passwords.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Identitas Pengirim -->
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#dcfce7;color:#16a34a">
                    <i class="fas fa-id-badge"></i>
                </div>
                <h3>Identitas Pengirim</h3>
            </div>
            <div class="setting-body">
                <div class="port-enc-grid">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Nama Pengirim</label>
                        <input type="text" name="from_name" class="form-control"
                            value="<?= htmlspecialchars($smtp['from_name'] ?? 'DailyFix Absensi') ?>"
                            placeholder="DailyFix Absensi">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Email Pengirim <span class="req">*</span></label>
                        <input type="email" name="from_email" class="form-control"
                            value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>"
                            placeholder="noreply@perusahaan.com">
                    </div>
                </div>
                <div class="form-hint" style="margin-top:0">
                    <i class="fas fa-info-circle"></i> Untuk Gmail, email pengirim harus sama dengan email akun Gmail di atas.
                </div>
            </div>
        </div>

        <!-- Status & Simpan -->
        <div class="setting-section">
            <div class="setting-body" style="flex-direction:row;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:10px">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" value="1" <?= ($smtp['is_active']??1)?'checked':'' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <div>
                        <div style="font-weight:600;font-size:13px">Aktifkan SMTP</div>
                        <div style="font-size:12px;color:var(--text-muted)">Matikan untuk gunakan fungsi mail() bawaan PHP</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Konfigurasi
                </button>
            </div>
        </div>

        </form>

        <!-- Test Email -->
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#ede9fe;color:#7c3aed">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h3>Kirim Email Test</h3>
            </div>
            <div class="setting-body">
                <?php if (!$smtp): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Simpan konfigurasi terlebih dahulu sebelum mengirim test email.
                </div>
                <?php else: ?>
                <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
                    <input type="hidden" name="action" value="test">
                    <div style="flex:1">
                        <label class="form-label">Kirim test ke email:</label>
                        <input type="email" name="test_email" class="form-control"
                            value="<?= htmlspecialchars($smtp['username'] ?? '') ?>"
                            placeholder="test@gmail.com" required>
                    </div>
                    <button type="submit" class="btn btn-outline" style="color:#7c3aed;border-color:#7c3aed">
                        <i class="fas fa-paper-plane"></i> Kirim Test
                    </button>
                </form>
                <div class="form-hint" style="margin-top:4px">
                    <i class="fas fa-info-circle"></i>
                    <?php
                    $phpmailerPaths = [
                        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
                        __DIR__ . '/../phpmailer/src/PHPMailer.php',
                        __DIR__ . '/../PHPMailer/src/PHPMailer.php',
                    ];
                    $found = false;
                    foreach ($phpmailerPaths as $p) { if (file_exists($p)) { $found=true; break; } }
                    echo $found
                        ? '<span style="color:var(--success)">✅ PHPMailer terdeteksi — SMTP akan digunakan</span>'
                        : '<span style="color:var(--warning)">⚠️ PHPMailer tidak ditemukan — akan pakai mail() bawaan PHP. <a href="#panduan-phpmailer">Cara install →</a></span>';
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info konfigurasi tersimpan -->
        <?php if ($smtp): ?>
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#f1f5f9;color:#64748b">
                    <i class="fas fa-circle-info"></i>
                </div>
                <h3>Konfigurasi Tersimpan</h3>
            </div>
            <div style="padding:14px 20px">
                <table style="min-width:unset;font-size:13px">
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none;width:130px">Host</td><td style="border:none;font-weight:600"><?= htmlspecialchars($smtp['host']) ?></td></tr>
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none">Port</td><td style="border:none;font-weight:600"><?= $smtp['port'] ?> (<?= strtoupper($smtp['encryption']) ?>)</td></tr>
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none">Username</td><td style="border:none;font-weight:600"><?= htmlspecialchars($smtp['username']) ?></td></tr>
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none">Nama Pengirim</td><td style="border:none;font-weight:600"><?= htmlspecialchars($smtp['from_name']) ?></td></tr>
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none">Email Pengirim</td><td style="border:none;font-weight:600"><?= htmlspecialchars($smtp['from_email']) ?></td></tr>
                    <tr><td style="padding:6px 14px 6px 0;color:var(--text-muted);border:none">Diperbarui</td><td style="border:none;color:var(--text-muted)"><?= tglIndonesia($smtp['updated_at'],'short') ?> <?= date('H:i',strtotime($smtp['updated_at'])) ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Kanan: Panduan -->
    <div>
        <!-- Panduan Gmail App Password -->
        <div class="setting-section" style="margin-bottom:16px">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fab fa-google"></i>
                </div>
                <h3>Cara Buat App Password Gmail</h3>
            </div>
            <div style="padding:16px 20px">
                <div class="panduan-item">
                    <div class="panduan-num">1</div>
                    <div>Buka <a href="https://myaccount.google.com/security" target="_blank">myaccount.google.com/security</a></div>
                </div>
                <div class="panduan-item">
                    <div class="panduan-num">2</div>
                    <div>Pastikan <strong>2-Step Verification</strong> sudah diaktifkan</div>
                </div>
                <div class="panduan-item">
                    <div class="panduan-num">3</div>
                    <div>Di kolom pencarian, ketik <strong>"App passwords"</strong> lalu klik</div>
                </div>
                <div class="panduan-item">
                    <div class="panduan-num">4</div>
                    <div>Klik <strong>"Create"</strong>, beri nama misal "DailyFix", klik <strong>Create</strong></div>
                </div>
                <div class="panduan-item">
                    <div class="panduan-num">5</div>
                    <div>Salin 16 karakter password yang muncul (contoh: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:11px">abcd efgh ijkl mnop</code>) ke field App Password di atas</div>
                </div>
                <div style="margin-top:12px;padding:10px 12px;background:#fef3c7;border-radius:8px;font-size:12px;color:#92400e">
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong>Penting:</strong> Jangan gunakan password Gmail biasa. Harus App Password khusus.
                </div>
            </div>
        </div>

        <!-- Panduan PHPMailer -->
        <div class="setting-section" id="panduan-phpmailer">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#ede9fe;color:#7c3aed">
                    <i class="fas fa-code"></i>
                </div>
                <h3>Install PHPMailer</h3>
            </div>
            <div style="padding:16px 20px;font-size:13px">
                <p style="color:var(--text-muted);margin-bottom:12px">PHPMailer diperlukan untuk SMTP Gmail. Tanpanya, sistem menggunakan <code>mail()</code> bawaan PHP yang tidak mendukung Gmail.</p>

                <p style="font-weight:700;margin-bottom:6px">Cara 1 — Composer (Rekomendasi)</p>
                <div style="background:#1e293b;color:#e2e8f0;padding:12px 14px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:12px;margin-bottom:14px;overflow-x:auto">
                    cd /Applications/XAMPP/xamppfiles/htdocs/dailyfix<br>
                    composer require phpmailer/phpmailer
                </div>

                <p style="font-weight:700;margin-bottom:6px">Cara 2 — Manual Download</p>
                <div class="panduan-item" style="padding:6px 0">
                    <div class="panduan-num" style="width:18px;height:18px;font-size:9px">1</div>
                    <div>Download dari <a href="https://github.com/PHPMailer/PHPMailer/releases" target="_blank">github.com/PHPMailer</a></div>
                </div>
                <div class="panduan-item" style="padding:6px 0">
                    <div class="panduan-num" style="width:18px;height:18px;font-size:9px">2</div>
                    <div>Ekstrak, salin folder <code style="background:#f1f5f9;padding:1px 4px;border-radius:3px">PHPMailer</code> ke root project</div>
                </div>
                <div class="panduan-item" style="padding:6px 0;border:none">
                    <div class="panduan-num" style="width:18px;height:18px;font-size:9px">3</div>
                    <div>Sistem akan otomatis mendeteksinya</div>
                </div>

                <div style="background:#1e293b;color:#e2e8f0;padding:10px 14px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:11.5px;margin-top:10px">
                    dailyfix/<br>
                    ├── PHPMailer/<br>
                    │&nbsp;&nbsp; └── src/<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ├── PHPMailer.php<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ├── SMTP.php<br>
                    │&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; └── Exception.php<br>
                    └── pages/smtp_gmail.php
                </div>
            </div>
        </div>

        <!-- Penggunaan di kode -->
        <div class="setting-section">
            <div class="setting-section-header">
                <div class="setting-section-icon" style="background:#dcfce7;color:#16a34a">
                    <i class="fas fa-plug"></i>
                </div>
                <h3>Penggunaan di Kode PHP</h3>
            </div>
            <div style="padding:14px 20px;font-size:12.5px;color:var(--text-muted)">
                <p style="margin-bottom:8px">Gunakan fungsi helper berikut untuk kirim email dari manapun di aplikasi:</p>
                <div style="background:#1e293b;color:#a5f3fc;padding:12px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:11.5px;overflow-x:auto;line-height:1.7">
                    <span style="color:#64748b">// Contoh kirim email</span><br>
                    <span style="color:#fb7185">$result</span> = <span style="color:#4ade80">sendSmtpEmail</span>(<br>
                    &nbsp;&nbsp;<span style="color:#fb7185">$db</span>,<br>
                    &nbsp;&nbsp;<span style="color:#fb7185">$perusahaan_id</span>,<br>
                    &nbsp;&nbsp;<span style="color:#fbbf24">'tujuan@email.com'</span>,<br>
                    &nbsp;&nbsp;<span style="color:#fbbf24">'Subjek Email'</span>,<br>
                    &nbsp;&nbsp;<span style="color:#fbbf24">'&lt;p&gt;Isi email HTML&lt;/p&gt;'</span><br>
                    );<br><br>
                    <span style="color:#64748b">// $result = true jika berhasil</span><br>
                    <span style="color:#64748b">// $result = 'pesan error' jika gagal</span>
                </div>
                <p style="margin-top:10px;font-size:12px">Fungsi <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px">sendSmtpEmail()</code> akan otomatis tersedia setelah SMTP disimpan dan config.php diperbarui.</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const inp  = document.getElementById('pwInput');
    const icon = document.getElementById('pwIcon');
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Sinkronkan port dan enkripsi
document.getElementById('portSelect').addEventListener('change', function() {
    const enc = document.getElementById('encSelect');
    if (this.value === '465') enc.value = 'ssl';
    else if (this.value === '587') enc.value = 'tls';
});
document.getElementById('encSelect').addEventListener('change', function() {
    const port = document.getElementById('portSelect');
    if (this.value === 'ssl') port.value = '465';
    else if (this.value === 'tls') port.value = '587';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>