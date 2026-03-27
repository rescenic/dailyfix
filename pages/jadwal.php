<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Jadwal';
$activePage = 'jadwal';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $nama           = sanitize($_POST['nama'] ?? '');
        $shift_id       = (int)($_POST['shift_id'] ?? 0);
        $lokasi_id      = (int)($_POST['lokasi_id'] ?? 0);
        $berlaku_dari   = $_POST['berlaku_dari'] ?? '';
        $berlaku_sampai = $_POST['berlaku_sampai'] ?: null;
        $hari_kerja     = $_POST['hari_kerja'] ?? [];
        $status         = $_POST['status'] ?? 'aktif';
        $keterangan     = sanitize($_POST['keterangan'] ?? '');
        $hariJson       = json_encode(array_map('intval', $hari_kerja));

        if (!$nama || !$shift_id || !$lokasi_id || !$berlaku_dari)
            redirect(APP_URL.'/pages/jadwal.php','Field wajib diisi!','danger');

        if ($id) {
            $db->prepare("UPDATE jadwal SET nama=?,shift_id=?,lokasi_id=?,berlaku_dari=?,berlaku_sampai=?,hari_kerja=?,status=?,keterangan=? WHERE id=? AND perusahaan_id=?")
               ->execute([$nama,$shift_id,$lokasi_id,$berlaku_dari,$berlaku_sampai,$hariJson,$status,$keterangan,$id,$user['perusahaan_id']]);
            redirect(APP_URL.'/pages/jadwal.php','Jadwal berhasil diperbarui.','success');
        } else {
            $db->prepare("INSERT INTO jadwal (perusahaan_id,nama,shift_id,lokasi_id,berlaku_dari,berlaku_sampai,hari_kerja,status,keterangan) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$user['perusahaan_id'],$nama,$shift_id,$lokasi_id,$berlaku_dari,$berlaku_sampai,$hariJson,$status,$keterangan]);
            redirect(APP_URL.'/pages/jadwal.php','Jadwal berhasil ditambahkan.','success');
        }

    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM jadwal WHERE id=? AND perusahaan_id=?")->execute([(int)$_POST['id'],$user['perusahaan_id']]);
        redirect(APP_URL.'/pages/jadwal.php','Jadwal berhasil dihapus.','success');

    } elseif ($action === 'assign') {
        $karyawan_id    = (int)($_POST['karyawan_id'] ?? 0);
        $jadwal_ids     = $_POST['jadwal_ids'] ?? [];
        $berlaku        = $_POST['berlaku_dari_assign'] ?? date('Y-m-d');
        $berlakuSampai  = $_POST['berlaku_sampai_assign'] ?: null;

        if (!$karyawan_id)
            redirect(APP_URL.'/pages/jadwal.php','Karyawan tidak valid!','danger');

        $cekK = $db->prepare("SELECT id FROM karyawan WHERE id=? AND perusahaan_id=?");
        $cekK->execute([$karyawan_id, $user['perusahaan_id']]);
        if (!$cekK->fetch())
            redirect(APP_URL.'/pages/jadwal.php','Karyawan tidak valid!','danger');

        // Hapus semua jadwal aktif lama karyawan ini
        $db->prepare("DELETE FROM jadwal_karyawan WHERE karyawan_id=? AND (berlaku_sampai IS NULL OR berlaku_sampai >= CURDATE())")
           ->execute([$karyawan_id]);

        $inserted = 0;
        foreach ($jadwal_ids as $jid) {
            $jid = (int)$jid;
            if (!$jid) continue;
            $cekJ = $db->prepare("SELECT id FROM jadwal WHERE id=? AND perusahaan_id=?");
            $cekJ->execute([$jid, $user['perusahaan_id']]);
            if (!$cekJ->fetch()) continue;
            $db->prepare("INSERT INTO jadwal_karyawan (karyawan_id,jadwal_id,berlaku_dari,berlaku_sampai) VALUES (?,?,?,?)")
               ->execute([$karyawan_id, $jid, $berlaku, $berlakuSampai]);
            $inserted++;
        }
        redirect(APP_URL.'/pages/jadwal.php', $inserted > 0 ? "Jadwal berhasil disimpan ({$inserted} jadwal)." : 'Semua jadwal karyawan dihapus.', 'success');

    } elseif ($action === 'hapus_jadwal_karyawan') {
        $jk_id = (int)($_POST['jk_id'] ?? 0);
        $cek   = $db->prepare("SELECT jk.id FROM jadwal_karyawan jk JOIN karyawan k ON k.id=jk.karyawan_id WHERE jk.id=? AND k.perusahaan_id=?");
        $cek->execute([$jk_id, $user['perusahaan_id']]);
        if ($cek->fetch()) {
            $db->prepare("DELETE FROM jadwal_karyawan WHERE id=?")->execute([$jk_id]);
            redirect(APP_URL.'/pages/jadwal.php','Jadwal berhasil dihapus dari karyawan.','success');
        }
        redirect(APP_URL.'/pages/jadwal.php','Data tidak ditemukan.','danger');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM jadwal WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'],$user['perusahaan_id']]);
    $edit = $stmt->fetch();
}

