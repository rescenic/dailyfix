<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Shift';
$activePage = 'shift';
$user       = currentUser();
$db         = getDB();

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = sanitize($_POST['nama'] ?? '');
        $jam_masuk  = $_POST['jam_masuk'] ?? '';
        $jam_keluar = $_POST['jam_keluar'] ?? '';
        $status     = $_POST['status'] ?? 'aktif';
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        // Konversi toleransi ke detik
        $tolJam   = (int)($_POST['tol_jam'] ?? 0);
        $tolMenit = (int)($_POST['tol_menit'] ?? 0);
        $tolDetik = (int)($_POST['tol_detik'] ?? 0);
        $totalDetik = ($tolJam * 3600) + ($tolMenit * 60) + $tolDetik;

        if (!$nama || !$jam_masuk || !$jam_keluar) {
            redirect(APP_URL . '/pages/shift.php', 'Semua field wajib diisi!', 'danger');
        }

        if ($id) {
            $stmt = $db->prepare("UPDATE shift SET nama=?, jam_masuk=?, jam_keluar=?, toleransi_terlambat_detik=?, keterangan=?, status=? WHERE id=? AND perusahaan_id=?");
            $stmt->execute([$nama, $jam_masuk, $jam_keluar, $totalDetik, $keterangan, $status, $id, $user['perusahaan_id']]);
            redirect(APP_URL . '/pages/shift.php', 'Shift berhasil diperbarui.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO shift (perusahaan_id, nama, jam_masuk, jam_keluar, toleransi_terlambat_detik, keterangan, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$user['perusahaan_id'], $nama, $jam_masuk, $jam_keluar, $totalDetik, $keterangan, $status]);
            redirect(APP_URL . '/pages/shift.php', 'Shift berhasil ditambahkan.', 'success');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM shift WHERE id=? AND perusahaan_id=?")->execute([$id, $user['perusahaan_id']]);
        redirect(APP_URL . '/pages/shift.php', 'Shift berhasil dihapus.', 'success');
    }
}

// Edit data
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM shift WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'], $user['perusahaan_id']]);
    $edit = $stmt->fetch();
}

// List shift
$shifts = $db->prepare("SELECT * FROM shift WHERE perusahaan_id=? ORDER BY jam_masuk");
$shifts->execute([$user['perusahaan_id']]);
$shifts = $shifts->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h2>Master Shift</h2>
        <p>Kelola shift kerja dan toleransi keterlambatan</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="fas fa-plus"></i> Tambah Shift
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Durasi</th>
                    <th>Toleransi Terlambat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:30px">Belum ada data shift</td></tr>
                <?php else: foreach ($shifts as $i => $s):
                    $det = (int)$s['toleransi_terlambat_detik'];
                    $jamTol   = floor($det / 3600);
                    $menitTol = floor(($det % 3600) / 60);
                    $detTol   = $det % 60;
                    $masuk  = strtotime('2000-01-01 ' . $s['jam_masuk']);
                    $keluar = strtotime('2000-01-01 ' . $s['jam_keluar']);
                    if ($keluar < $masuk) $keluar += 86400;
                    $durasi = round(($keluar - $masuk) / 3600, 1);
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                    <td style="font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--success)"><?= substr($s['jam_masuk'],0,5) ?></td>
                    <td style="font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--danger)"><?= substr($s['jam_keluar'],0,5) ?></td>
                    <td><?= $durasi ?> jam</td>
                    <td>
                        <?php if ($det > 0): ?>
                        <span style="font-size:13px">
                            <?php $parts=[];
                            if($jamTol) $parts[]=$jamTol.' jam';
                            if($menitTol) $parts[]=$menitTol.' menit';
                            if($detTol) $parts[]=$detTol.' detik';
                            echo implode(' ',$parts); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:13px">Tidak ada</span>
                        <?php endif; ?>
                    </td>
                    <td><?= badgeStatus($s['status']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?edit=<?= $s['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" onsubmit="return confirm('Hapus shift ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button class="btn btn-danger btn-sm btn-icon" title="Hapus"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal-overlay <?= $edit ? 'open' : '' ?>" id="modalShift">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $edit ? 'Edit Shift' : 'Tambah Shift' ?></h3>
            <div class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Shift <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($edit['nama'] ?? '') ?>" placeholder="contoh: Shift Pagi" required>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Jam Masuk <span class="req">*</span></label>
                        <input type="time" name="jam_masuk" class="form-control" value="<?= $edit['jam_masuk'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jam Keluar <span class="req">*</span></label>
                        <input type="time" name="jam_keluar" class="form-control" value="<?= $edit['jam_keluar'] ?? '' ?>" required>
                    </div>
                </div>

                <!-- Toleransi terlambat -->
                <div class="form-group">
                    <label class="form-label">Toleransi Keterlambatan</label>
                    <div style="padding:14px;background:var(--surface2);border-radius:8px;border:1.5px solid var(--border)">
                        <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px">Isi sesuai kebutuhan. Contoh: 0 jam, 15 menit, 0 detik = 15 menit toleransi</p>
                        <div class="form-row cols-3">
                            <?php
                            $det = (int)($edit['toleransi_terlambat_detik'] ?? 0);
                            $jamTol   = floor($det / 3600);
                            $menitTol = floor(($det % 3600) / 60);
                            $detTol   = $det % 60;
                            ?>
                            <div>
                                <label class="form-label" style="font-size:12px">Jam</label>
                                <input type="number" name="tol_jam" class="form-control" value="<?= $jamTol ?>" min="0" max="23" placeholder="0">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:12px">Menit</label>
                                <input type="number" name="tol_menit" class="form-control" value="<?= $menitTol ?>" min="0" max="59" placeholder="0">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:12px">Detik</label>
                                <input type="number" name="tol_detik" class="form-control" value="<?= $detTol ?>" min="0" max="59" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif" <?= ($edit['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($edit['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($edit['keterangan'] ?? '') ?></textarea>
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
function openModal() { document.getElementById('modalShift').classList.add('open'); }
function closeModal() {
    document.getElementById('modalShift').classList.remove('open');
    history.replaceState(null, '', window.location.pathname);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
