<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Rekap Semua Karyawan';
$activePage = 'rekap_admin';
$user       = currentUser();
$db         = getDB();

$bulan       = (int)($_GET['bulan']       ?? date('m'));
$tahun       = (int)($_GET['tahun']       ?? date('Y'));
$karyawan_id = (int)($_GET['karyawan_id'] ?? 0);
$period      = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);

$monthsId = ['','Januari','Februari','Maret','April','Mei','Juni',
             'Juli','Agustus','September','Oktober','November','Desember'];
$hariId   = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab',
             'Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];

// Query rekap
$sql = "SELECT k.id, k.nik, k.nama,
    SUM(CASE WHEN a.status_kehadiran IN ('hadir','terlambat') THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN a.status_kehadiran = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN a.status_kehadiran = 'absen'     THEN 1 ELSE 0 END) as absen,
    SUM(CASE WHEN a.status_kehadiran = 'izin'      THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN a.status_kehadiran = 'sakit'     THEN 1 ELSE 0 END) as sakit,
    SUM(COALESCE(a.terlambat_detik,0)) as total_terlambat_detik,
    SUM(COALESCE(a.durasi_kerja,0))    as total_durasi
    FROM karyawan k
    LEFT JOIN absensi a ON a.karyawan_id=k.id AND DATE_FORMAT(a.tanggal,'%Y-%m')=?
    WHERE k.perusahaan_id=? AND k.role='karyawan'";
$params = [$period, $user['perusahaan_id']];
if ($karyawan_id) { $sql .= " AND k.id=?"; $params[] = $karyawan_id; }
$sql .= " GROUP BY k.id ORDER BY k.nama";
$stmt = $db->prepare($sql); $stmt->execute($params);
$rekapKaryawan = $stmt->fetchAll();

// Detail per karyawan
$detailAbsen = [];
$selectedK   = null;
if ($karyawan_id) {
    $stmtD = $db->prepare("SELECT a.*, s.nama as shift_nama FROM absensi a
        LEFT JOIN shift s ON s.id=a.shift_id
        WHERE a.karyawan_id=? AND DATE_FORMAT(a.tanggal,'%Y-%m')=? ORDER BY a.tanggal");
    $stmtD->execute([$karyawan_id, $period]);
    $detailAbsen = $stmtD->fetchAll();

    $stmtK = $db->prepare("SELECT * FROM karyawan WHERE id=? AND perusahaan_id=?");
    $stmtK->execute([$karyawan_id, $user['perusahaan_id']]);
    $selectedK = $stmtK->fetch();
}

$allKaryawan = $db->prepare("SELECT id,nama,nik FROM karyawan WHERE perusahaan_id=? AND role='karyawan' ORDER BY nama");
$allKaryawan->execute([$user['perusahaan_id']]); $allKaryawan = $allKaryawan->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Mobile-first rekap ── */
.rekap-header-actions { display:flex; gap:8px; flex-wrap:wrap; }

/* Stat summary bar */
.rekap-summary {
    display:grid;
    grid-template-columns: repeat(5,1fr);
    gap:10px;
    margin-bottom:20px;
}
@media(max-width:640px) { .rekap-summary { grid-template-columns:repeat(3,1fr); } }
.rekap-sum-item {
    background:#fff;
    border-radius:12px;
    padding:14px 10px;
    text-align:center;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}
.rekap-sum-num  { font-size:1.6rem; font-weight:800; line-height:1; }
.rekap-sum-lbl  { font-size:11px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-top:4px; }

/* Card karyawan mobile */
.karyawan-card {
    background:#fff;
    border-radius:12px;
    border:1px solid var(--border);
    padding:14px 16px;
    margin-bottom:10px;
    box-shadow:var(--shadow);
    display:none; /* hidden on desktop, shown on mobile */
}
@media(max-width:700px) {
    .karyawan-card  { display:block; }
    .table-rekap-wrap { display:none; }
}
.karyawan-card-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
.karyawan-card-stats  { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:12px; }
.kc-stat { text-align:center; background:var(--surface2); border-radius:8px; padding:8px 4px; }
.kc-stat-num { font-size:1.2rem; font-weight:800; }
.kc-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; }
.karyawan-card-actions { display:flex; gap:8px; }
.karyawan-card-actions .btn { flex:1; justify-content:center; }

