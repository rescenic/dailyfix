<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Data Karyawan';
$activePage = 'karyawan';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id            = (int)($_POST['id'] ?? 0);
        $nik           = sanitize($_POST['nik']    ?? '');
        $nama          = sanitize($_POST['nama']   ?? '');
        $email         = sanitize($_POST['email']  ?? '');
        $telepon       = sanitize($_POST['telepon'] ?? '');
        $jabatan_id    = (int)($_POST['jabatan_id']    ?? 0) ?: null;
        $dept_id       = (int)($_POST['departemen_id'] ?? 0) ?: null;
        $role          = $_POST['role']   ?? 'karyawan';
        $status_baru   = $_POST['status'] ?? 'aktif';
        $tgl_bergabung = $_POST['tanggal_bergabung'] ?: null;
        $password      = $_POST['password'] ?? '';

        // ── Ambil array lokasi_id yang dipilih ──
        $lokasi_ids = isset($_POST['lokasi_id']) ? array_map('intval', (array)$_POST['lokasi_id']) : [];
        $lokasi_ids = array_filter($lokasi_ids); // buang 0

        if (!$nik || !$nama || !$email) {
            redirect(APP_URL.'/pages/karyawan.php', 'Field wajib diisi!', 'danger');
        }

        if ($id) {
            // ── Cek status lama untuk deteksi aktivasi ──
            $stmtLama = $db->prepare("SELECT status, nama, email, perusahaan_id FROM karyawan WHERE id=? AND perusahaan_id=?");
            $stmtLama->execute([$id, $user['perusahaan_id']]);
            $dataLama = $stmtLama->fetch();
            $statusLama = $dataLama['status'] ?? '';

            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE karyawan SET nik=?,nama=?,email=?,telepon=?,jabatan_id=?,departemen_id=?,role=?,status=?,tanggal_bergabung=?,password=? WHERE id=? AND perusahaan_id=?")
                   ->execute([$nik,$nama,$email,$telepon,$jabatan_id,$dept_id,$role,$status_baru,$tgl_bergabung,$hash,$id,$user['perusahaan_id']]);
            } else {
                $db->prepare("UPDATE karyawan SET nik=?,nama=?,email=?,telepon=?,jabatan_id=?,departemen_id=?,role=?,status=?,tanggal_bergabung=? WHERE id=? AND perusahaan_id=?")
                   ->execute([$nik,$nama,$email,$telepon,$jabatan_id,$dept_id,$role,$status_baru,$tgl_bergabung,$id,$user['perusahaan_id']]);
            }

            // ── Update relasi lokasi (hapus lama, insert baru) ──
            $db->prepare("DELETE FROM karyawan_lokasi WHERE karyawan_id=?")->execute([$id]);
            if (!empty($lokasi_ids)) {
                $stmtLok = $db->prepare("INSERT IGNORE INTO karyawan_lokasi (karyawan_id, lokasi_id) VALUES (?,?)");
                foreach ($lokasi_ids as $lid) {
                    $stmtLok->execute([$id, $lid]);
                }
            }

            // ── Kirim email jika baru diaktifkan (nonaktif → aktif) ──
            if ($statusLama === 'nonaktif' && $status_baru === 'aktif') {
                $emailAktif = '
                <div style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;max-width:520px;margin:0 auto">
                    <div style="background:linear-gradient(135deg,#0f4c81,#0a2d55);padding:28px;border-radius:12px 12px 0 0;text-align:center">
                        <div style="display:inline-block;background:linear-gradient(135deg,#00c9a7,#0ea5e9);width:52px;height:52px;border-radius:12px;line-height:52px;font-size:24px;font-weight:900;color:#fff;text-align:center">D</div>
                        <h1 style="color:#fff;font-size:20px;margin:10px 0 0;font-weight:800">DailyFix</h1>
                        <p style="color:rgba(255,255,255,.65);font-size:13px;margin:4px 0 0">Sistem Absensi Digital</p>
                    </div>
                    <div style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none">
                        <div style="text-align:center;margin-bottom:20px">
                            <div style="width:60px;height:60px;background:linear-gradient(135deg,#10b981,#00c9a7);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:26px;color:#fff;box-shadow:0 6px 20px rgba(16,185,129,.35)">✓</div>
                        </div>
                        <h2 style="color:#0f172a;font-size:18px;margin:0 0 8px;text-align:center">Akun Anda Telah Diaktifkan! 🎉</h2>
                        <p style="color:#64748b;font-size:14px;margin:0 0 24px;text-align:center">Halo <strong style="color:#0f172a">' . htmlspecialchars($nama) . '</strong>, selamat bergabung di DailyFix!</p>

                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px 20px;margin-bottom:20px">
                            <div style="font-size:12px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">✅ Status Akun: Aktif</div>
                            <p style="font-size:13px;color:#166534;margin:0;line-height:1.65">Akun Anda sudah aktif dan siap digunakan. Anda dapat login ke DailyFix sekarang menggunakan email ini.</p>
                        </div>

                        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin-bottom:24px">
                            <div style="font-size:12px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">🔐 Cara Login</div>
                            <ol style="font-size:13px;color:#1e40af;margin:0;padding-left:1.2rem;line-height:2">
                                <li>Buka halaman login DailyFix</li>
                                <li>Masukkan email: <strong>' . htmlspecialchars($email) . '</strong></li>
                                <li>Klik <strong>Kirim Kode OTP</strong></li>
                                <li>Masukkan 6 digit kode yang dikirim ke email ini</li>
                                <li>Selesai — Anda berhasil masuk! ✅</li>
                            </ol>
                        </div>
                    </div>
                    <div style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;text-align:center;font-size:11.5px;color:#94a3b8">
                        © ' . date('Y') . ' DailyFix — Sistem Absensi Digital
                    </div>
                </div>';

                sendSmtpEmail($db, $user['perusahaan_id'], $email, '✅ Akun DailyFix Anda Telah Diaktifkan!', $emailAktif);
            }

            redirect(APP_URL.'/pages/karyawan.php', 'Data karyawan berhasil diperbarui.', 'success');

        } else {
            // Tambah karyawan baru dari admin (langsung aktif)
            if (!$password) redirect(APP_URL.'/pages/karyawan.php','Password wajib diisi untuk karyawan baru!','danger');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO karyawan (perusahaan_id,nik,nama,email,password,telepon,jabatan_id,departemen_id,role,status,tanggal_bergabung) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$user['perusahaan_id'],$nik,$nama,$email,$hash,$telepon,$jabatan_id,$dept_id,$role,$status_baru,$tgl_bergabung]);
            $newId = (int)$db->lastInsertId();

            // ── Insert relasi lokasi ──
            if ($newId && !empty($lokasi_ids)) {
                $stmtLok = $db->prepare("INSERT IGNORE INTO karyawan_lokasi (karyawan_id, lokasi_id) VALUES (?,?)");
                foreach ($lokasi_ids as $lid) {
                    $stmtLok->execute([$newId, $lid]);
                }
            }

            redirect(APP_URL.'/pages/karyawan.php','Karyawan berhasil ditambahkan.','success');
        }

    } elseif ($action === 'delete') {
        $delId = (int)$_POST['id'];
        // Hapus relasi lokasi dulu
        $db->prepare("DELETE FROM karyawan_lokasi WHERE karyawan_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM karyawan WHERE id=? AND perusahaan_id=? AND id != ?")
           ->execute([$delId, $user['perusahaan_id'], $user['id']]);
        redirect(APP_URL.'/pages/karyawan.php','Karyawan berhasil dihapus.','success');
    }
}

