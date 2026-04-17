<?php
ob_start(); // Buffer output — wajib di baris PERTAMA agar header bisa dikirim

require_once __DIR__ . '/../includes/config.php';
requireLogin();

// Paksa no-cache SETELAH ob_start
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$db      = getDB();
$me      = currentUser();
$user_id = (int)$me['id'];
$errors  = [];

// ─── Handle POST dulu (sebelum fetch) ────────────────────────────────────────
// Supaya redirect terjadi sebelum ada output apapun
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update Profil ──────────────────────────────────────────────────────
    if ($action === 'update_profil') {
        $nama          = sanitize($_POST['nama']       ?? '');
        $telepon       = sanitize($_POST['telepon']    ?? '');
        $email         = sanitize($_POST['email']      ?? '');
        $jabatan_id    = (int)($_POST['jabatan_id']    ?? 0);
        $departemen_id = (int)($_POST['departemen_id'] ?? 0);

        if (empty($nama))  $errors[] = 'Nama tidak boleh kosong.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';

        if (empty($errors)) {
            $cek = $db->prepare("SELECT id FROM karyawan WHERE email = ? AND id != ?");
            $cek->execute([$email, $user_id]);
            if ($cek->fetch()) $errors[] = 'Email sudah digunakan akun lain.';
        }

        if (empty($errors)) {
            $jid = $jabatan_id    > 0 ? $jabatan_id    : null;
            $did = $departemen_id > 0 ? $departemen_id : null;

            $db->prepare("
                UPDATE karyawan
                SET nama = ?, telepon = ?, email = ?,
                    jabatan_id = ?, departemen_id = ?
                WHERE id = ?
            ")->execute([$nama, $telepon, $email, $jid, $did, $user_id]);

            $_SESSION['nama']  = $nama;
            $_SESSION['email'] = $email;
            logActivity('UPDATE_PROFIL', 'Memperbarui data profil');

            $_SESSION['flash'] = ['message' => 'Profil berhasil diperbarui!', 'type' => 'success'];
            ob_end_clean();
            header('Location: ' . APP_URL . '/pages/profil.php');
            exit;
        }
    }

    // ── Ganti Password ─────────────────────────────────────────────────────
    if ($action === 'ganti_password') {
        // Fetch user untuk verifikasi password lama
        $uStmt = $db->prepare("SELECT password FROM karyawan WHERE id = ? LIMIT 1");
        $uStmt->execute([$user_id]);
        $uRow = $uStmt->fetch();

        $password_lama       = $_POST['password_lama']       ?? '';
        $password_baru       = $_POST['password_baru']       ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        if (!empty($uRow['password'])) {
            if (!password_verify($password_lama, $uRow['password']))
                $errors[] = 'Password lama tidak sesuai.';
        }
        if (strlen($password_baru) < 6)             $errors[] = 'Password baru minimal 6 karakter.';
        if ($password_baru !== $password_konfirmasi) $errors[] = 'Konfirmasi password tidak cocok.';

        if (empty($errors)) {
            $db->prepare("UPDATE karyawan SET password = ? WHERE id = ?")
               ->execute([password_hash($password_baru, PASSWORD_DEFAULT), $user_id]);
            logActivity('GANTI_PASSWORD', 'Mengganti password akun');
            $_SESSION['flash'] = ['message' => 'Password berhasil diubah!', 'type' => 'success'];
            ob_end_clean();
            header('Location: ' . APP_URL . '/pages/profil.php');
            exit;
        }
    }
}

