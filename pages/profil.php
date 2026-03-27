<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db      = getDB();
$me      = currentUser();
$user_id = $me['id'];
$errors  = [];

// Suppress undefined key notices — beberapa field optional (jabatan, dept, telepon)
error_reporting(E_ERROR | E_PARSE);

// Fetch current user data
$stmt = $db->prepare("SELECT k.*, j.nama as jabatan_nama, d.nama as departemen_nama 
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    LEFT JOIN departemen d ON k.departemen_id = d.id
    WHERE k.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profil') {
        $nama    = sanitize($_POST['nama']    ?? '');
        $telepon = sanitize($_POST['telepon'] ?? '');
        $email   = sanitize($_POST['email']   ?? '');
        
        if (empty($nama))  $errors[] = 'Nama tidak boleh kosong.';
        if (empty($email)) $errors[] = 'Email tidak boleh kosong.';
        
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM karyawan WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) $errors[] = 'Email sudah digunakan akun lain.';
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE karyawan SET nama = ?, telepon = ?, email = ? WHERE id = ?");
            $stmt->execute([$nama, $telepon, $email, $user_id]);
            $_SESSION['nama']  = $nama;
            $_SESSION['email'] = $email;
            logActivity('UPDATE_PROFIL', 'Memperbarui data profil');
            redirect(APP_URL . '/pages/profil.php', 'Profil berhasil diperbarui.', 'success');
        }
    }
    
    if ($action === 'ganti_password') {
        $password_lama       = $_POST['password_lama']       ?? '';
        $password_baru       = $_POST['password_baru']       ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';
        
        if (!password_verify($password_lama, $user['password'])) {
            $errors[] = 'Password lama tidak sesuai.';
        }
        if (strlen($password_baru) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($password_baru !== $password_konfirmasi) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
        
        if (empty($errors)) {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE karyawan SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            logActivity('GANTI_PASSWORD', 'Mengganti password akun');
            redirect(APP_URL . '/pages/profil.php', 'Password berhasil diubah.', 'success');
        }
    }
}

// Statistik absensi bulan ini
$bulan = date('m');
$tahun = date('Y');
$stmt  = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_kehadiran IN ('hadir','terlambat') THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN status_kehadiran = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(COALESCE(terlambat_detik,0)) as total_terlambat_detik,
    SUM(COALESCE(durasi_kerja,0)) as total_durasi
    FROM absensi WHERE karyawan_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$user_id, $bulan, $tahun]);
$stats = $stmt->fetch();