$edit = null;
$editLokasis = []; // lokasi_id yang sudah dipilih untuk karyawan yang diedit
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM karyawan WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'], $user['perusahaan_id']]);
    $edit = $stmt->fetch();

    if ($edit) {
        // Ambil lokasi yang sudah terpilih
        $stmtEl = $db->prepare("SELECT lokasi_id FROM karyawan_lokasi WHERE karyawan_id=?");
        $stmtEl->execute([$edit['id']]);
        $editLokasis = array_column($stmtEl->fetchAll(), 'lokasi_id');
    }
}

$search = sanitize($_GET['q'] ?? '');

// Query utama — gabungkan nama-nama lokasi dengan GROUP_CONCAT
$sql = "SELECT k.*, j.nama as jabatan_nama, d.nama as dept_nama,
               GROUP_CONCAT(l.nama ORDER BY l.nama SEPARATOR ', ') as lokasi_nama
        FROM karyawan k
        LEFT JOIN jabatan j ON j.id=k.jabatan_id
        LEFT JOIN departemen d ON d.id=k.departemen_id
        LEFT JOIN karyawan_lokasi kl ON kl.karyawan_id=k.id
        LEFT JOIN lokasi l ON l.id=kl.lokasi_id
        WHERE k.perusahaan_id=?";