// ─── Fetch user FRESH dari DB — query langsung tanpa JOIN dulu ───────────────
// Ambil raw data karyawan
$stmt = $db->prepare("SELECT * FROM karyawan WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    ob_end_clean();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ─── Resolve nama jabatan langsung by id (paling andal, tidak tergantung JOIN) ─
$user['jabatan_nama']    = '';
$user['departemen_nama'] = '';
$user['perusahaan_nama'] = '';

if (!empty($user['jabatan_id'])) {
    try {
        $r = $db->prepare("SELECT nama FROM jabatan WHERE id = ? LIMIT 1");
        $r->execute([(int)$user['jabatan_id']]);
        $row = $r->fetch();
        if ($row) $user['jabatan_nama'] = $row['nama'];
    } catch (Exception $e) {}
}

if (!empty($user['departemen_id'])) {
    try {
        $r = $db->prepare("SELECT nama FROM departemen WHERE id = ? LIMIT 1");
        $r->execute([(int)$user['departemen_id']]);
        $row = $r->fetch();
        if ($row) $user['departemen_nama'] = $row['nama'];
    } catch (Exception $e) {}
}

if (!empty($user['perusahaan_id'])) {
    try {
        $r = $db->prepare("SELECT nama FROM perusahaan WHERE id = ? LIMIT 1");
        $r->execute([(int)$user['perusahaan_id']]);
        $row = $r->fetch();
        if ($row) $user['perusahaan_nama'] = $row['nama'];
    } catch (Exception $e) {}
}

// ─── Daftar jabatan & departemen untuk dropdown ───────────────────────────────
$perusahaan_id = (int)($user['perusahaan_id'] ?? 1);

$jabatanList = [];
try {
    $s = $db->prepare("SELECT id, nama FROM jabatan WHERE perusahaan_id = ? ORDER BY nama ASC");
    $s->execute([$perusahaan_id]);
    $jabatanList = $s->fetchAll();
} catch (Exception $e) { $jabatanList = []; }

$departemenList = [];
try {
    $s = $db->prepare("SELECT id, nama FROM departemen WHERE perusahaan_id = ? ORDER BY nama ASC");
    $s->execute([$perusahaan_id]);
    $departemenList = $s->fetchAll();
} catch (Exception $e) { $departemenList = []; }

// ─── Statistik bulan ini ──────────────────────────────────────────────────────
$bulan = date('m'); $tahun = date('Y');
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status_kehadiran IN ('hadir','terlambat') THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status_kehadiran = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN status_kehadiran = 'izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(COALESCE(terlambat_detik, 0)) AS total_terlambat_detik,
            SUM(COALESCE(durasi_kerja,    0)) AS total_durasi
        FROM absensi
        WHERE karyawan_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = [];
}

// ─── Log aktivitas ────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT * FROM log_aktivitas WHERE karyawan_id = ? ORDER BY created_at DESC LIMIT 15");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
}

// ─── Helper format ────────────────────────────────────────────────────────────
function fmtDetik($d) {
    if (!$d) return '-';
    $j = floor($d/3600); $m = floor(($d%3600)/60); $s = $d%60;
    return trim(($j?"$j".'j ':'').($m?"$m".'m ':'').($s&&!$j?"$s".'d':'')) ?: '-';
}
function fmtMenit($m) {
    if (!$m) return '-';
    $j = floor($m/60); $s = $m%60;
    return ($j?"$j".'j ':'').($s?"$s".'m':'');
}
$telatStr = fmtDetik((int)($stats['total_terlambat_detik'] ?? 0));
$jamStr   = fmtMenit((int)($stats['total_durasi'] ?? 0));

// ─── Flash message ────────────────────────────────────────────────────────────
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$pageTitle  = 'Profil Saya';
$activePage = 'profil';
include __DIR__ . '/../includes/header.php';
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h2><i class="fas fa-user-circle" style="color:var(--primary);margin-right:.4rem"></i> Profil Saya</h2>
        <p>Kelola informasi akun dan keamanan Anda</p>
    </div>
</div>

<!-- Flash -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"
     style="margin-bottom:1rem;display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:10px">
    <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Error -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:10px">
    <div style="display:flex;align-items:flex-start;gap:.6rem">
        <i class="fas fa-exclamation-circle" style="margin-top:.15rem;flex-shrink:0"></i>
        <ul style="margin:0;padding-left:1.1rem">
            <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- GRID -->