// Log aktivitas terakhir
$stmt = $db->prepare("SELECT * FROM log_aktivitas WHERE karyawan_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();

$pageTitle  = 'Profil Saya';
$activePage = 'profil';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Profil Saya</h2>
        <p>Kelola informasi akun dan keamanan Anda</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <ul style="margin:0;padding-left:1.2rem">
        <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="profil-grid">
    <!-- Left: Profile Card -->
    <div class="profil-sidebar">
        <div class="card profil-card text-center">
            <div class="avatar-wrap">
                <div class="avatar-circle">
                    <?= strtoupper(substr($user['nama'], 0, 2)) ?>
                </div>
                <div class="avatar-badge <?= ($user['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>">
                    <?= ucfirst($user['status'] ?? 'aktif') ?>
                </div>
            </div>
            <h2 class="profil-nama"><?= htmlspecialchars($user['nama']) ?></h2>
            <p class="profil-nik"><i class="fas fa-id-card"></i> <?= htmlspecialchars($user['nik']) ?></p>
            <div class="profil-badges">
                <span class="badge badge-role"><?= $user['role'] === 'admin' ? '<i class="fas fa-shield-alt"></i> Admin' : '<i class="fas fa-user"></i> Karyawan' ?></span>
                <?php if (!empty($user['jabatan_nama'])): ?>
                <span class="badge badge-jabatan"><i class="fas fa-briefcase"></i> <?= htmlspecialchars($user['jabatan_nama'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <div class="profil-info-list">
                <div class="profil-info-item">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <?php if (!empty($user['telepon'])): ?>
                <div class="profil-info-item">
                    <i class="fas fa-phone"></i>
                    <span><?= htmlspecialchars($user['telepon'] ?? '') ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($user['departemen_nama'])): ?>
                <div class="profil-info-item">
                    <i class="fas fa-building"></i>
                    <span><?= htmlspecialchars($user['departemen_nama'] ?? '') ?></span>
                </div>
                <?php endif; ?>
                <div class="profil-info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Bergabung <?= tglIndonesia($user['tanggal_bergabung'] ?? null, 'short') ?></span>
                </div>
            </div>
        </div>

        <!-- Monthly Stats -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Statistik <?= tglIndonesia(null,'bulan').' '.date('Y') ?></h3>
            </div>
            <?php
            $telat    = (int)($stats['total_terlambat_detik'] ?? 0);
            $durMenit = (int)($stats['total_durasi'] ?? 0);
            // Format ringkas: 5j 18m 3d
            function fmtTelatRingkas($d) {
                if (!$d) return '-';
                $j=$d>=3600?floor($d/3600).'j ':'';
                $m=floor(($d%3600)/60); $s=$d%60;
                return trim($j.($m?$m.'m ':'').($s&&!$j?$s.'d':''));
            }
            $telatStr = fmtTelatRingkas($telat);
            $jamStr   = $durMenit >= 60 ? floor($durMenit/60).'j '.($durMenit%60).'m' : ($durMenit?$durMenit.'m':'-');
            ?>
            <div class="stat-rows">
                <div class="stat-row">
                    <span class="stat-row-icon" style="background:rgba(16,185,129,.12);color:var(--success)"><i class="fas fa-calendar-check"></i></span>
                    <span class="stat-row-label">Hadir</span>
                    <span class="stat-row-val" style="color:var(--success)"><?= $stats['hadir'] ?? 0 ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-row-icon" style="background:rgba(245,158,11,.12);color:var(--warning)"><i class="fas fa-clock"></i></span>
                    <span class="stat-row-label">Terlambat</span>
                    <span class="stat-row-val" style="color:var(--warning)"><?= $stats['terlambat'] ?? 0 ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-row-icon" style="background:rgba(15,76,129,.12);color:var(--primary)"><i class="fas fa-stopwatch"></i></span>
                    <span class="stat-row-label">Total Jam Kerja</span>
                    <span class="stat-row-val" style="color:var(--primary)"><?= $jamStr ?></span>
                </div>
                <div class="stat-row" style="border-bottom:none">
                    <span class="stat-row-icon" style="background:rgba(239,68,68,.12);color:var(--danger)"><i class="fas fa-triangle-exclamation"></i></span>
                    <span class="stat-row-label">Total Keterlambatan</span>
                    <span class="stat-row-val" style="color:var(--danger)"><?= $telatStr ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Edit Forms -->
    <div class="profil-main">
        <!-- Edit Profil -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Edit Informasi Profil</h3>
            </div>
            <form method="POST" class="profil-form" style="padding:1rem 1.25rem 1.25rem">
                <input type="hidden" name="action" value="update_profil">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>NIK</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['nik']) ?>" disabled>
                        <small class="form-hint">NIK tidak dapat diubah</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($user['telepon'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jabatan</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['jabatan_nama'] ?? '-') ?>" disabled>
                        <small class="form-hint">Diatur oleh admin</small>
                    </div>
                    <div class="form-group">
                        <label>Departemen</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['departemen_nama'] ?? '-') ?>" disabled>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <!-- Ganti Password -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Ganti Password</h3>
            </div>
            <form method="POST" class="profil-form" id="formPassword" style="padding:1rem 1.25rem 1.25rem">
                <input type="hidden" name="action" value="ganti_password">
                <div class="form-group">
                    <label>Password Saat Ini <span class="required">*</span></label>
                    <div class="input-pw-wrap">
                        <input type="password" name="password_lama" id="pw_lama" class="form-control" required>
                        <button type="button" class="btn-eye" onclick="togglePw('pw_lama',this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password Baru <span class="required">*</span></label>
                        <div class="input-pw-wrap">
                            <input type="password" name="password_baru" id="pw_baru" class="form-control" minlength="6" required>
                            <button type="button" class="btn-eye" onclick="togglePw('pw_baru',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="pw-strength" id="pwStrength"></div>
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password <span class="required">*</span></label>
                        <div class="input-pw-wrap">
                            <input type="password" name="password_konfirmasi" id="pw_konfirm" class="form-control" required>
                            <button type="button" class="btn-eye" onclick="togglePw('pw_konfirm',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="pwMatch" class="form-hint"></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Ubah Password</button>
                </div>
            </form>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Riwayat Aktivitas</h3>
            </div>
            <?php if (empty($logs)): ?>
            <div class="empty-state-sm"><i class="fas fa-history"></i><p>Belum ada aktivitas tercatat.</p></div>
            <?php else: ?>
            <div class="log-list" style="padding:0 1.25rem">
                <?php foreach ($logs as $log): ?>
                <div class="log-item">
                    <div class="log-icon">
                        <?php
                        $icons = [
                            'login' => 'fa-sign-in-alt text-success',
                            'logout' => 'fa-sign-out-alt text-secondary',
                            'absen_masuk' => 'fa-clock text-primary',
                            'absen_keluar' => 'fa-clock-rotate-left text-info',
                            'update_profil' => 'fa-user-edit text-warning',
                            'ganti_password' => 'fa-lock text-danger',
                        ];
                        $iconClass = $icons[$log['aksi']] ?? 'fa-circle text-secondary';
                        ?>
                        <i class="fas <?= $iconClass ?>"></i>
                    </div>
                    <div class="log-body">
                        <span class="log-desc"><?= htmlspecialchars($log['keterangan']) ?></span>
                        <span class="log-time"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></span>
                    </div>
                    <?php if ($log['ip_address']): ?>
                    <div class="log-ip"><i class="fas fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ── Layout ── */
.profil-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media(max-width:900px) { .profil-grid { grid-template-columns: 1fr; } }

/* ── Card profil kiri ── */
.profil-card { text-align: center; padding: 1.25rem 1rem 1rem; }
.avatar-wrap  { position: relative; display: inline-block; margin-bottom: .75rem; }
.avatar-circle {
    width: 72px; height: 72px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: #fff; font-size: 1.6rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; margin: 0 auto;
}
.avatar-badge {
    position: absolute; bottom: 0; right: -2px;
    font-size: .6rem; padding: 2px 6px; border-radius: 20px;
    font-weight: 700; border: 2px solid var(--surface); color: #fff;
}
.badge-aktif    { background: var(--success); }
.badge-nonaktif { background: var(--danger); }
.profil-nama  { font-size: 1rem; font-weight: 700; margin: .5rem 0 .15rem; }
.profil-nik   { font-size: .75rem; color: var(--text-muted); margin: 0 0 .5rem; font-family: 'JetBrains Mono', monospace; }
.profil-badges { display: flex; flex-wrap: wrap; gap: .3rem; justify-content: center; margin-bottom: .75rem; }
.badge-role    { background: rgba(15,76,129,.1); color: var(--primary); font-size: .68rem; padding: 2px 9px; border-radius: 20px; font-weight: 600; display: inline-block; }
.badge-jabatan { background: rgba(0,201,167,.1); color: var(--accent);  font-size: .68rem; padding: 2px 9px; border-radius: 20px; font-weight: 600; display: inline-block; }
.profil-info-list { text-align: left; border-top: 1px solid var(--border); padding-top: .75rem; display: flex; flex-direction: column; gap: .4rem; }
.profil-info-item { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: var(--text-muted); }
.profil-info-item i { width: 14px; color: var(--primary); flex-shrink: 0; font-size: .8rem; }

/* ── Statistik ── */
.stat-rows { display: flex; flex-direction: column; }
.stat-row  { display: flex; align-items: center; gap: .65rem; padding: .55rem .75rem; border-bottom: 1px solid var(--border); }
.stat-row:last-child { border-bottom: none; }
.stat-row-icon  { width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
.stat-row-label { flex: 1; font-size: .78rem; color: var(--text-muted); }
.stat-row-val   { font-size: .85rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; white-space: nowrap; }

/* ── Form kanan ── */
.profil-main { display: flex; flex-direction: column; gap: 1rem; }
.profil-form { display: flex; flex-direction: column; gap: .85rem; padding: 1rem 1.25rem 1.25rem; }
.form-row    { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media(max-width:680px) { .form-row { grid-template-columns: 1fr; } }
.form-group  { display: flex; flex-direction: column; gap: .3rem; }
.form-group label { font-size: .82rem; font-weight: 600; color: var(--text); }
.form-hint   { font-size: .72rem; color: var(--text-muted); }
.form-actions { padding-top: .75rem; border-top: 1px solid var(--border); }
.required    { color: var(--danger); }

/* ── Password ── */
.input-pw-wrap { position: relative; }
.input-pw-wrap .form-control { padding-right: 2.4rem; }
.btn-eye { position: absolute; right: .55rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: .15rem; line-height: 1; }
.pw-strength        { height: 3px; border-radius: 2px; margin-top: .3rem; transition: all .3s; background: var(--border); }
.pw-strength.weak   { background: var(--danger);  width: 33%; }
.pw-strength.medium { background: var(--warning); width: 66%; }
.pw-strength.strong { background: var(--success); width: 100%; }

/* ── Log aktivitas ── */
.log-list { display: flex; flex-direction: column; padding: .25rem 1.25rem; }
.log-item { display: flex; align-items: center; gap: .65rem; padding: .6rem 0; border-bottom: 1px solid var(--border); }
.log-item:last-child { border-bottom: none; }
.log-icon { width: 30px; height: 30px; border-radius: 8px; background: var(--surface2); display: flex; align-items: center; justify-content: center; font-size: .78rem; flex-shrink: 0; }
.log-body { flex: 1; min-width: 0; }
.log-desc { display: block; font-size: .8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.log-time { display: block; font-size: .68rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; margin-top: 1px; }
.log-ip   { font-size: .68rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

.empty-state-sm { text-align: center; padding: 1.5rem; color: var(--text-muted); }
.empty-state-sm i { font-size: 1.6rem; margin-bottom: .4rem; display: block; }
</style>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}

document.getElementById('pw_baru')?.addEventListener('input', function() {
    const val = this.value;
    const bar = document.getElementById('pwStrength');
    if (!val) { bar.className = 'pw-strength'; return; }
    if (val.length < 6) bar.className = 'pw-strength weak';
    else if (val.length < 10) bar.className = 'pw-strength medium';
    else bar.className = 'pw-strength strong';
    checkMatch();
});

document.getElementById('pw_konfirm')?.addEventListener('input', checkMatch);

function checkMatch() {
    const baru = document.getElementById('pw_baru').value;
    const konfirm = document.getElementById('pw_konfirm').value;
    const el = document.getElementById('pwMatch');
    if (!konfirm) { el.textContent = ''; return; }
    if (baru === konfirm) {
        el.textContent = '✓ Password cocok';
        el.style.color = 'var(--success)';
    } else {
        el.textContent = '✗ Password tidak cocok';
        el.style.color = 'var(--danger)';
    }
}

document.getElementById('formPassword')?.addEventListener('submit', function(e) {
    const baru = document.getElementById('pw_baru').value;
    const konfirm = document.getElementById('pw_konfirm').value;
    if (baru !== konfirm) {
        e.preventDefault();
        alert('Konfirmasi password tidak cocok!');
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>