$params = [$user['perusahaan_id']];
if ($search) {
    $sql .= " AND (k.nama LIKE ? OR k.nik LIKE ? OR k.email LIKE ?)";
    $s = '%'.$search.'%';
    $params = array_merge($params, [$s,$s,$s]);
}
$sql .= " GROUP BY k.id ORDER BY k.status ASC, k.nama ASC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$karyawans = $stmt->fetchAll();

// Hitung pending (nonaktif dari register)
$pendingCount = 0;
foreach ($karyawans as $k) { if ($k['status'] === 'nonaktif') $pendingCount++; }

$jabatans   = $db->prepare("SELECT * FROM jabatan WHERE perusahaan_id=? ORDER BY nama"); $jabatans->execute([$user['perusahaan_id']]); $jabatans=$jabatans->fetchAll();
$departmens = $db->prepare("SELECT * FROM departemen WHERE perusahaan_id=? ORDER BY nama"); $departmens->execute([$user['perusahaan_id']]); $departmens=$departmens->fetchAll();
$lokasis    = $db->prepare("SELECT * FROM lokasi WHERE perusahaan_id=? AND status='aktif' ORDER BY nama"); $lokasis->execute([$user['perusahaan_id']]); $lokasis=$lokasis->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
.badge-pending {
    display:inline-flex;align-items:center;gap:5px;
    background:#fef3c7;color:#92400e;
    padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;
}
.pending-banner {
    display:flex;align-items:center;gap:12px;
    background:#fffbeb;border:1px solid #fcd34d;border-left:4px solid #f59e0b;
    border-radius:10px;padding:12px 16px;margin-bottom:16px;
    font-size:13.5px;color:#92400e;
}
.pending-banner strong { font-weight:700; }
.pending-banner .btn-filter {
    margin-left:auto;background:#f59e0b;color:#fff;border:none;
    padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;
    cursor:pointer;font-family:inherit;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;
}