<div class="prf-grid">

    <!-- ══ SIDEBAR KIRI ═══════════════════════════════════════════════════ -->
    <aside class="prf-sidebar">

        <!-- Kartu identitas -->
        <div class="card prf-id-card">
            <div class="prf-banner"><div class="prf-banner-dots"></div></div>
            <div class="prf-avatar-wrap">
                <div class="prf-avatar"><?= strtoupper(substr($user['nama'], 0, 2)) ?></div>
                <span class="prf-status-dot <?= ($user['status'] ?? '') === 'aktif' ? 'dot-aktif' : 'dot-nonaktif' ?>"></span>
            </div>
            <div class="prf-id-body">
                <h2 class="prf-nama"><?= htmlspecialchars($user['nama']) ?></h2>
                <p class="prf-nik"><i class="fas fa-id-card"></i> <?= htmlspecialchars($user['nik']) ?></p>
                <div class="prf-badges">
                    <span class="prf-badge prf-badge-role">
                        <i class="fas <?= ($user['role'] ?? '') === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                        <?= ucfirst($user['role'] ?? 'karyawan') ?>
                    </span>
                    <span class="prf-badge <?= ($user['status'] ?? '') === 'aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>">
                        <i class="fas fa-circle" style="font-size:.4rem"></i>
                        <?= ucfirst($user['status'] ?? 'aktif') ?>
                    </span>
                </div>
                <div class="prf-info-list">
                    <div class="prf-info-row">
                        <span class="prf-info-icon"><i class="fas fa-envelope"></i></span>
                        <span class="prf-info-val"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <?php if (!empty($user['telepon'])): ?>
                    <div class="prf-info-row">
                        <span class="prf-info-icon"><i class="fas fa-phone"></i></span>
                        <span class="prf-info-val"><?= htmlspecialchars($user['telepon']) ?></span>
                    </div>
                    <?php endif; ?>
                    <!-- Jabatan -->
                    <div class="prf-info-row <?= empty($user['jabatan_nama']) ? 'prf-info-empty' : '' ?>">
                        <span class="prf-info-icon"><i class="fas fa-briefcase"></i></span>
                        <span class="prf-info-val">
                            <?= !empty($user['jabatan_nama'])
                                ? htmlspecialchars($user['jabatan_nama'])
                                : 'Jabatan belum dipilih' ?>
                        </span>
                    </div>
                    <!-- Departemen -->
                    <div class="prf-info-row <?= empty($user['departemen_nama']) ? 'prf-info-empty' : '' ?>">
                        <span class="prf-info-icon"><i class="fas fa-building"></i></span>
                        <span class="prf-info-val">
                            <?= !empty($user['departemen_nama'])
                                ? htmlspecialchars($user['departemen_nama'])
                                : 'Departemen belum dipilih' ?>
                        </span>
                    </div>
                    <?php if (!empty($user['perusahaan_nama'])): ?>
                    <div class="prf-info-row">
                        <span class="prf-info-icon"><i class="fas fa-city"></i></span>
                        <span class="prf-info-val"><?= htmlspecialchars($user['perusahaan_nama']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="prf-info-row">
                        <span class="prf-info-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="prf-info-val">Bergabung <?= tglIndonesia($user['tanggal_bergabung'] ?? null, 'short') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik -->
        <div class="card">
            <div class="card-header" style="padding:.75rem 1rem;border-bottom:1px solid var(--border)">
                <h3 style="font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.4rem;margin:0">
                    <i class="fas fa-chart-bar" style="color:var(--primary)"></i>
                    Statistik <?= tglIndonesia(null,'bulan').' '.date('Y') ?>
                </h3>
            </div>
            <div class="prf-stats">
                <?php
                $statItems = [
                    ['fa-calendar-check',       '#10b981', 'rgba(16,185,129,.1)',  'Hari Hadir',          (int)($stats['hadir']??0),     'hari'],
                    ['fa-clock',                '#f59e0b', 'rgba(245,158,11,.1)',  'Terlambat',           (int)($stats['terlambat']??0), 'kali'],
                    ['fa-umbrella-beach',       '#3b82f6', 'rgba(59,130,246,.1)',  'Izin',                (int)($stats['izin']??0),      'hari'],
                    ['fa-stopwatch',            'var(--primary)','rgba(15,76,129,.1)', 'Total Jam Kerja', $jamStr ?: '-',                ''],
                    ['fa-triangle-exclamation', '#ef4444', 'rgba(239,68,68,.1)',   'Total Keterlambatan', $telatStr,                     ''],
                ];
                foreach ($statItems as $i => [$ico,$clr,$bg,$lbl,$val,$unit]):
                ?>
                <div class="prf-stat-item" <?= $i===count($statItems)-1?'style="border-bottom:none"':'' ?>>
                    <div class="prf-stat-icon" style="background:<?= $bg ?>;color:<?= $clr ?>">
                        <i class="fas <?= $ico ?>"></i>
                    </div>
                    <div class="prf-stat-body">
                        <span class="prf-stat-label"><?= $lbl ?></span>
                        <span class="prf-stat-val" style="color:<?= $clr ?>">
                            <?= $val ?><?= $unit ? " <small>$unit</small>" : '' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </aside>

    <!-- ══ KONTEN KANAN ════════════════════════════════════════════════════ -->
    <div class="prf-main">

        <!-- Edit Profil -->
        <div class="card">
            <div class="card-header prf-card-header">
                <div class="prf-card-header-icon" style="background:rgba(15,76,129,.1);color:var(--primary)">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div>
                    <h3>Edit Informasi Profil</h3>
                    <p>Perbarui data diri, jabatan, dan departemen Anda</p>
                </div>
            </div>
            <form method="POST" class="prf-form">
                <input type="hidden" name="action" value="update_profil">

                <div class="prf-form-grid">
                    <div class="prf-field">
                        <label>Nama Lengkap <span class="req">*</span></label>
                        <div class="prf-input-wrap">
                            <span class="prf-input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" name="nama" class="form-control prf-input"
                                value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>
                    </div>
                    <div class="prf-field">
                        <label>NIK / No. Karyawan</label>
                        <div class="prf-input-wrap">
                            <span class="prf-input-icon"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control prf-input prf-input-disabled"
                                value="<?= htmlspecialchars($user['nik']) ?>" disabled>
                        </div>
                        <span class="prf-hint"><i class="fas fa-lock" style="font-size:.55rem"></i> NIK tidak dapat diubah</span>
                    </div>
                </div>

                <div class="prf-form-grid">
                    <div class="prf-field">
                        <label>Alamat Email <span class="req">*</span></label>
                        <div class="prf-input-wrap">
                            <span class="prf-input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control prf-input"
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    <div class="prf-field">
                        <label>No. Telepon</label>
                        <div class="prf-input-wrap">
                            <span class="prf-input-icon"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="telepon" class="form-control prf-input"
                                value="<?= htmlspecialchars($user['telepon'] ?? '') ?>"
                                placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>

                <!-- Jabatan & Departemen — dropdown bisa diedit user -->
                <div class="prf-form-grid">
                    <div class="prf-field">
                        <label>Jabatan</label>
                        <div class="prf-input-wrap prf-select-wrap">
                            <span class="prf-input-icon"><i class="fas fa-briefcase"></i></span>
                            <select name="jabatan_id" class="form-control prf-input prf-select">
                                <option value="0">— Pilih Jabatan —</option>
                                <?php foreach ($jabatanList as $j): ?>
                                <option value="<?= (int)$j['id'] ?>"
                                    <?= ((int)($user['jabatan_id'] ?? 0) === (int)$j['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($j['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="prf-select-arrow"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <?php if (!empty($user['jabatan_nama'])): ?>
                        <span class="prf-hint prf-hint-ok">
                            <i class="fas fa-check-circle"></i>
                            Terpilih: <strong><?= htmlspecialchars($user['jabatan_nama']) ?></strong>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="prf-field">
                        <label>Departemen</label>
                        <div class="prf-input-wrap prf-select-wrap">
                            <span class="prf-input-icon"><i class="fas fa-building"></i></span>
                            <select name="departemen_id" class="form-control prf-input prf-select">
                                <option value="0">— Pilih Departemen —</option>
                                <?php foreach ($departemenList as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"
                                    <?= ((int)($user['departemen_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="prf-select-arrow"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <?php if (!empty($user['departemen_nama'])): ?>
                        <span class="prf-hint prf-hint-ok">
                            <i class="fas fa-check-circle"></i>
                            Terpilih: <strong><?= htmlspecialchars($user['departemen_nama']) ?></strong>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="prf-form-footer">
                    <button type="submit" class="prf-btn-save">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>

        <!-- Keamanan -->
        <div class="card">
            <div class="card-header prf-card-header">
                <div class="prf-card-header-icon" style="background:rgba(245,158,11,.1);color:#f59e0b">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <h3>Keamanan Akun</h3>
                    <p>Ubah password untuk menjaga keamanan akun Anda</p>
                </div>
            </div>
            <form method="POST" class="prf-form" id="formPassword">
                <input type="hidden" name="action" value="ganti_password">

                <?php if (!empty($user['password'])): ?>
                <div class="prf-field">
                    <label>Password Saat Ini <span class="req">*</span></label>
                    <div class="prf-input-wrap prf-pw-wrap">
                        <span class="prf-input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password_lama" id="pw_lama"
                            class="form-control prf-input" required placeholder="••••••••">
                        <button type="button" class="prf-eye-btn" onclick="togglePw('pw_lama',this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="prf-otp-notice">
                    <i class="fas fa-circle-info"></i>
                    <span>Akun Anda menggunakan login <strong>OTP</strong>. Anda dapat mengatur password di bawah ini.</span>
                </div>
                <?php endif; ?>

                <div class="prf-form-grid">
                    <div class="prf-field">
                        <label>Password Baru <span class="req">*</span></label>
                        <div class="prf-input-wrap prf-pw-wrap">
                            <span class="prf-input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" name="password_baru" id="pw_baru"
                                class="form-control prf-input" minlength="6" required placeholder="Min. 6 karakter">
                            <button type="button" class="prf-eye-btn" onclick="togglePw('pw_baru',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="prf-pw-bar" id="pwBar"></div>
                        <span class="prf-hint" id="pwStrengthLabel"></span>
                    </div>
                    <div class="prf-field">
                        <label>Konfirmasi Password <span class="req">*</span></label>
                        <div class="prf-input-wrap prf-pw-wrap">
                            <span class="prf-input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" name="password_konfirmasi" id="pw_konfirm"
                                class="form-control prf-input" required placeholder="Ulangi password baru">
                            <button type="button" class="prf-eye-btn" onclick="togglePw('pw_konfirm',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <span class="prf-hint" id="pwMatchLabel"></span>
                    </div>
                </div>

                <div class="prf-form-footer">
                    <button type="submit" class="prf-btn-pw">
                        <i class="fas fa-shield-halved"></i> Ubah Password
                    </button>
                </div>
            </form>
        </div>

        <!-- Log Aktivitas -->
        <div class="card">
            <div class="card-header prf-card-header">
                <div class="prf-card-header-icon" style="background:rgba(99,102,241,.1);color:#6366f1">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h3>Riwayat Aktivitas</h3>
                    <p>15 aktivitas terakhir pada akun Anda</p>
                </div>
            </div>
            <?php if (empty($logs)): ?>
            <div class="prf-empty">
                <div class="prf-empty-icon"><i class="fas fa-history"></i></div>
                <p>Belum ada aktivitas yang tercatat.</p>
            </div>
            <?php else: ?>
            <div class="prf-log-list">
                <?php
                $logConfig = [
                    'LOGIN'          => ['fa-right-to-bracket',  '#10b981', 'rgba(16,185,129,.12)',  'Login'],
                    'OTP_SENT'       => ['fa-envelope',           '#3b82f6', 'rgba(59,130,246,.12)',  'OTP Dikirim'],
                    'LOGOUT'         => ['fa-right-from-bracket', '#64748b', 'rgba(100,116,139,.12)', 'Logout'],
                    'ABSEN_MASUK'    => ['fa-clock',              '#0f4c81', 'rgba(15,76,129,.12)',   'Absen Masuk'],
                    'ABSEN_KELUAR'   => ['fa-clock-rotate-left',  '#0ea5e9', 'rgba(14,165,233,.12)',  'Absen Keluar'],
                    'UPDATE_PROFIL'  => ['fa-user-pen',           '#f59e0b', 'rgba(245,158,11,.12)',  'Update Profil'],
                    'GANTI_PASSWORD' => ['fa-lock',               '#ef4444', 'rgba(239,68,68,.12)',   'Ganti Password'],
                ];
                foreach ($logs as $i => $log):
                    $aksi   = strtoupper($log['aksi'] ?? '');
                    $cfg    = $logConfig[$aksi] ?? ['fa-circle-dot','#94a3b8','rgba(148,163,184,.12)',ucwords(strtolower(str_replace('_',' ',$aksi)))];
                    [$icon,$color,$bg,$label] = $cfg;
                    $isLast = ($i === count($logs)-1);
                ?>
                <div class="prf-log-item <?= $isLast?'prf-log-last':'' ?>">
                    <div class="prf-log-line-wrap">
                        <div class="prf-log-dot" style="background:<?= $bg ?>;border-color:<?= $color ?>">
                            <i class="fas <?= $icon ?>" style="color:<?= $color ?>"></i>
                        </div>
                        <?php if (!$isLast): ?><div class="prf-log-connector"></div><?php endif; ?>
                    </div>
                    <div class="prf-log-content">
                        <div class="prf-log-header">
                            <span class="prf-log-label" style="color:<?= $color ?>"><?= $label ?></span>
                            <span class="prf-log-time">
                                <i class="fas fa-clock"></i>
                                <?= date('d M Y', strtotime($log['created_at'])) ?> · <?= date('H:i', strtotime($log['created_at'])) ?>
                            </span>
                        </div>
                        <p class="prf-log-desc"><?= htmlspecialchars($log['keterangan']) ?></p>
                        <?php if (!empty($log['ip_address'])): ?>
                        <span class="prf-log-ip"><i class="fas fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.prf-grid    { display:grid; grid-template-columns:270px 1fr; gap:1.25rem; align-items:start; }
@media(max-width:960px){ .prf-grid { grid-template-columns:1fr; } }
.prf-sidebar, .prf-main { display:flex; flex-direction:column; gap:1rem; }

.prf-id-card { padding:0; overflow:hidden; }
.prf-banner  { height:68px; position:relative; overflow:hidden; background:linear-gradient(135deg,var(--primary) 0%,#0a2d55 100%); }
.prf-banner-dots { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.1) 1px,transparent 1px); background-size:18px 18px; }
.prf-avatar-wrap { position:relative; display:inline-block; margin:-30px 0 0 1.25rem; z-index:1; }
.prf-avatar  { width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--accent)); color:#fff; font-size:1.25rem; font-weight:800; display:flex; align-items:center; justify-content:center; border:3px solid var(--surface); box-shadow:0 4px 14px rgba(15,76,129,.25); }
.prf-status-dot { position:absolute; bottom:2px; right:1px; width:13px; height:13px; border-radius:50%; border:2.5px solid var(--surface); }
.dot-aktif    { background:#10b981; }
.dot-nonaktif { background:#ef4444; }
.prf-id-body  { padding:.55rem 1.25rem 1.2rem; }
.prf-nama { font-size:.93rem; font-weight:800; color:var(--text); margin:.4rem 0 .08rem; }
.prf-nik  { font-size:.68rem; color:var(--text-muted); margin:0 0 .5rem; font-family:'JetBrains Mono',monospace; display:flex; align-items:center; gap:.3rem; }
.prf-badges { display:flex; flex-wrap:wrap; gap:.28rem; margin-bottom:.75rem; }
.prf-badge  { display:inline-flex; align-items:center; gap:.28rem; font-size:.64rem; font-weight:700; padding:3px 9px; border-radius:20px; }
.prf-badge-role { background:rgba(15,76,129,.1); color:var(--primary); }
.badge-aktif    { background:rgba(16,185,129,.12); color:#10b981; }
.badge-nonaktif { background:rgba(239,68,68,.12);  color:#ef4444; }
.prf-info-list  { border-top:1px solid var(--border); padding-top:.65rem; display:flex; flex-direction:column; gap:.35rem; }
.prf-info-row   { display:flex; align-items:center; gap:.48rem; font-size:.75rem; color:var(--text); }
.prf-info-empty .prf-info-val { color:var(--text-muted); font-style:italic; }
.prf-info-icon  { width:21px; height:21px; border-radius:6px; background:rgba(15,76,129,.08); display:flex; align-items:center; justify-content:center; font-size:.6rem; color:var(--primary); flex-shrink:0; }
.prf-info-val   { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.prf-stats { display:flex; flex-direction:column; }
.prf-stat-item { display:flex; align-items:center; gap:.55rem; padding:.5rem .85rem; border-bottom:1px solid var(--border); transition:background .15s; }
.prf-stat-item:hover { background:var(--surface2); }
.prf-stat-icon  { width:30px; height:30px; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.7rem; }
.prf-stat-body  { flex:1; display:flex; flex-direction:column; gap:.06rem; }
.prf-stat-label { font-size:.68rem; color:var(--text-muted); }
.prf-stat-val   { font-size:.83rem; font-weight:800; font-family:'JetBrains Mono',monospace; }
.prf-stat-val small { font-size:.6rem; font-weight:500; font-family:inherit; color:var(--text-muted); }

.prf-card-header { display:flex; align-items:center; gap:.7rem; padding:.85rem 1.25rem; border-bottom:1px solid var(--border); }
.prf-card-header-icon { width:36px; height:36px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.82rem; }
.prf-card-header h3 { font-size:.85rem; font-weight:700; color:var(--text); margin:0 0 1px; }
.prf-card-header p  { font-size:.69rem; color:var(--text-muted); margin:0; }

.prf-form      { padding:1rem 1.25rem 1.25rem; display:flex; flex-direction:column; gap:.8rem; }
.prf-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:640px) { .prf-form-grid { grid-template-columns:1fr; } }
.prf-field { display:flex; flex-direction:column; gap:.26rem; }
.prf-field label { font-size:.78rem; font-weight:600; color:var(--text); }
.req { color:#ef4444; margin-left:2px; }
.prf-hint { font-size:.67rem; color:var(--text-muted); display:flex; align-items:center; gap:.22rem; }
.prf-hint-warn { color:#f59e0b !important; }
.prf-hint-ok   { color:#10b981 !important; }
.prf-input-wrap { position:relative; }
.prf-input-icon { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:.68rem; pointer-events:none; z-index:1; }
.prf-input { width:100% !important; padding:9px 12px 9px 33px !important; border:1.5px solid var(--border) !important; border-radius:10px !important; font-size:.8rem !important; font-family:inherit !important; color:var(--text) !important; background:var(--surface2) !important; outline:none !important; transition:all .2s !important; }
.prf-input:focus { border-color:var(--primary) !important; background:var(--surface) !important; box-shadow:0 0 0 3px rgba(15,76,129,.1) !important; }
.prf-input-disabled { opacity:.6 !important; cursor:not-allowed !important; }
.prf-select-wrap .prf-input { padding-right:30px !important; appearance:none !important; cursor:pointer !important; }
.prf-select-arrow { position:absolute; right:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:.6rem; pointer-events:none; }
.prf-pw-wrap .prf-input { padding-right:34px !important; }
.prf-eye-btn { position:absolute; right:9px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; padding:3px; font-size:.73rem; }
.prf-eye-btn:hover { color:var(--primary); }
.prf-pw-bar { height:3px; border-radius:2px; background:var(--border); margin-top:.26rem; transition:all .3s; }
.prf-pw-bar.weak   { background:#ef4444; width:33%; }
.prf-pw-bar.medium { background:#f59e0b; width:66%; }
.prf-pw-bar.strong { background:#10b981; width:100%; }
.prf-otp-notice { display:flex; align-items:center; gap:.6rem; background:rgba(59,130,246,.07); border:1px solid rgba(59,130,246,.2); border-radius:10px; padding:.65rem 1rem; font-size:.77rem; color:#3b82f6; }
.prf-form-footer { padding-top:.75rem; border-top:1px solid var(--border); }
.prf-btn-save { display:inline-flex; align-items:center; gap:.42rem; padding:.55rem 1.35rem; font-size:.81rem; font-weight:700; border-radius:10px; cursor:pointer; border:none; font-family:inherit; background:linear-gradient(135deg,var(--primary),#0a2d55); color:#fff; transition:all .2s; }
.prf-btn-save:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(15,76,129,.35); }
.prf-btn-pw  { display:inline-flex; align-items:center; gap:.42rem; padding:.55rem 1.35rem; font-size:.81rem; font-weight:700; border-radius:10px; cursor:pointer; border:none; font-family:inherit; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; transition:all .2s; }
.prf-btn-pw:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(245,158,11,.35); }

.prf-log-list { padding:.6rem 1.25rem 1rem; display:flex; flex-direction:column; }
.prf-log-item { display:flex; gap:.75rem; }
.prf-log-line-wrap { display:flex; flex-direction:column; align-items:center; flex-shrink:0; }
.prf-log-dot { width:28px; height:28px; border-radius:50%; border:2px solid; display:flex; align-items:center; justify-content:center; font-size:.62rem; flex-shrink:0; z-index:1; }
.prf-log-connector { flex:1; width:2px; background:var(--border); margin:3px 0; min-height:14px; }
.prf-log-content { flex:1; padding:.05rem 0 .9rem; }
.prf-log-last .prf-log-content { padding-bottom:0; }
.prf-log-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.2rem; margin-bottom:.15rem; }
.prf-log-label  { font-size:.75rem; font-weight:700; }
.prf-log-time   { font-size:.63rem; color:var(--text-muted); display:flex; align-items:center; gap:.2rem; font-family:'JetBrains Mono',monospace; }
.prf-log-desc   { font-size:.72rem; color:var(--text-muted); margin:0 0 .2rem; line-height:1.5; }
.prf-log-ip     { display:inline-flex; align-items:center; gap:.25rem; font-size:.61rem; color:var(--text-muted); background:var(--surface2); padding:1px 7px; border-radius:20px; }
.prf-empty { text-align:center; padding:2rem 1rem; }
.prf-empty-icon { width:46px; height:46px; border-radius:50%; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:var(--text-muted); margin:0 auto .7rem; }
.prf-empty p { font-size:.79rem; color:var(--text-muted); margin:0; }
</style>

<script>
function togglePw(id, btn) {
    const el = document.getElementById(id);
    const isText = el.type === 'text';
    el.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}
document.getElementById('pw_baru')?.addEventListener('input', function () {
    const v=this.value, bar=document.getElementById('pwBar'), lbl=document.getElementById('pwStrengthLabel');
    if (!v){bar.className='prf-pw-bar';lbl.textContent='';return;}
    const s=(v.length>=10?1:0)+(/[A-Z]/.test(v)?1:0)+(/[0-9]/.test(v)?1:0)+(/[^a-zA-Z0-9]/.test(v)?1:0);
    if(v.length<6){bar.className='prf-pw-bar weak';lbl.textContent='Terlalu pendek';lbl.style.color='#ef4444';}
    else if(s<=1) {bar.className='prf-pw-bar weak';lbl.textContent='Lemah';lbl.style.color='#ef4444';}
    else if(s<=2) {bar.className='prf-pw-bar medium';lbl.textContent='Sedang';lbl.style.color='#f59e0b';}
    else          {bar.className='prf-pw-bar strong';lbl.textContent='Kuat ✓';lbl.style.color='#10b981';}
    checkMatch();
});
document.getElementById('pw_konfirm')?.addEventListener('input', checkMatch);
function checkMatch(){
    const b=document.getElementById('pw_baru')?.value??'', k=document.getElementById('pw_konfirm')?.value??'', el=document.getElementById('pwMatchLabel');
    if(!el||!k){if(el)el.textContent='';return;}
    el.textContent=b===k?'✓ Password cocok':'✗ Belum cocok';
    el.style.color=b===k?'#10b981':'#ef4444';
}
document.getElementById('formPassword')?.addEventListener('submit',function(e){
    const b=document.getElementById('pw_baru')?.value??'', k=document.getElementById('pw_konfirm')?.value??'';
    if(b&&k&&b!==k){
        e.preventDefault();
        const el=document.getElementById('pwMatchLabel');
        el.textContent='✗ Password tidak cocok!';el.style.color='#ef4444';
        document.getElementById('pw_konfirm').focus();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>