$shifts  = $db->prepare("SELECT * FROM shift WHERE perusahaan_id=? AND status='aktif' ORDER BY jam_masuk");
$shifts->execute([$user['perusahaan_id']]); $shifts = $shifts->fetchAll();

$lokasis = $db->prepare("SELECT * FROM lokasi WHERE perusahaan_id=? AND status='aktif' ORDER BY nama");
$lokasis->execute([$user['perusahaan_id']]); $lokasis = $lokasis->fetchAll();

$jadwals = $db->prepare("SELECT jd.*, s.nama as shift_nama, s.jam_masuk, s.jam_keluar, l.nama as lokasi_nama FROM jadwal jd JOIN shift s ON s.id=jd.shift_id JOIN lokasi l ON l.id=jd.lokasi_id WHERE jd.perusahaan_id=? ORDER BY jd.nama");
$jadwals->execute([$user['perusahaan_id']]); $jadwals = $jadwals->fetchAll();

// Karyawan dengan search/filter
$search = sanitize($_GET['q'] ?? '');
$sqlK = "SELECT * FROM karyawan WHERE perusahaan_id=? AND role='karyawan' AND status='aktif'";
$paramsK = [$user['perusahaan_id']];
if ($search) { $sqlK .= " AND (nama LIKE ? OR nik LIKE ?)"; $paramsK[] = "%$search%"; $paramsK[] = "%$search%"; }
$sqlK .= " ORDER BY nama";
$stmtK = $db->prepare($sqlK);
$stmtK->execute($paramsK);
$karyawans = $stmtK->fetchAll();

