<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Perusahaan';
$activePage = 'perusahaan';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = sanitize($_POST['nama'] ?? '');
        $alamat  = sanitize($_POST['alamat'] ?? '');
        $telepon = sanitize($_POST['telepon'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $npwp    = sanitize($_POST['npwp'] ?? '');

        if (!$nama) redirect(APP_URL.'/pages/perusahaan.php','Nama perusahaan wajib diisi!','danger');

        if ($id) {
            $db->prepare("UPDATE perusahaan SET nama=?,alamat=?,telepon=?,email=?,website=?,npwp=? WHERE id=?")
               ->execute([$nama,$alamat,$telepon,$email,$website,$npwp,$id]);
        } else {
            $db->prepare("INSERT INTO perusahaan (nama,alamat,telepon,email,website,npwp) VALUES (?,?,?,?,?,?)")
               ->execute([$nama,$alamat,$telepon,$email,$website,$npwp]);
        }
        redirect(APP_URL.'/pages/perusahaan.php','Data perusahaan berhasil disimpan.','success');
    }
}

$perusahaan = $db->prepare("SELECT * FROM perusahaan WHERE id=?");
$perusahaan->execute([$user['perusahaan_id']]);
$perusahaan = $perusahaan->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h2>Master Perusahaan</h2><p>Kelola data dan profil perusahaan</p></div>

<div class="grid-2" style="align-items:start">
    <div class="card">
        <div class="card-header"><h3>Data Perusahaan</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $perusahaan['id']??'' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($perusahaan['nama']??'') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control"><?= htmlspecialchars($perusahaan['alamat']??'') ?></textarea>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($perusahaan['telepon']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($perusahaan['email']??'') ?>">
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($perusahaan['website']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NPWP</label>
                        <input type="text" name="npwp" class="form-control" value="<?= htmlspecialchars($perusahaan['npwp']??'') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <div>
        <!-- Info Card -->
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Informasi Sistem</h3></div>
            <div class="card-body">
                <?php
                $totalKaryawan = $db->prepare("SELECT COUNT(*) FROM karyawan WHERE perusahaan_id=? AND status='aktif'");
                $totalKaryawan->execute([$user['perusahaan_id']]); $totalK = $totalKaryawan->fetchColumn();
                $totalLokasi = $db->prepare("SELECT COUNT(*) FROM lokasi WHERE perusahaan_id=?");
                $totalLokasi->execute([$user['perusahaan_id']]); $totalL = $totalLokasi->fetchColumn();
                $totalShift = $db->prepare("SELECT COUNT(*) FROM shift WHERE perusahaan_id=?");
                $totalShift->execute([$user['perusahaan_id']]); $totalS = $totalShift->fetchColumn();
                $totalAbsen = $db->prepare("SELECT COUNT(*) FROM absensi a JOIN karyawan k ON k.id=a.karyawan_id WHERE k.perusahaan_id=?");
                $totalAbsen->execute([$user['perusahaan_id']]); $totalA = $totalAbsen->fetchColumn();
                ?>
                <div style="display:grid;gap:12px">
                    <?php $items=[['Karyawan Aktif',$totalK,'fas fa-users','blue'],['Lokasi Terdaftar',$totalL,'fas fa-map-pin','teal'],['Shift Aktif',$totalS,'fas fa-clock','purple'],['Total Data Absensi',$totalA,'fas fa-fingerprint','green']];
                    foreach($items as [$label,$val,$icon,$color]): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--surface2);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="stat-icon <?= $color ?>" style="width:36px;height:36px;border-radius:8px;font-size:14px"><i class="<?= $icon ?>"></i></div>
                            <span style="font-size:13.5px"><?= $label ?></span>
                        </div>
                        <span style="font-size:18px;font-weight:800"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Jabatan & Departemen -->
        <div class="card">
            <div class="card-header">
                <h3>Jabatan & Departemen</h3>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <p style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Jabatan</p>
                        <?php $jabs=$db->prepare("SELECT * FROM jabatan WHERE perusahaan_id=? ORDER BY nama"); $jabs->execute([$user['perusahaan_id']]); $jabs=$jabs->fetchAll();
                        foreach($jabs as $j): ?><div style="padding:6px 0;font-size:13.5px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($j['nama']) ?></div><?php endforeach; ?>
                    </div>
                    <div>
                        <p style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Departemen</p>
                        <?php $depts=$db->prepare("SELECT * FROM departemen WHERE perusahaan_id=? ORDER BY nama"); $depts->execute([$user['perusahaan_id']]); $depts=$depts->fetchAll();
                        foreach($depts as $d): ?><div style="padding:6px 0;font-size:13.5px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($d['nama']) ?></div><?php endforeach; ?>
                    </div>
                </div>
                <div style="margin-top:12px;padding:10px;background:var(--surface2);border-radius:8px;font-size:12.5px;color:var(--text-muted)">
                    <i class="fas fa-info-circle"></i> Edit jabatan dan departemen langsung di database atau hubungi pengembang.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
