<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$user       = currentUser();
$db         = getDB();

// Statistik hari ini
$today = date('Y-m-d');

if ($user['role'] === 'admin' || $user['role'] === 'manager') {
    $totalKaryawan = $db->query("SELECT COUNT(*) FROM karyawan WHERE perusahaan_id = {$user['perusahaan_id']} AND role = 'karyawan' AND status = 'aktif'")->fetchColumn();
    $hadirHariIni  = $db->query("SELECT COUNT(*) FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id WHERE k.perusahaan_id = {$user['perusahaan_id']} AND a.tanggal = '$today' AND a.status_kehadiran IN ('hadir','terlambat')")->fetchColumn();
    $terlambat     = $db->query("SELECT COUNT(*) FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id WHERE k.perusahaan_id = {$user['perusahaan_id']} AND a.tanggal = '$today' AND a.status_kehadiran = 'terlambat'")->fetchColumn();
    $absen         = $totalKaryawan - $hadirHariIni;

    // Absensi 7 hari terakhir
    $stmt = $db->prepare("SELECT DATE_FORMAT(a.tanggal,'%d/%m') as tgl, 
        SUM(CASE WHEN a.status_kehadiran IN ('hadir','terlambat') THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN a.status_kehadiran = 'terlambat' THEN 1 ELSE 0 END) as terlambat
        FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id
        WHERE k.perusahaan_id = ? AND a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY a.tanggal ORDER BY a.tanggal ASC");
    $stmt->execute([$user['perusahaan_id']]);
    $chartData = $stmt->fetchAll();

    // Daftar absensi hari ini
    $stmtAbsen = $db->prepare("SELECT a.*, k.nama, k.nik, k.foto FROM absensi a 
        JOIN karyawan k ON k.id = a.karyawan_id
        WHERE k.perusahaan_id = ? AND a.tanggal = ? ORDER BY a.waktu_masuk DESC LIMIT 10");
    $stmtAbsen->execute([$user['perusahaan_id'], $today]);
    $absensiHariIni = $stmtAbsen->fetchAll();
} else {
    // Statistik personal
    $bulan = date('Y-m');
    $totalHadir   = $db->prepare("SELECT COUNT(*) FROM absensi WHERE karyawan_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ? AND status_kehadiran IN ('hadir','terlambat')");
    $totalHadir->execute([$user['id'], $bulan]);
    $totalHadir = $totalHadir->fetchColumn();

    $totalTerlambat = $db->prepare("SELECT COUNT(*) FROM absensi WHERE karyawan_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ? AND status_kehadiran = 'terlambat'");
    $totalTerlambat->execute([$user['id'], $bulan]);
    $totalTerlambat = $totalTerlambat->fetchColumn();

    $absensiSaya = $db->prepare("SELECT a.*, s.nama as shift_nama, s.jam_masuk, s.jam_keluar FROM absensi a LEFT JOIN shift s ON s.id = a.shift_id WHERE a.karyawan_id = ? ORDER BY a.tanggal DESC LIMIT 7");
    $absensiSaya->execute([$user['id']]);
    $absensiSaya = $absensiSaya->fetchAll();

    // Absensi hari ini
    $stmtToday = $db->prepare("SELECT a.*, s.nama as shift_nama, s.jam_masuk, s.jam_keluar, l.nama as lokasi_nama FROM absensi a LEFT JOIN shift s ON s.id = a.shift_id LEFT JOIN jadwal j ON j.id = a.jadwal_id LEFT JOIN lokasi l ON l.id = j.lokasi_id WHERE a.karyawan_id = ? AND a.tanggal = ?");
    $stmtToday->execute([$user['id'], $today]);
    $absenToday = $stmtToday->fetch();

    // Jadwal aktif
    $stmtJadwal = $db->prepare("SELECT jk.*, j.nama as jadwal_nama, s.nama as shift_nama, s.jam_masuk, s.jam_keluar, s.toleransi_terlambat_detik, l.nama as lokasi_nama FROM jadwal_karyawan jk JOIN jadwal j ON j.id = jk.jadwal_id JOIN shift s ON s.id = j.shift_id JOIN lokasi l ON l.id = j.lokasi_id WHERE jk.karyawan_id = ? AND jk.berlaku_dari <= CURDATE() AND (jk.berlaku_sampai IS NULL OR jk.berlaku_sampai >= CURDATE()) LIMIT 1");
    $stmtJadwal->execute([$user['id']]);
    $jadwalAktif = $stmtJadwal->fetch();
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>Selamat Datang, <?= htmlspecialchars(explode(' ', $user['nama'])[0]) ?>! 👋</h2>
    <p><?= tglIndonesia() ?> — <?= ucfirst($user['role']) ?></p>
</div>

<?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
<!-- ===== ADMIN DASHBOARD ===== -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $totalKaryawan ?></div>
            <div class="stat-label">Total Karyawan Aktif</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-value"><?= $hadirHariIni ?></div>
            <div class="stat-label">Hadir Hari Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $terlambat ?></div>
            <div class="stat-label">Terlambat Hari Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-xmark"></i></div>
        <div>
            <div class="stat-value"><?= $absen < 0 ? 0 : $absen ?></div>
            <div class="stat-label">Belum Absen</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line" style="color:var(--primary)"></i> Kehadiran 7 Hari Terakhir</h3>
        </div>
        <div class="card-body">
            <canvas id="chartKehadiran" height="200"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-check" style="color:var(--accent)"></i> Absensi Hari Ini</h3>
            <a href="pages/rekap_admin.php" class="btn btn-outline btn-sm">Lihat Semua</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Karyawan</th><th>Masuk</th><th>Status</th><th class="hide-mobile">Terlambat</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($absensiHariIni)): ?>
                    <tr><td colspan="4" class="text-center text-muted" style="padding:24px">Belum ada absensi hari ini</td></tr>
                    <?php else: foreach ($absensiHariIni as $ab): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($ab['nama']) ?></div>
                            <div style="font-size:11.5px;color:var(--text-muted)"><?= $ab['nik'] ?></div>
                        </td>
                        <td style="font-family:'JetBrains Mono',monospace;font-size:13px">
                            <?= $ab['waktu_masuk'] ? date('H:i', strtotime($ab['waktu_masuk'])) : '-' ?>
                        </td>
                        <td><?= badgeStatus($ab['status_kehadiran']) ?></td>
                        <td style="font-size:12.5px;color:var(--text-muted)">
                            <?= formatTerlambat($ab['terlambat_detik']) ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
const labels = chartData.map(d => d.tgl);
const hadir  = chartData.map(d => parseInt(d.hadir));
const terlambat = chartData.map(d => parseInt(d.terlambat));
new Chart(document.getElementById('chartKehadiran'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Hadir', data: hadir, backgroundColor: '#dcfce7', borderColor: '#16a34a', borderWidth: 2, borderRadius: 6 },
            { label: 'Terlambat', data: terlambat, backgroundColor: '#fef3c7', borderColor: '#d97706', borderWidth: 2, borderRadius: 6 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php else: ?>
<!-- ===== KARYAWAN DASHBOARD ===== -->
<div class="grid-2" style="margin-bottom:20px">
    <div class="absen-widget">
        <div class="absen-time" id="liveTime">--:--:--</div>
        <div class="absen-date"><?= tglIndonesia() ?></div>
        <?php if ($jadwalAktif): ?>
        <div style="margin-top:10px;font-size:13px;opacity:.85">
            <i class="fas fa-clock"></i>
            <?= htmlspecialchars($jadwalAktif['shift_nama']) ?> &nbsp;|&nbsp;
            <?= substr($jadwalAktif['jam_masuk'],0,5) ?> – <?= substr($jadwalAktif['jam_keluar'],0,5) ?>
        </div>
        <div class="loc-status">
            <div class="loc-dot" id="locDot"></div>
            <span id="locText">Mendeteksi lokasi...</span>
        </div>
        <?php endif; ?>
        <a href="pages/absen.php" class="btn-absen <?= ($absenToday && $absenToday['waktu_masuk'] && !$absenToday['waktu_keluar']) ? 'btn-absen-keluar' : 'btn-absen-masuk' ?>">
            <i class="fas fa-<?= ($absenToday && $absenToday['waktu_masuk'] && !$absenToday['waktu_keluar']) ? 'door-open' : 'fingerprint' ?>"></i>
            <?= ($absenToday && $absenToday['waktu_masuk'] && !$absenToday['waktu_keluar']) ? 'Absen Keluar' : 'Absen Masuk' ?>
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Status Absen Hari Ini</h3>
        </div>
        <div class="card-body">
            <?php if ($absenToday && $absenToday['waktu_masuk']): ?>
            <div style="display:grid;gap:12px">
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface2);border-radius:8px">
                    <div style="width:40px;height:40px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:16px"><i class="fas fa-sign-in-alt"></i></div>
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);font-weight:600">MASUK</div>
                        <div style="font-size:20px;font-family:'JetBrains Mono',monospace;font-weight:700"><?= date('H:i:s', strtotime($absenToday['waktu_masuk'])) ?></div>
                    </div>
                    <div style="margin-left:auto"><?= badgeStatus($absenToday['status_kehadiran']) ?></div>
                </div>
                <?php if ($absenToday['waktu_keluar']): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface2);border-radius:8px">
                    <div style="width:40px;height:40px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:16px"><i class="fas fa-sign-out-alt"></i></div>
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);font-weight:600">KELUAR</div>
                        <div style="font-size:20px;font-family:'JetBrains Mono',monospace;font-weight:700"><?= date('H:i:s', strtotime($absenToday['waktu_keluar'])) ?></div>
                    </div>
                    <div style="margin-left:auto;font-size:13px;color:var(--text-muted)">Durasi: <?= formatDurasi($absenToday['durasi_kerja']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($absenToday['terlambat_detik'] > 0): ?>
                <div style="padding:10px 14px;background:#fffbeb;border-radius:8px;border-left:3px solid #f59e0b;font-size:13px;color:#92400e">
                    <i class="fas fa-clock"></i> Terlambat: <strong><?= formatTerlambat($absenToday['terlambat_detik']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:30px 20px;color:var(--text-muted)">
                <i class="fas fa-fingerprint" style="font-size:40px;opacity:.3;margin-bottom:10px;display:block"></i>
                <p>Anda belum melakukan absen hari ini</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats bulan ini -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        <div>
            <div class="stat-value"><?= $totalHadir ?></div>
            <div class="stat-label">Hadir Bulan Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $totalTerlambat ?></div>
            <div class="stat-label">Terlambat Bulan Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
        <div>
            <div class="stat-value"><?= date('t') - date('j') ?></div>
            <div class="stat-label">Sisa Hari Kerja</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Riwayat Absensi Terakhir</h3>
        <a href="pages/rekap.php" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Tanggal</th><th class="hide-mobile">Shift</th><th>Masuk</th><th>Keluar</th><th>Status</th><th class="hide-mobile">Terlambat</th><th class="hide-mobile">Durasi</th></tr>
            </thead>
            <tbody>
                <?php if (empty($absensiSaya)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Belum ada data absensi</td></tr>
                <?php else: foreach ($absensiSaya as $ab): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($ab['tanggal'])) ?></td>
                    <td style="font-size:12.5px"><?= htmlspecialchars($ab['shift_nama'] ?? '-') ?></td>
                    <td style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= $ab['waktu_masuk'] ? date('H:i', strtotime($ab['waktu_masuk'])) : '-' ?></td>
                    <td style="font-family:'JetBrains Mono',monospace;font-size:13px"><?= $ab['waktu_keluar'] ? date('H:i', strtotime($ab['waktu_keluar'])) : '-' ?></td>
                    <td><?= badgeStatus($ab['status_kehadiran']) ?></td>
                    <td style="font-size:12.5px"><?= formatTerlambat($ab['terlambat_detik']) ?></td>
                    <td style="font-size:12.5px"><?= formatDurasi($ab['durasi_kerja']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
setInterval(() => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveTime').textContent = h+':'+m+':'+s;
}, 1000);

if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('locDot').classList.add('ok');
        document.getElementById('locText').textContent = 'Lokasi terdeteksi ✓';
    }, () => {
        document.getElementById('locText').textContent = 'Akses lokasi ditolak';
    });
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>