// Jadwal aktif per karyawan
$stmtJK = $db->prepare("SELECT jk.id as jk_id, jk.karyawan_id, jk.jadwal_id, jk.berlaku_dari, j.nama as jadwal_nama, j.hari_kerja, s.jam_masuk, s.jam_keluar
    FROM jadwal_karyawan jk JOIN jadwal j ON j.id=jk.jadwal_id JOIN shift s ON s.id=j.shift_id
    WHERE jk.karyawan_id IN (SELECT id FROM karyawan WHERE perusahaan_id=?)
    AND (jk.berlaku_sampai IS NULL OR jk.berlaku_sampai >= CURDATE())
    ORDER BY jk.karyawan_id, j.nama");
$stmtJK->execute([$user['perusahaan_id']]);
$jadwalKaryawanMap = [];
foreach ($stmtJK->fetchAll() as $row) {
    $jadwalKaryawanMap[$row['karyawan_id']][] = $row;
}

// Semua jadwal aktif per karyawan (untuk modal JS — all karyawan regardless of search)
$allJKStmt = $db->prepare("SELECT jk.karyawan_id, jk.jadwal_id FROM jadwal_karyawan jk JOIN karyawan k ON k.id=jk.karyawan_id WHERE k.perusahaan_id=? AND (jk.berlaku_sampai IS NULL OR jk.berlaku_sampai >= CURDATE())");
$allJKStmt->execute([$user['perusahaan_id']]);
$allJKMap = [];
foreach ($allJKStmt->fetchAll() as $r) {
    $allJKMap[$r['karyawan_id']][] = $r['jadwal_id'];
}

$hariNames = ['','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$hariShort = ['','Sen','Sel','Rab','Kam','Jum','Sab','Min'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header flex justify-between items-center">
    <div><h2>Master Jadwal</h2><p>Kelola jadwal kerja dan penugasan karyawan</p></div>
    <button class="btn btn-primary" onclick="openModal('modalJadwal')"><i class="fas fa-plus"></i> Tambah Jadwal</button>
</div>

<!-- Daftar Jadwal -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>Daftar Jadwal</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Nama Jadwal</th><th>Shift</th><th>Lokasi</th><th>Hari Kerja</th><th>Berlaku</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if(empty($jadwals)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px">Belum ada jadwal. Klik "+ Tambah Jadwal" untuk membuat.</td></tr>
                <?php else: foreach($jadwals as $i=>$j):
                    $hk = json_decode($j['hari_kerja'],true)??[];
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($j['nama']) ?></strong></td>
                    <td>
                        <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($j['shift_nama']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-muted);font-family:'JetBrains Mono',monospace"><?= substr($j['jam_masuk'],0,5).' – '.substr($j['jam_keluar'],0,5) ?></div>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($j['lokasi_nama']) ?></td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:3px">
                            <?php foreach($hk as $h): ?>
                            <span style="font-size:11px;padding:2px 6px;background:var(--accent-light);color:#0d9488;border-radius:4px;font-weight:600"><?= $hariShort[$h] ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)"><?= $j['berlaku_dari'] ?><?= $j['berlaku_sampai'] ? '<br><span style="font-size:11px">s/d '.$j['berlaku_sampai'].'</span>' : '<br><span style="font-size:11px">Selamanya</span>' ?></td>
                    <td><?= badgeStatus($j['status']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?edit=<?= $j['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" onsubmit="return confirm('Hapus jadwal ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
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

<!-- Penugasan Jadwal Karyawan -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <h3><i class="fas fa-user-clock" style="color:var(--primary)"></i> Penugasan Jadwal Karyawan</h3>
        <!-- Search -->
        <form method="GET" style="display:flex;gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Cari nama / NIK..." value="<?= htmlspecialchars($search) ?>" style="width:220px">
            <button class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
            <?php if($search): ?><a href="?" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Karyawan</th>
                    <th>Jadwal Aktif</th>
                    <th style="width:120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($karyawans)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:24px">
                    <?= $search ? "Tidak ada karyawan dengan pencarian \"$search\"" : 'Belum ada karyawan aktif.' ?>
                </td></tr>
                <?php else: foreach($karyawans as $i=>$k):
                    $jadwalAktif = $jadwalKaryawanMap[$k['id']] ?? [];
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($k['nama']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($k['nik']) ?></div>
                    </td>
                    <td>
                        <?php if(empty($jadwalAktif)): ?>
                            <span style="font-size:12px;color:var(--text-muted);font-style:italic">Belum ada jadwal</span>
                        <?php else: ?>
                            <div style="display:flex;flex-wrap:wrap;gap:5px">
                            <?php foreach($jadwalAktif as $jk):
                                $hkArr = json_decode($jk['hari_kerja'],true)??[];
                                $hkStr = implode(',', array_map(fn($h)=>$hariShort[$h], $hkArr));
                            ?>
                                <div style="display:inline-flex;align-items:center;gap:5px;background:var(--accent-light);border:1px solid #99f6e4;border-radius:6px;padding:3px 8px;font-size:12px">
                                    <i class="fas fa-calendar-check" style="color:#0d9488;font-size:10px"></i>
                                    <span style="color:#0d9488;font-weight:600"><?= htmlspecialchars($jk['jadwal_nama']) ?></span>
                                    <span style="color:var(--text-muted)"><?= substr($jk['jam_masuk'],0,5).'–'.substr($jk['jam_keluar'],0,5) ?></span>
                                    <span style="background:rgba(13,148,136,.1);border-radius:3px;padding:1px 4px;font-size:10px;color:#0d9488"><?= $hkStr ?></span>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick='openAssignModal(<?= $k['id'] ?>, <?= json_encode($k['nama']) ?>, <?= json_encode(array_column($jadwalAktif,'jadwal_id')) ?>)'>
                            <i class="fas fa-calendar-plus"></i> Atur Jadwal
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if(count($karyawans) > 0): ?>
    <div style="padding:10px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted)">
        Menampilkan <?= count($karyawans) ?> karyawan<?= $search ? " (filter: \"$search\")" : '' ?>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MODAL ASSIGN JADWAL ===== -->
<div class="modal-overlay" id="modalAssign">
    <div class="modal" style="max-width:480px;width:96vw">
        <div class="modal-header">
            <div>
                <h3 id="assignTitle">Atur Jadwal Karyawan</h3>
                <div id="assignSubtitle" style="font-size:12px;color:var(--text-muted);margin-top:2px"></div>
            </div>
            <div class="modal-close" onclick="closeModal('modalAssign')"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST" id="formAssign">
            <div class="modal-body" style="padding:20px">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="karyawan_id" id="assignKaryawanId">

                <div style="margin-bottom:16px">
                    <label class="form-label" style="margin-bottom:8px">Pilih Jadwal <span style="color:var(--danger)">*</span></label>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Centang satu atau lebih jadwal yang akan diterapkan</div>
                    <div id="assignJadwalList" style="display:flex;flex-direction:column;gap:6px;max-height:260px;overflow-y:auto">
                        <?php foreach($jadwals as $jd):
                            $hkArr = json_decode($jd['hari_kerja'],true)??[];
                            $hkStr = implode(' · ', array_map(fn($h)=>$hariNames[$h], $hkArr));
                        ?>
                        <label class="assign-jadwal-item" data-id="<?= $jd['id'] ?>" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s">
                            <input type="checkbox" name="jadwal_ids[]" value="<?= $jd['id'] ?>" style="width:16px;height:16px;flex-shrink:0" onchange="styleAssignItem(this)">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($jd['nama']) ?></div>
                                <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;display:flex;gap:8px;flex-wrap:wrap">
                                    <span><i class="fas fa-clock" style="color:var(--primary)"></i> <?= substr($jd['jam_masuk'],0,5).' – '.substr($jd['jam_keluar'],0,5) ?></span>
                                    <span><i class="fas fa-calendar" style="color:var(--accent)"></i> <?= $hkStr ?></span>
                                </div>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);flex-shrink:0;text-align:right">
                                <?= htmlspecialchars($jd['lokasi_nama']) ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        <?php if(empty($jadwals)): ?>
                        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                            <i class="fas fa-calendar-xmark" style="font-size:1.5rem;margin-bottom:6px;display:block"></i>
                            Belum ada jadwal. Buat jadwal terlebih dahulu.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px">
                    <div>
                        <label class="form-label">Berlaku Dari <span style="color:var(--danger)">*</span></label>
                        <input type="date" name="berlaku_dari_assign" id="inputBerlakuDari" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="form-label">Berlaku Sampai <span style="color:var(--text-muted);font-weight:400;font-size:11px">(kosong = selamanya)</span></label>
                        <input type="date" name="berlaku_sampai_assign" id="inputBerlakuSampai" class="form-control">
                    </div>
                </div>
                <div style="margin-top:10px;padding:8px 12px;background:#eff6ff;border-radius:8px;font-size:12px;color:#1d4ed8;border-left:3px solid #3b82f6">
                    <i class="fas fa-info-circle"></i> Jadwal aktif sebelumnya akan digantikan dengan jadwal baru ini
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAssign')">Batal</button>
                <button type="button" class="btn btn-primary" onclick="konfirmasiSimpanJadwal()"><i class="fas fa-save"></i> Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODAL KONFIRMASI SIMPAN ===== -->
<div class="modal-overlay" id="modalKonfirmasi" style="z-index:1100">
    <div class="modal" style="max-width:400px;width:96vw">
        <div class="modal-header" style="border-bottom:none;padding-bottom:0">
            <div style="width:52px;height:52px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto">
                <i class="fas fa-calendar-check" style="font-size:1.4rem;color:#3b82f6"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align:center;padding:16px 24px 8px">
            <h3 style="margin-bottom:8px;font-size:1.1rem">Konfirmasi Simpan Jadwal</h3>
            <div id="konfirmasiDetail" style="font-size:13px;color:var(--text-muted);line-height:1.6"></div>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:10px">
            <button type="button" class="btn btn-outline" style="min-width:100px" onclick="closeModal('modalKonfirmasi')">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
            <button type="button" class="btn btn-primary" style="min-width:120px" id="btnKonfirmasiOk" onclick="doSimpanJadwal()">
                <i class="fas fa-check"></i> Ya, Simpan
            </button>
        </div>
    </div>
</div>

<!-- ===== TOAST NOTIFIKASI ===== -->
<div id="toastContainer" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;pointer-events:none;display:none">
    <div id="toastBox" style="background:#fff;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.18);padding:28px 36px;text-align:center;min-width:280px;max-width:360px;pointer-events:all">
        <div id="toastIcon" style="font-size:2.5rem;margin-bottom:12px"></div>
        <div id="toastTitle" style="font-size:1rem;font-weight:700;margin-bottom:4px"></div>
        <div id="toastMsg" style="font-size:13px;color:var(--text-muted)"></div>
        <button onclick="hideToast()" style="margin-top:16px;padding:7px 24px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-weight:600;cursor:pointer;font-size:13px">OK</button>
    </div>
</div>
<div id="toastBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9998;display:none;backdrop-filter:blur(2px)"></div>

<!-- ===== MODAL TAMBAH/EDIT JADWAL ===== -->
<div class="modal-overlay <?= $edit?'open':'' ?>" id="modalJadwal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><?= $edit?'Edit Jadwal':'Tambah Jadwal' ?></h3>
            <div class="modal-close" onclick="closeModal('modalJadwal')"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Jadwal <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($edit['nama']??'') ?>" placeholder="Contoh: Shift Senin–Jumat" required>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Shift <span class="req">*</span></label>
                        <select name="shift_id" class="form-select" required>
                            <option value="">-- Pilih Shift --</option>
                            <?php foreach($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($edit['shift_id']??'')==$s['id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['nama']) ?> (<?= substr($s['jam_masuk'],0,5).' - '.substr($s['jam_keluar'],0,5) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lokasi <span class="req">*</span></label>
                        <select name="lokasi_id" class="form-select" required>
                            <option value="">-- Pilih Lokasi --</option>
                            <?php foreach($lokasis as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($edit['lokasi_id']??'')==$l['id']?'selected':'' ?>>
                                <?= htmlspecialchars($l['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Hari Kerja <span class="req">*</span></label>
                    <?php $editHari = $edit ? (json_decode($edit['hari_kerja'],true)??[]) : []; ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <?php for($h=1;$h<=7;$h++): ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;transition:all .2s;user-select:none" class="hari-label">
                            <input type="checkbox" name="hari_kerja[]" value="<?= $h ?>" <?= in_array($h,$editHari)?'checked':'' ?> onchange="updateHariStyle(this)" style="display:none">
                            <?= $hariNames[$h] ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Berlaku Dari <span class="req">*</span></label>
                        <input type="date" name="berlaku_dari" class="form-control" value="<?= $edit['berlaku_dari']??date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Berlaku Sampai <span style="color:var(--text-muted);font-weight:400">(kosong = selamanya)</span></label>
                        <input type="date" name="berlaku_sampai" class="form-control" value="<?= $edit['berlaku_sampai']??'' ?>">
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
                    <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($edit['keterangan']??'') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalJadwal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ---- Modal helpers ---- */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'modalJadwal') history.replaceState(null,'',window.location.pathname);
}

/* ---- Hari kerja style (modal jadwal) ---- */
function updateHariStyle(cb) {
    const lbl = cb.closest('label');
    if (cb.checked) {
        lbl.style.background   = 'var(--accent-light)';
        lbl.style.borderColor  = 'var(--accent)';
        lbl.style.color        = '#0d9488';
    } else {
        lbl.style.background   = '';
        lbl.style.borderColor  = '';
        lbl.style.color        = '';
    }
}
document.querySelectorAll('.hari-label input[type=checkbox]').forEach(cb => updateHariStyle(cb));

/* ---- Assign Modal ---- */
let currentKaryawanNama = '';

function openAssignModal(karyawanId, karyawanNama, activeJadwalIds) {
    currentKaryawanNama = karyawanNama;
    document.getElementById('assignKaryawanId').value = karyawanId;
    document.getElementById('assignTitle').textContent = 'Atur Jadwal: ' + karyawanNama;
    document.getElementById('assignSubtitle').textContent = 'Centang jadwal yang akan diterapkan untuk karyawan ini';
    // Reset berlaku sampai
    document.getElementById('inputBerlakuSampai').value = '';

    // Reset & set checkboxes berdasarkan jadwal aktif
    document.querySelectorAll('#assignJadwalList input[type=checkbox]').forEach(cb => {
        const isActive = activeJadwalIds.includes(parseInt(cb.value));
        cb.checked = isActive;
        styleAssignItem(cb);
    });

    openModal('modalAssign');
}

function styleAssignItem(cb) {
    const lbl = cb.closest('label');
    if (cb.checked) {
        lbl.style.borderColor = 'var(--accent)';
        lbl.style.background  = 'var(--accent-light)';
    } else {
        lbl.style.borderColor = '';
        lbl.style.background  = '';
    }
}

// Init style assign items on page load
document.querySelectorAll('.assign-jadwal-item input').forEach(cb => styleAssignItem(cb));

/* ---- Konfirmasi Simpan ---- */
function konfirmasiSimpanJadwal() {
    const checked = [...document.querySelectorAll('#assignJadwalList input:checked')];
    const berlakuDari   = document.getElementById('inputBerlakuDari').value;
    const berlakuSampai = document.getElementById('inputBerlakuSampai').value;

    if (!berlakuDari) {
        showToast('error', 'Tanggal Wajib', 'Isi tanggal berlaku dari terlebih dahulu.');
        return;
    }

    let detailHTML = `<strong>${currentKaryawanNama}</strong> akan mendapatkan:<br><br>`;
    if (checked.length === 0) {
        detailHTML += '<span style="color:var(--danger)"><i class="fas fa-trash"></i> Semua jadwal akan dihapus</span><br>';
    } else {
        checked.forEach(cb => {
            const lbl = cb.closest('label');
            const nama = lbl.querySelector('div > div:first-child').textContent.trim();
            detailHTML += `<span style="color:#0d9488"><i class="fas fa-check-circle"></i> ${nama}</span><br>`;
        });
    }
    detailHTML += `<br><span style="font-size:12px">Berlaku: <strong>${berlakuDari}</strong>`;
    detailHTML += berlakuSampai ? ` s/d <strong>${berlakuSampai}</strong>` : ' (Selamanya)';
    detailHTML += '</span><br>';
    detailHTML += '<span style="font-size:12px;color:var(--danger)"><i class="fas fa-triangle-exclamation"></i> Jadwal aktif sebelumnya akan digantikan</span>';

    document.getElementById('konfirmasiDetail').innerHTML = detailHTML;
    openModal('modalKonfirmasi');
}

function doSimpanJadwal() {
    const btn = document.getElementById('btnKonfirmasiOk');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    closeModal('modalKonfirmasi');
    closeModal('modalAssign');
    document.getElementById('formAssign').submit();
}

/* ---- Toast Notifikasi ---- */
function showToast(type, title, msg) {
    const icons = {
        success: '<span style="color:#10b981">✅</span>',
        error:   '<span style="color:#ef4444">❌</span>',
        warning: '<span style="color:#f59e0b">⚠️</span>',
        info:    '<span style="color:#3b82f6">ℹ️</span>',
    };
    document.getElementById('toastIcon').innerHTML = icons[type] || icons.info;
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMsg').textContent   = msg;
    document.getElementById('toastContainer').style.display = 'block';
    document.getElementById('toastBackdrop').style.display  = 'block';
}

function hideToast() {
    document.getElementById('toastContainer').style.display = 'none';
    document.getElementById('toastBackdrop').style.display  = 'none';
}

// Tampilkan toast dari flash message PHP jika ada
<?php $flash = getFlash(); if($flash): ?>
window.addEventListener('load', () => {
    showToast(
        '<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'danger' ? 'error' : $flash['type']) ?>',
        '<?= $flash['type'] === 'success' ? 'Berhasil' : 'Gagal' ?>',
        '<?= addslashes($flash['message']) ?>'
    );
});
<?php endif; ?>

<?php if($edit): ?>
window.addEventListener('load', () => openModal('modalJadwal'));
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>