/* ── Multi-Lokasi Checkbox Pills ── */
.lokasi-picker {
    border:1px solid var(--border-color, #e2e8f0);
    border-radius:10px;
    padding:10px 12px;
    max-height:160px;
    overflow-y:auto;
    background:#fff;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    scrollbar-width:thin;
}
.lokasi-picker:focus-within {
    border-color:#0f4c81;
    box-shadow:0 0 0 3px rgba(15,76,129,.12);
}
.lokasi-chip {
    display:inline-flex;
    align-items:center;
    gap:6px;
    cursor:pointer;
    user-select:none;
}
.lokasi-chip input[type=checkbox] { display:none; }
.lokasi-chip-label {
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:5px 12px;
    border-radius:20px;
    font-size:12.5px;
    font-weight:600;
    background:#f1f5f9;
    color:#475569;
    border:1.5px solid #e2e8f0;
    transition:all .15s;
    cursor:pointer;
}
.lokasi-chip input[type=checkbox]:checked + .lokasi-chip-label {
    background:#eff6ff;
    color:#1d4ed8;
    border-color:#93c5fd;
}
.lokasi-chip-label .chip-dot {
    width:7px;height:7px;border-radius:50%;
    background:#cbd5e1;flex-shrink:0;
    transition:background .15s;
}
.lokasi-chip input[type=checkbox]:checked + .lokasi-chip-label .chip-dot {
    background:#3b82f6;
}
.lokasi-empty {
    font-size:12.5px;color:#94a3b8;padding:4px;
}
.lokasi-selected-count {
    font-size:11.5px;color:#64748b;margin-top:4px;font-weight:600;
}
.lokasi-selected-count span { color:#0f4c81;font-weight:700; }

/* Badge lokasi di tabel */
.badge-lokasi {
    display:inline-flex;align-items:center;gap:4px;
    background:#eff6ff;color:#1d4ed8;
    padding:2px 8px;border-radius:20px;font-size:11.5px;font-weight:600;
    white-space:nowrap;
}
.lokasi-list-cell {
    display:flex;flex-wrap:wrap;gap:4px;
}
</style>



<!-- Banner pending aktivasi -->
<?php if ($pendingCount > 0): ?>
<div class="pending-banner">
    <i class="fas fa-clock" style="font-size:18px;flex-shrink:0"></i>
    <div>
        <strong><?= $pendingCount ?> karyawan</strong> menunggu aktivasi akun.
        Aktifkan dengan klik tombol edit dan ubah status menjadi <strong>Aktif</strong> — karyawan akan otomatis menerima email notifikasi.
    </div>
    <a href="?q=&status=nonaktif" class="btn-filter">
        <i class="fas fa-eye"></i> Lihat Semua
    </a>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <div style="padding:12px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <form method="GET" style="flex:1;min-width:200px">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Cari nama, NIK, email..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
        <div style="font-size:13px;color:var(--text-muted)"><?= count($karyawans) ?> karyawan ditemukan</div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>NIK</th><th>Nama Karyawan</th>
                    <th class="hide-mobile">Jabatan / Departemen</th>
                    <th class="hide-mobile">Lokasi</th>
                    <th class="hide-mobile">Role</th>
                    <th>Status</th>
                    <th class="hide-mobile">Bergabung</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($karyawans)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:30px">Tidak ada data karyawan</td></tr>
                <?php else: foreach($karyawans as $k): ?>
                <tr <?= $k['status']==='nonaktif' ? 'style="background:#fffbeb"' : '' ?>>
                    <td style="font-family:'JetBrains Mono',monospace;font-size:12px"><?= htmlspecialchars($k['nik']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:<?= $k['status']==='nonaktif' ? '#e2e8f0' : 'linear-gradient(135deg,#0f4c81,#00c9a7)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $k['status']==='nonaktif' ? '#94a3b8' : '#fff' ?>;font-weight:700;font-size:13px;flex-shrink:0">
                                <?= strtoupper(substr($k['nama'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600"><?= htmlspecialchars($k['nama']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($k['email']) ?></div>
                                <?php if ($k['status'] === 'nonaktif'): ?>
                                <span class="badge-pending"><i class="fas fa-clock" style="font-size:9px"></i> Menunggu Aktivasi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="hide-mobile">
                        <div style="font-size:13px"><?= htmlspecialchars($k['jabatan_nama']??'-') ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($k['dept_nama']??'-') ?></div>
                    </td>
                    <td class="hide-mobile">
                        <?php if (!empty($k['lokasi_nama'])): ?>
                            <div class="lokasi-list-cell">
                                <?php foreach(explode(', ', $k['lokasi_nama']) as $ln): ?>
                                <span class="badge-lokasi"><i class="fas fa-map-marker-alt" style="font-size:9px"></i><?= htmlspecialchars(trim($ln)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:12.5px">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile"><?= badgeStatus($k['role']) ?></td>
                    <td><?= badgeStatus($k['status']) ?></td>
                    <td class="hide-mobile" style="font-size:12.5px"><?= $k['tanggal_bergabung'] ? date('d/m/Y',strtotime($k['tanggal_bergabung'])) : '-' ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <?php if ($k['status'] === 'nonaktif'): ?>
                            <!-- Tombol cepat aktifkan -->
                            <form method="POST" onsubmit="return confirm('Aktifkan akun <?= htmlspecialchars(addslashes($k['nama'])) ?>? Email notifikasi akan dikirim.')">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <input type="hidden" name="nik" value="<?= htmlspecialchars($k['nik']) ?>">
                                <input type="hidden" name="nama" value="<?= htmlspecialchars($k['nama']) ?>">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($k['email']) ?>">
                                <input type="hidden" name="telepon" value="<?= htmlspecialchars($k['telepon'] ?? '') ?>">
                                <input type="hidden" name="jabatan_id" value="<?= $k['jabatan_id'] ?? '' ?>">
                                <input type="hidden" name="departemen_id" value="<?= $k['departemen_id'] ?? '' ?>">
                                <input type="hidden" name="role" value="<?= $k['role'] ?>">
                                <input type="hidden" name="status" value="aktif">
                                <input type="hidden" name="tanggal_bergabung" value="<?= $k['tanggal_bergabung'] ?? '' ?>">
                                <button type="submit" class="btn btn-sm" style="background:#10b981;color:#fff;border:none;display:flex;align-items:center;gap:4px;font-size:12px;padding:6px 10px;border-radius:8px;cursor:pointer">
                                    <i class="fas fa-check"></i> Aktifkan
                                </button>
                            </form>
                            <?php endif; ?>
                            <a href="?edit=<?= $k['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if ($k['id'] != $user['id']): ?>
                            <form method="POST" onsubmit="return confirm('Hapus karyawan ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal-overlay <?= $edit?'open':'' ?>" id="modalKaryawan">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><?= $edit?'Edit Karyawan':'Tambah Karyawan' ?></h3>
            <div class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">

                <?php if ($edit && $edit['status'] === 'nonaktif'): ?>
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-left:4px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#92400e">
                    <i class="fas fa-clock"></i> <strong>Akun ini menunggu aktivasi.</strong>
                    Ubah status ke <strong>Aktif</strong> untuk mengaktifkan — email notifikasi otomatis dikirim.
                </div>
                <?php endif; ?>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">NIK <span class="req">*</span></label>
                        <input type="text" name="nik" class="form-control" value="<?= htmlspecialchars($edit['nik']??'') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($edit['nama']??'') ?>" required>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Email <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit['email']??'') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($edit['telepon']??'') ?>">
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Password <?= !$edit?'<span class="req">*</span>':'(kosongkan jika tidak diubah)' ?></label>
                        <input type="password" name="password" class="form-control" <?= !$edit?'required':'' ?> placeholder="<?= $edit?'••••••• (biarkan kosong)':'Min. 6 karakter' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Bergabung</label>
                        <input type="date" name="tanggal_bergabung" class="form-control" value="<?= $edit['tanggal_bergabung']??'' ?>">
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Jabatan</label>
                        <select name="jabatan_id" class="form-select">
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach($jabatans as $j): ?>
                            <option value="<?= $j['id'] ?>" <?= ($edit['jabatan_id']??'')==$j['id']?'selected':'' ?>><?= htmlspecialchars($j['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Departemen</label>
                        <select name="departemen_id" class="form-select">
                            <option value="">-- Pilih Departemen --</option>
                            <?php foreach($departmens as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit['departemen_id']??'')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ── MULTI-LOKASI PICKER ── -->
                <div class="form-group">
                    <label class="form-label">
                        Lokasi yang Diizinkan
                        <span style="font-size:11px;font-weight:400;color:#64748b;margin-left:4px">(bisa pilih lebih dari satu)</span>
                    </label>
                    <?php if (empty($lokasis)): ?>
                        <div class="lokasi-picker"><span class="lokasi-empty"><i class="fas fa-map-marker-alt"></i> Belum ada lokasi aktif — tambahkan di menu Lokasi terlebih dahulu.</span></div>
                    <?php else: ?>
                        <div class="lokasi-picker" id="lokasiPicker">
                            <?php foreach($lokasis as $l): ?>
                            <?php $checked = in_array($l['id'], $editLokasis); ?>
                            <label class="lokasi-chip">
                                <input type="checkbox" name="lokasi_id[]" value="<?= $l['id'] ?>" <?= $checked ? 'checked' : '' ?> onchange="updateLokasiCount()">
                                <span class="lokasi-chip-label">
                                    <span class="chip-dot"></span>
                                    <?= htmlspecialchars($l['nama']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="lokasi-selected-count" id="lokasiCount">
                            <span id="lokasiCountNum"><?= count($editLokasis) ?></span> lokasi dipilih
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="karyawan" <?= ($edit['role']??'karyawan')==='karyawan'?'selected':'' ?>>Karyawan</option>
                            <option value="manager"  <?= ($edit['role']??'')==='manager'?'selected':'' ?>>Manager</option>
                            <option value="admin"    <?= ($edit['role']??'')==='admin'?'selected':'' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" id="statusSelect">
                            <option value="aktif"    <?= ($edit['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($edit['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                            <option value="cuti"     <?= ($edit['status']??'')==='cuti'?'selected':'' ?>>Cuti</option>
                        </select>
                        <?php if ($edit && $edit['status'] === 'nonaktif'): ?>
                        <div style="font-size:11.5px;color:#10b981;margin-top:4px;font-weight:600">
                            <i class="fas fa-info-circle"></i> Ubah ke Aktif → email otomatis terkirim ke karyawan
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('modalKaryawan').classList.add('open'); }
function closeModal() { document.getElementById('modalKaryawan').classList.remove('open'); history.replaceState(null,'',window.location.pathname); }
<?php if($edit): ?>window.addEventListener('load',()=>openModal());<?php endif; ?>

function updateLokasiCount() {
    const checked = document.querySelectorAll('#lokasiPicker input[type=checkbox]:checked').length;
    const el = document.getElementById('lokasiCountNum');
    if (el) el.textContent = checked;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>