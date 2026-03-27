<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Jabatan';
$activePage = 'master_jabatan';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $nama        = sanitize($_POST['nama'] ?? '');
        $keterangan  = sanitize($_POST['keterangan'] ?? '');

        if (!$nama) redirect(APP_URL.'/pages/master_jabatan.php','Nama jabatan wajib diisi!','danger');

        if ($id) {
            $db->prepare("UPDATE jabatan SET nama=?, keterangan=? WHERE id=? AND perusahaan_id=?")
               ->execute([$nama, $keterangan, $id, $user['perusahaan_id']]);
            redirect(APP_URL.'/pages/master_jabatan.php','Jabatan berhasil diperbarui.','success');
        } else {
            // Cek duplikat
            $cek = $db->prepare("SELECT id FROM jabatan WHERE nama=? AND perusahaan_id=?");
            $cek->execute([$nama, $user['perusahaan_id']]);
            if ($cek->fetch()) redirect(APP_URL.'/pages/master_jabatan.php','Jabatan sudah ada!','danger');

            $db->prepare("INSERT INTO jabatan (perusahaan_id, nama, keterangan) VALUES (?,?,?)")
               ->execute([$user['perusahaan_id'], $nama, $keterangan]);
            redirect(APP_URL.'/pages/master_jabatan.php','Jabatan berhasil ditambahkan.','success');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek apakah masih dipakai karyawan
        $cek = $db->prepare("SELECT COUNT(*) FROM karyawan WHERE jabatan_id=? AND perusahaan_id=?");
        $cek->execute([$id, $user['perusahaan_id']]);
        if ($cek->fetchColumn() > 0) {
            redirect(APP_URL.'/pages/master_jabatan.php','Jabatan masih digunakan oleh karyawan, tidak bisa dihapus!','danger');
        }
        $db->prepare("DELETE FROM jabatan WHERE id=? AND perusahaan_id=?")->execute([$id, $user['perusahaan_id']]);
        redirect(APP_URL.'/pages/master_jabatan.php','Jabatan berhasil dihapus.','success');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM jabatan WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'], $user['perusahaan_id']]);
    $edit = $stmt->fetch();
}

// Ambil semua jabatan + jumlah karyawan
$stmt = $db->prepare("SELECT j.*, COUNT(k.id) as jumlah_karyawan 
    FROM jabatan j 
    LEFT JOIN karyawan k ON k.jabatan_id=j.id AND k.status='aktif'
    WHERE j.perusahaan_id=? 
    GROUP BY j.id 
    ORDER BY j.nama");
$stmt->execute([$user['perusahaan_id']]);
$jabatans = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h2>Master Jabatan</h2>
        <p>Kelola data jabatan karyawan perusahaan</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="fas fa-plus"></i> Tambah Jabatan
    </button>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h3><i class="fas fa-briefcase" style="color:var(--primary)"></i> Daftar Jabatan</h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($jabatans) ?> jabatan</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Nama Jabatan</th>
                    <th>Keterangan</th>
                    <th class="text-center" style="width:130px">Jumlah Karyawan</th>
                    <th style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jabatans)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:30px">
                    <i class="fas fa-briefcase" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i>
                    Belum ada jabatan. Klik "+ Tambah Jabatan" untuk memulai.
                </td></tr>
                <?php else: foreach ($jabatans as $i => $j): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($j['nama']) ?></div>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted)">
                        <?= $j['keterangan'] ? htmlspecialchars($j['keterangan']) : '<em>-</em>' ?>
                    </td>
                    <td class="text-center">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $j['jumlah_karyawan']>0?'rgba(15,76,129,.1)':'var(--surface2)' ?>;color:<?= $j['jumlah_karyawan']>0?'var(--primary)':'var(--text-muted)' ?>;padding:3px 12px;border-radius:20px;font-size:13px;font-weight:600">
                            <i class="fas fa-user" style="font-size:11px"></i> <?= $j['jumlah_karyawan'] ?> karyawan
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?edit=<?= $j['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php if ($j['jumlah_karyawan'] == 0): ?>
                            <form method="POST" onsubmit="return confirm('Hapus jabatan \'<?= addslashes($j['nama']) ?>\'?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                <button class="btn btn-danger btn-sm btn-icon" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-icon" style="opacity:.3;cursor:not-allowed;background:var(--surface2);border:1px solid var(--border)" 
                                title="Tidak bisa dihapus — masih digunakan <?= $j['jumlah_karyawan'] ?> karyawan" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
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
<div class="modal-overlay <?= $edit?'open':'' ?>" id="modalJabatan">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3><?= $edit ? 'Edit Jabatan' : 'Tambah Jabatan' ?></h3>
            <div class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Jabatan <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control"
                        value="<?= htmlspecialchars($edit['nama'] ?? '') ?>"
                        placeholder="Contoh: Staff HRD, Manager Operasional, dll"
                        required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="3"
                        placeholder="Deskripsi singkat jabatan ini..."><?= htmlspecialchars($edit['keterangan'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Jabatan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal()  { document.getElementById('modalJabatan').classList.add('open'); }
function closeModal() { document.getElementById('modalJabatan').classList.remove('open'); history.replaceState(null,'',window.location.pathname); }
<?php if ($edit): ?>window.addEventListener('load', () => openModal());<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>