/* Detail absensi mobile */
.detail-item {
    background:#fff;
    border-radius:10px;
    border:1px solid var(--border);
    padding:12px 14px;
    margin-bottom:8px;
}
.detail-item-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.detail-item-stats  { display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:12.5px; }
.detail-stat-row    { display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid var(--border); }
.detail-stat-row:last-child { border:none; }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h2>Rekap Semua Karyawan</h2>
        <p>Laporan kehadiran — <?= $monthsId[$bulan].' '.$tahun ?></p>
    </div>
    <div class="rekap-header-actions">
        <a href="cetak_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $karyawan_id ?>"
           target="_blank" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> <span class="hide-xs">Cetak </span>PDF
        </a>
        <a href="../api/export_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $karyawan_id ?>&all=1"
           class="btn btn-outline btn-sm">
            <i class="fas fa-download"></i> <span class="hide-xs">Export </span>CSV
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:16px">
    <div style="padding:12px 16px">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="display:flex;flex-direction:column;gap:4px">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted)">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m==$bulan?'selected':'' ?>><?= $monthsId[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted)">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for($y=date('Y');$y>=2024;$y--): ?>
                    <option value="<?= $y ?>" <?= $y==$tahun?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:160px">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted)">Karyawan</label>
                <select name="karyawan_id" class="form-select">
                    <option value="">Semua Karyawan</option>
                    <?php foreach($allKaryawan as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $k['id']==$karyawan_id?'selected':'' ?>>
                        <?= htmlspecialchars($k['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
            <?php if($karyawan_id): ?>
            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-outline"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Summary stats bar -->
<?php
$totHadir=$totTelat=$totAbsen=$totIzin=$totSakit=0;
foreach($rekapKaryawan as $r){
    $totHadir+=$r['hadir']; $totTelat+=$r['terlambat'];
    $totAbsen+=$r['absen']; $totIzin+=$r['izin']; $totSakit+=$r['sakit'];
}
?>
<div class="rekap-summary">
    <div class="rekap-sum-item">
        <div class="rekap-sum-num" style="color:var(--success)"><?= $totHadir ?></div>
        <div class="rekap-sum-lbl"><i class="fas fa-user-check"></i> Hadir</div>
    </div>
    <div class="rekap-sum-item">
        <div class="rekap-sum-num" style="color:var(--warning)"><?= $totTelat ?></div>
        <div class="rekap-sum-lbl"><i class="fas fa-clock"></i> Terlambat</div>
    </div>
    <div class="rekap-sum-item">
        <div class="rekap-sum-num" style="color:var(--danger)"><?= $totAbsen ?></div>
        <div class="rekap-sum-lbl"><i class="fas fa-user-xmark"></i> Absen</div>
    </div>
    <div class="rekap-sum-item">
        <div class="rekap-sum-num" style="color:var(--info)"><?= $totIzin ?></div>
        <div class="rekap-sum-lbl"><i class="fas fa-file-lines"></i> Izin</div>
    </div>
    <div class="rekap-sum-item">
        <div class="rekap-sum-num" style="color:#7c3aed"><?= $totSakit ?></div>
        <div class="rekap-sum-lbl"><i class="fas fa-kit-medical"></i> Sakit</div>
    </div>
</div>

<!-- ═══ DESKTOP: Tabel ═══ -->
<div class="card table-rekap-wrap" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <h3>Ringkasan Kehadiran — <?= $monthsId[$bulan].' '.$tahun ?></h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($rekapKaryawan) ?> karyawan</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th class="text-center"><i class="fas fa-user-check" style="color:var(--success)"></i> Hadir</th>
                    <th class="text-center"><i class="fas fa-clock" style="color:var(--warning)"></i> Terlambat</th>
                    <th class="text-center"><i class="fas fa-user-xmark" style="color:var(--danger)"></i> Absen</th>
                    <th class="text-center"><i class="fas fa-file-lines" style="color:var(--info)"></i> Izin</th>
                    <th class="text-center"><i class="fas fa-kit-medical" style="color:#7c3aed"></i> Sakit</th>
                    <th>Total Terlambat</th>
                    <th>Total Kerja</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekapKaryawan)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:24px">Tidak ada data</td></tr>
                <?php else: foreach ($rekapKaryawan as $r): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#0f4c81,#00c9a7);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0">
                                <?= strtoupper(substr($r['nama'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px"><?= htmlspecialchars($r['nama']) ?></div>
                                <div style="font-size:11.5px;color:var(--text-muted)"><?= $r['nik'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center"><span style="font-weight:700;color:var(--success)"><?= $r['hadir'] ?></span></td>
                    <td class="text-center"><span style="font-weight:700;color:var(--warning)"><?= $r['terlambat'] ?></span></td>
                    <td class="text-center"><span style="font-weight:700;color:var(--danger)"><?= $r['absen'] ?></span></td>
                    <td class="text-center"><span style="font-weight:700;color:var(--info)"><?= $r['izin'] ?></span></td>
                    <td class="text-center"><span style="font-weight:700;color:#7c3aed"><?= $r['sakit'] ?></span></td>
                    <td style="font-size:13px;color:<?= $r['total_terlambat_detik']>0?'var(--warning)':'var(--text-muted)' ?>">
                        <?= $r['total_terlambat_detik']>0 ? formatTerlambat($r['total_terlambat_detik']) : '-' ?>
                    </td>
                    <td style="font-size:13px"><?= formatDurasi($r['total_durasi']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $r['id'] ?>"
                               class="btn btn-outline btn-sm btn-icon" title="Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="cetak_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $r['id'] ?>"
                               target="_blank" class="btn btn-sm btn-icon" style="background:#0f4c81;color:#fff;border:none" title="Cetak PDF">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ MOBILE: Card per karyawan ═══ -->
<?php foreach($rekapKaryawan as $r): ?>
<div class="karyawan-card">
    <div class="karyawan-card-header">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0f4c81,#00c9a7);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0">
            <?= strtoupper(substr($r['nama'],0,1)) ?>
        </div>
        <div style="flex:1">
            <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($r['nama']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $r['nik'] ?></div>
        </div>
        <?php if($r['total_terlambat_detik']>0): ?>
        <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:6px;font-weight:600">
            <i class="fas fa-clock"></i> <?= formatTerlambat($r['total_terlambat_detik']) ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="karyawan-card-stats">
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:var(--success)"><?= $r['hadir'] ?></div>
            <div class="kc-stat-lbl">Hadir</div>
        </div>
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:var(--warning)"><?= $r['terlambat'] ?></div>
            <div class="kc-stat-lbl">Terlambat</div>
        </div>
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:var(--danger)"><?= $r['absen'] ?></div>
            <div class="kc-stat-lbl">Absen</div>
        </div>
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:var(--info)"><?= $r['izin'] ?></div>
            <div class="kc-stat-lbl">Izin</div>
        </div>
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:#7c3aed"><?= $r['sakit'] ?></div>
            <div class="kc-stat-lbl">Sakit</div>
        </div>
        <div class="kc-stat">
            <div class="kc-stat-num" style="color:var(--primary)"><?= formatDurasi($r['total_durasi']) ?></div>
            <div class="kc-stat-lbl">Total Jam</div>
        </div>
    </div>
    <div class="karyawan-card-actions">
        <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $r['id'] ?>"
           class="btn btn-outline btn-sm" style="display:flex;align-items:center;justify-content:center;gap:6px">
            <i class="fas fa-eye"></i> Detail
        </a>
        <a href="cetak_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $r['id'] ?>"
           target="_blank" class="btn btn-sm" style="background:#0f4c81;color:#fff;display:flex;align-items:center;justify-content:center;gap:6px">
            <i class="fas fa-print"></i> PDF
        </a>
    </div>
</div>
<?php endforeach; ?>

<!-- Detail per karyawan -->
<?php if ($karyawan_id && $selectedK): ?>
<div class="card" style="margin-top:8px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <h3>
            <i class="fas fa-list-check" style="color:var(--primary)"></i>
            Detail — <?= htmlspecialchars($selectedK['nama']) ?>
        </h3>
        <a href="cetak_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&karyawan_id=<?= $karyawan_id ?>"
           target="_blank" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> Cetak PDF
        </a>
    </div>

    <?php if(empty($detailAbsen)): ?>
    <div style="text-align:center;padding:30px;color:var(--text-muted)">Tidak ada data absensi</div>
    <?php else: ?>

    <!-- Desktop detail table -->
    <div class="table-wrap table-rekap-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th><th>Hari</th><th>Shift</th>
                    <th>Masuk</th><th>Keluar</th><th>Status</th>
                    <th>Terlambat</th><th>Durasi</th><th>Jarak</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($detailAbsen as $a): ?>
            <tr>
                <td style="font-weight:600"><?= date('d/m/Y',strtotime($a['tanggal'])) ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= $hariId[date('D',strtotime($a['tanggal']))] ?? date('D',strtotime($a['tanggal'])) ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($a['shift_nama']??'-') ?></td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= $a['waktu_masuk']?date('H:i:s',strtotime($a['waktu_masuk'])):'-' ?></td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= $a['waktu_keluar']?date('H:i:s',strtotime($a['waktu_keluar'])):'-' ?></td>
                <td><?= badgeStatus($a['status_kehadiran']) ?></td>
                <td style="font-size:12.5px;color:var(--warning)"><?= $a['terlambat_detik']>0?formatTerlambat($a['terlambat_detik']):'-' ?></td>
                <td style="font-size:12.5px"><?= formatDurasi($a['durasi_kerja']) ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= $a['jarak_masuk']?$a['jarak_masuk'].'m':'-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile detail cards -->
    <div style="padding:12px;display:none" class="mobile-detail-list">
    <?php foreach($detailAbsen as $a):
        $hariStr = $hariId[date('D',strtotime($a['tanggal']))] ?? date('D',strtotime($a['tanggal']));
    ?>
    <div class="detail-item">
        <div class="detail-item-header">
            <div>
                <span style="font-weight:700;font-size:14px"><?= date('d/m/Y',strtotime($a['tanggal'])) ?></span>
                <span style="font-size:12px;color:var(--text-muted);margin-left:6px"><?= $hariStr ?></span>
            </div>
            <?= badgeStatus($a['status_kehadiran']) ?>
        </div>
        <div class="detail-item-stats">
            <div class="detail-stat-row">
                <span style="color:var(--text-muted)"><i class="fas fa-sign-in-alt" style="color:var(--success)"></i> Masuk</span>
                <span style="font-family:'JetBrains Mono',monospace;font-weight:600"><?= $a['waktu_masuk']?date('H:i:s',strtotime($a['waktu_masuk'])):'-' ?></span>
            </div>
            <div class="detail-stat-row">
                <span style="color:var(--text-muted)"><i class="fas fa-sign-out-alt" style="color:var(--danger)"></i> Keluar</span>
                <span style="font-family:'JetBrains Mono',monospace;font-weight:600"><?= $a['waktu_keluar']?date('H:i:s',strtotime($a['waktu_keluar'])):'-' ?></span>
            </div>
            <?php if($a['terlambat_detik']>0): ?>
            <div class="detail-stat-row">
                <span style="color:var(--text-muted)"><i class="fas fa-clock" style="color:var(--warning)"></i> Terlambat</span>
                <span style="color:var(--warning);font-weight:600"><?= formatTerlambat($a['terlambat_detik']) ?></span>
            </div>
            <?php endif; ?>
            <?php if($a['durasi_kerja']): ?>
            <div class="detail-stat-row">
                <span style="color:var(--text-muted)"><i class="fas fa-stopwatch" style="color:var(--primary)"></i> Durasi</span>
                <span style="font-weight:600"><?= formatDurasi($a['durasi_kerja']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <script>
    // Show mobile cards on small screen
    if (window.innerWidth <= 700) {
        document.querySelector('.mobile-detail-list').style.display = 'block';
    }
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>