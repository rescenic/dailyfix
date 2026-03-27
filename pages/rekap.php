<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Rekap Absensi';
$activePage = 'rekap';
$user       = currentUser();
$db         = getDB();

$bulan  = $_GET['bulan'] ?? date('m');
$tahun  = $_GET['tahun'] ?? date('Y');
$period = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);

$stmt = $db->prepare("SELECT a.*, s.nama as shift_nama, s.jam_masuk, s.jam_keluar 
    FROM absensi a 
    LEFT JOIN shift s ON s.id = a.shift_id
    WHERE a.karyawan_id = ? AND DATE_FORMAT(a.tanggal,'%Y-%m') = ?
    ORDER BY a.tanggal DESC");
$stmt->execute([$user['id'], $period]);
$absensis = $stmt->fetchAll();

// Summary
$summary = ['hadir'=>0,'terlambat'=>0,'absen'=>0,'izin'=>0,'sakit'=>0,'total_terlambat_detik'=>0,'total_durasi'=>0];
foreach ($absensis as $a) {
    if (in_array($a['status_kehadiran'],['hadir','terlambat'])) $summary['hadir']++;
    if ($a['status_kehadiran']==='terlambat') $summary['terlambat']++;
    if ($a['status_kehadiran']==='absen') $summary['absen']++;
    if ($a['status_kehadiran']==='izin') $summary['izin']++;
    if ($a['status_kehadiran']==='sakit') $summary['sakit']++;
    $summary['total_terlambat_detik'] += (int)$a['terlambat_detik'];
    $summary['total_durasi'] += (int)$a['durasi_kerja'];
}

$months = [];
for ($m = 1; $m <= 12; $m++) $months[$m] = date('F', mktime(0,0,0,$m,1));

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Rekap Absensi</h2>
    <p>Riwayat absensi dan statistik kehadiran Anda</p>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:20px">
    <div style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label class="form-label" style="margin-bottom:4px">Bulan</label>
                <select name="bulan" class="form-select" style="min-width:130px">
                    <?php foreach($months as $m=>$n): ?><option value="<?= $m ?>" <?= $m==$bulan?'selected':'' ?>><?= $n ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="margin-bottom:4px">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for($y=date('Y');$y>=2024;$y--): ?><option value="<?= $y ?>" <?= $y==$tahun?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
</div>

<!-- Statistik -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-value"><?= $summary['hadir'] ?></div><div class="stat-label">Total Hadir</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= $summary['terlambat'] ?></div><div class="stat-label">Kali Terlambat</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-calendar-xmark"></i></div>
        <div><div class="stat-value"><?= $summary['absen'] ?></div><div class="stat-label">Absen</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-business-time"></i></div>
        <div><div class="stat-value"><?= formatDurasi($summary['total_durasi']) ?></div><div class="stat-label">Total Durasi Kerja</div></div>
    </div>
</div>

<?php if ($summary['total_terlambat_detik'] > 0): ?>
<div class="alert alert-warning" style="margin-bottom:20px">
    <i class="fas fa-clock"></i>
    Total keterlambatan bulan ini: <strong><?= formatTerlambat($summary['total_terlambat_detik']) ?></strong>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Detail Absensi — <?= $months[(int)$bulan] . ' ' . $tahun ?></h3>
        <a href="../api/export_rekap.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-outline btn-sm">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Hari</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Status</th>
                    <th>Terlambat</th>
                    <th>Durasi Kerja</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absensis)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:30px">Tidak ada data absensi untuk periode ini</td></tr>
                <?php else: foreach ($absensis as $a): ?>
                <tr>
                    <td style="font-weight:600"><?= date('d/m/Y', strtotime($a['tanggal'])) ?></td>
                    <td style="font-size:13px;color:var(--text-muted)"><?= date('l', strtotime($a['tanggal'])) ?></td>
                    <td style="font-size:13px"><?= htmlspecialchars($a['shift_nama']??'-') ?></td>
                    <td>
                        <?php if ($a['waktu_masuk']): ?>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= date('H:i:s', strtotime($a['waktu_masuk'])) ?></span>
                        <?php if ($a['jam_masuk']): ?>
                        <div style="font-size:11px;color:var(--text-muted)">Jadwal: <?= substr($a['jam_masuk'],0,5) ?></div>
                        <?php endif; ?>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($a['waktu_keluar']): ?>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= date('H:i:s', strtotime($a['waktu_keluar'])) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td><?= badgeStatus($a['status_kehadiran']) ?></td>
                    <td>
                        <?php if ($a['terlambat_detik'] > 0): ?>
                        <span style="color:var(--warning);font-size:13px;font-weight:600"><?= formatTerlambat($a['terlambat_detik']) ?></span>
                        <?php else: ?><span class="text-muted text-sm">-</span><?php endif; ?>
                    </td>
                    <td style="font-size:13px"><?= formatDurasi($a['durasi_kerja']) ?></td>
                    <td style="font-size:12.5px;color:var(--text-muted)"><?= htmlspecialchars($a['keterangan']??'') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
