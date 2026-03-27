<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Departemen';
$activePage = 'master_departemen';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $nama       = sanitize($_POST['nama'] ?? '');
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        if (!$nama) redirect(APP_URL.'/pages/master_departemen.php','Nama departemen wajib diisi!','danger');

        if ($id) {
            $db->prepare("UPDATE departemen SET nama=?, keterangan=? WHERE id=? AND perusahaan_id=?")
               ->execute([$nama, $keterangan, $id, $user['perusahaan_id']]);
            redirect(APP_URL.'/pages/master_departemen.php','Departemen berhasil diperbarui.','success');
        } else {
            $cek = $db->prepare("SELECT id FROM departemen WHERE nama=? AND perusahaan_id=?");
            $cek->execute([$nama, $user['perusahaan_id']]);
            if ($cek->fetch()) redirect(APP_URL.'/pages/master_departemen.php','Departemen sudah ada!','danger');

            $db->prepare("INSERT INTO departemen (perusahaan_id, nama, keterangan) VALUES (?,?,?)")
               ->execute([$user['perusahaan_id'], $nama, $keterangan]);
            redirect(APP_URL.'/pages/master_departemen.php','Departemen berhasil ditambahkan.','success');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $cek = $db->prepare("SELECT COUNT(*) FROM karyawan WHERE departemen_id=? AND perusahaan_id=?");
        $cek->execute([$id, $user['perusahaan_id']]);
        if ($cek->fetchColumn() > 0) {
            redirect(APP_URL.'/pages/master_departemen.php','Departemen masih digunakan oleh karyawan, tidak bisa dihapus!','danger');
        }
        $db->prepare("DELETE FROM departemen WHERE id=? AND perusahaan_id=?")->execute([$id, $user['perusahaan_id']]);
        redirect(APP_URL.'/pages/master_departemen.php','Departemen berhasil dihapus.','success');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM departemen WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'], $user['perusahaan_id']]);
    $edit = $stmt->fetch();
}

$stmt = $db->prepare("SELECT d.*, COUNT(k.id) as jumlah_karyawan
    FROM departemen d
    LEFT JOIN karyawan k ON k.departemen_id=d.id AND k.status='aktif'
    WHERE d.perusahaan_id=?
    GROUP BY d.id
    ORDER BY d.nama");
$stmt->execute([$user['perusahaan_id']]);
$departmens = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h2>Master Departemen</h2>
        <p>Kelola data departemen / divisi perusahaan</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="fas fa-plus"></i> Tambah Departemen
    </button>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h3><i class="fas fa-sitemap" style="color:var(--primary)"></i> Daftar Departemen</h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($departmens) ?> departemen</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Nama Departemen</th>
                    <th>Keterangan</th>
                    <th class="text-center" style="width:130px">Jumlah Karyawan</th>
                    <th style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departmens)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:30px">
                    <i class="fas fa-sitemap" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i>
                    Belum ada departemen. Klik "+ Tambah Departemen" untuk memulai.
                </td></tr>
                <?php else: foreach ($departmens as $i => $d): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($d['nama']) ?></div>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted)">
                        <?= $d['keterangan'] ? htmlspecialchars($d['keterangan']) : '<em>-</em>' ?>
                    </td>
                    <td class="text-center">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $d['jumlah_karyawan']>0?'rgba(0,201,167,.1)':'var(--surface2)' ?>;color:<?= $d['jumlah_karyawan']>0?'var(--accent)':'var(--text-muted)' ?>;padding:3px 12px;border-radius:20px;font-size:13px;font-weight:600">
                            <i class="fas fa-user" style="font-size:11px"></i> <?= $d['jumlah_karyawan'] ?> karyawan
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?edit=<?= $d['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php if ($d['jumlah_karyawan'] == 0): ?>
                            <form method="POST" onsubmit="return confirm('Hapus departemen \'<?= addslashes($d['nama']) ?>\'?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-danger btn-sm btn-icon" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-icon" style="opacity:.3;cursor:not-allowed;background:var(--surface2);border:1px solid var(--border)"
                                title="Tidak bisa dihapus — masih digunakan <?= $d['jumlah_karyawan'] ?> karyawan" disabled>
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

<!-- Modal -->
<div class="modal-overlay <?= $edit?'open':'' ?>" id="modalDept">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3><?= $edit ? 'Edit Departemen' : 'Tambah Departemen' ?></h3>
            <div class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Departemen <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control"
                        value="<?= htmlspecialchars($edit['nama'] ?? '') ?>"
                        placeholder="Contoh: HRD, Keuangan, Operasional, IT, dll"
                        required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="3"
                        placeholder="Deskripsi singkat departemen ini..."><?= htmlspecialchars($edit['keterangan'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Departemen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal()  { document.getElementById('modalDept').classList.add('open'); }
function closeModal() { document.getElementById('modalDept').classList.remove('open'); history.replaceState(null,'',window.location.pathname); }
<?php if ($edit): ?>window.addEventListener('load', () => openModal());<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>