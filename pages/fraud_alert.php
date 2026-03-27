<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Fraud Alert GPS';
$activePage = 'fraud_alert';
$user       = currentUser();
$db         = getDB();

// Handle aksi: tandai aman / blokir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $absensi_id  = (int)($_POST['absensi_id'] ?? 0);
    $karyawan_id = (int)($_POST['karyawan_id'] ?? 0);

    if ($action === 'mark_safe' && $absensi_id) {
        // Hapus flag dari keterangan
        $db->prepare("UPDATE absensi SET keterangan = REGEXP_REPLACE(keterangan, '\\\\[FLAG:[^\\\\]]*\\\\]\\\\s*', '') WHERE id = ? AND karyawan_id IN (SELECT id FROM karyawan WHERE perusahaan_id = ?)")
           ->execute([$absensi_id, $user['perusahaan_id']]);
        redirect(APP_URL.'/pages/fraud_alert.php', 'Absensi ditandai aman.', 'success');
    }

    if ($action === 'blokir' && $karyawan_id) {
        $db->prepare("UPDATE karyawan SET status='nonaktif' WHERE id=? AND perusahaan_id=?")
           ->execute([$karyawan_id, $user['perusahaan_id']]);
        logActivity('BLOKIR_KARYAWAN', "Memblokir karyawan ID $karyawan_id karena fraud GPS");
        redirect(APP_URL.'/pages/fraud_alert.php', 'Karyawan berhasil diblokir.', 'success');
    }

    if ($action === 'hapus_absensi' && $absensi_id) {
        $db->prepare("DELETE FROM absensi WHERE id=? AND karyawan_id IN (SELECT id FROM karyawan WHERE perusahaan_id=?)")
           ->execute([$absensi_id, $user['perusahaan_id']]);
        redirect(APP_URL.'/pages/fraud_alert.php', 'Data absensi mencurigakan dihapus.', 'success');
    }
}

// Filter
$filterTipe   = $_GET['tipe']   ?? '';
$filterBulan  = $_GET['bulan']  ?? date('m');
$filterTahun  = $_GET['tahun']  ?? date('Y');
$period       = $filterTahun . '-' . str_pad($filterBulan, 2, '0', STR_PAD_LEFT);

// Ambil semua absensi dengan FLAG
$sql = "SELECT a.*, k.nama, k.nik, k.email, k.status as karyawan_status,
        j.nama as jabatan_nama, d.nama as dept_nama
        FROM absensi a
        JOIN karyawan k ON k.id = a.karyawan_id
        LEFT JOIN jabatan j ON j.id = k.jabatan_id
        LEFT JOIN departemen d ON d.id = k.departemen_id
        WHERE k.perusahaan_id = ?
        AND a.keterangan LIKE '%[FLAG:%'
        AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
$params = [$user['perusahaan_id'], $period];

if ($filterTipe) {
    $sql .= " AND a.keterangan LIKE ?";
    $params[] = "%FLAG:%$filterTipe%";
}
$sql .= " ORDER BY a.tanggal DESC, a.waktu_masuk DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$fraudList = $stmt->fetchAll();

// Statistik fraud bulan ini
$stmtStat = $db->prepare("SELECT
    COUNT(*) as total_fraud,
    COUNT(DISTINCT karyawan_id) as total_karyawan,
    SUM(CASE WHEN keterangan LIKE '%location_anomaly%' THEN 1 ELSE 0 END) as anomaly_lokasi,
    SUM(CASE WHEN keterangan LIKE '%low_accuracy%' THEN 1 ELSE 0 END) as akurasi_rendah,
    SUM(CASE WHEN keterangan LIKE '%shared_ip%' THEN 1 ELSE 0 END) as shared_ip,
    SUM(CASE WHEN keterangan LIKE '%speed_anomaly%' THEN 1 ELSE 0 END) as speed_anomaly,
    SUM(CASE WHEN keterangan LIKE '%suspicious_coords%' THEN 1 ELSE 0 END) as coords_palsu
    FROM absensi a
    JOIN karyawan k ON k.id = a.karyawan_id
    WHERE k.perusahaan_id = ?
    AND a.keterangan LIKE '%[FLAG:%'
    AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?");
$stmtStat->execute([$user['perusahaan_id'], $period]);
$stat = $stmtStat->fetch();

// Top karyawan dengan fraud terbanyak
$stmtTop = $db->prepare("SELECT k.id, k.nama, k.nik, k.status,
    COUNT(a.id) as total_fraud
    FROM absensi a
    JOIN karyawan k ON k.id = a.karyawan_id
    WHERE k.perusahaan_id = ?
    AND a.keterangan LIKE '%[FLAG:%'
    AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
    GROUP BY k.id ORDER BY total_fraud DESC LIMIT 5");
$stmtTop->execute([$user['perusahaan_id'], $period]);
$topFraud = $stmtTop->fetchAll();

$months = ['','Januari','Februari','Maret','April','Mei','Juni',
           'Juli','Agustus','September','Oktober','November','Desember'];

// Parse flag dari keterangan
function parseFlags($keterangan) {
    preg_match_all('/\[FLAG:([^\]]+)\]/', $keterangan ?? '', $matches);
    if (empty($matches[1])) return [];
    $flags = [];
    foreach ($matches[1] as $m) {
        foreach (explode(',', $m) as $f) {
            $flags[] = trim($f);
        }
    }
    return array_unique($flags);
}

function flagLabel($flag) {
    $map = [
        'location_anomaly' => ['Anomali Lokasi',     'danger',  'fa-location-dot'],
        'low_accuracy'     => ['GPS Tidak Akurat',   'warning', 'fa-signal'],
        'shared_ip'        => ['IP Dipakai Bersama', 'info',    'fa-network-wired'],
        'speed_anomaly'    => ['Kecepatan Janggal',  'danger',  'fa-gauge-high'],
        'suspicious_coords'=> ['Koordinat Palsu',    'danger',  'fa-map-pin'],
        'out_of_radius'    => ['Di Luar Radius',     'warning', 'fa-circle-xmark'],
        'rate_limit'       => ['Terlalu Sering',     'secondary','fa-repeat'],
    ];
    return $map[$flag] ?? [ucfirst(str_replace('_',' ',$flag)), 'secondary', 'fa-flag'];
}

function riskLevel($flags) {
    $dangerFlags = ['location_anomaly','speed_anomaly','suspicious_coords'];
    $high = array_intersect($flags, $dangerFlags);
    if (count($high) >= 2) return ['TINGGI',  'danger'];
    if (count($high) >= 1) return ['SEDANG',  'warning'];
    return ['RENDAH', 'info'];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.fraud-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.fraud-stat { background:#fff; border-radius:12px; padding:16px; border:1px solid var(--border); display:flex; align-items:center; gap:12px; box-shadow:var(--shadow); }
.fraud-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.fraud-stat-num { font-size:1.5rem; font-weight:800; line-height:1; }
.fraud-stat-lbl { font-size:11px; color:var(--text-muted); margin-top:2px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

.flag-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; margin:2px; }
.flag-badge.danger   { background:#fee2e2; color:#991b1b; }
.flag-badge.warning  { background:#fef3c7; color:#92400e; }
.flag-badge.info     { background:#dbeafe; color:#1e40af; }
.flag-badge.secondary{ background:#f1f5f9; color:#475569; }

.risk-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.risk-badge.danger  { background:#fee2e2; color:#991b1b; }
.risk-badge.warning { background:#fef3c7; color:#92400e; }
.risk-badge.info    { background:#dbeafe; color:#1e40af; }

.fraud-row-high   { border-left:3px solid var(--danger) !important; }
.fraud-row-medium { border-left:3px solid var(--warning) !important; }
.fraud-row-low    { border-left:3px solid var(--info) !important; }

.top-fraudster { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
.top-fraudster:last-child { border-bottom:none; }
.rank-badge { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; flex-shrink:0; }

.empty-fraud { text-align:center; padding:48px 20px; }
.empty-fraud i { font-size:3rem; color:#10b981; display:block; margin-bottom:12px; }
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
        <h2><i class="fas fa-shield-halved" style="color:var(--danger)"></i> Fraud Alert GPS</h2>
        <p>Monitor aktivitas absensi mencurigakan secara real-time</p>
    </div>
    <!-- Filter -->
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select name="bulan" class="form-select" style="width:130px">
            <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m==$filterBulan?'selected':'' ?>><?= $months[$m] ?></option>
            <?php endfor; ?>
        </select>
        <select name="tahun" class="form-select" style="width:90px">
            <?php for($y=date('Y');$y>=2024;$y--): ?>
            <option value="<?= $y ?>" <?= $y==$filterTahun?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select name="tipe" class="form-select" style="width:160px">
            <option value="">Semua Tipe Flag</option>
            <option value="location_anomaly" <?= $filterTipe==='location_anomaly'?'selected':'' ?>>Anomali Lokasi</option>
            <option value="low_accuracy"     <?= $filterTipe==='low_accuracy'?'selected':'' ?>>GPS Tidak Akurat</option>
            <option value="shared_ip"        <?= $filterTipe==='shared_ip'?'selected':'' ?>>IP Dipakai Bersama</option>
            <option value="speed_anomaly"    <?= $filterTipe==='speed_anomaly'?'selected':'' ?>>Kecepatan Janggal</option>
            <option value="suspicious_coords"<?= $filterTipe==='suspicious_coords'?'selected':'' ?>>Koordinat Palsu</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- Statistik -->
<div class="fraud-stat-grid">
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-triangle-exclamation"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:var(--danger)"><?= $stat['total_fraud'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">Total Alert</div>
        </div>
    </div>
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-users"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:var(--warning)"><?= $stat['total_karyawan'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">Karyawan Terlibat</div>
        </div>
    </div>
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-location-dot"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:var(--danger)"><?= $stat['anomaly_lokasi'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">Anomali Lokasi</div>
        </div>
    </div>
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-signal"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:var(--warning)"><?= $stat['akurasi_rendah'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">GPS Tidak Akurat</div>
        </div>
    </div>
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-network-wired"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:#2563eb"><?= $stat['shared_ip'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">Shared IP</div>
        </div>
    </div>
    <div class="fraud-stat">
        <div class="fraud-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-map-pin"></i></div>
        <div>
            <div class="fraud-stat-num" style="color:var(--danger)"><?= $stat['coords_palsu'] ?? 0 ?></div>
            <div class="fraud-stat-lbl">Koordinat Palsu</div>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-bottom:20px;align-items:start">

    <!-- Tabel Alert -->
    <div class="card" style="grid-column:1/-1">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3><i class="fas fa-list" style="color:var(--danger)"></i> Daftar Alert — <?= $months[(int)$filterBulan].' '.$filterTahun ?></h3>
            <span style="font-size:12px;color:var(--text-muted);background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-weight:600">
                <?= count($fraudList) ?> alert ditemukan
            </span>
        </div>

        <?php if (empty($fraudList)): ?>
        <div class="empty-fraud">
            <i class="fas fa-shield-check"></i>
            <h3 style="color:#10b981;margin-bottom:8px">Tidak Ada Alert!</h3>
            <p style="color:var(--text-muted)">Tidak ada aktivitas mencurigakan terdeteksi pada periode <?= $months[(int)$filterBulan].' '.$filterTahun ?></p>
        </div>

        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Tanggal & Waktu</th>
                        <th>Tipe Fraud</th>
                        <th class="text-center">Risk Level</th>
                        <th>Detail Teknis</th>
                        <th style="width:140px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fraudList as $f):
                    $flags    = parseFlags($f['keterangan']);
                    [$riskLbl, $riskCls] = riskLevel($flags);
                    $rowCls   = $riskCls === 'danger' ? 'fraud-row-high' : ($riskCls === 'warning' ? 'fraud-row-medium' : 'fraud-row-low');
                    // Ambil akurasi dari keterangan
                    preg_match('/acc:([\d.]+)m/', $f['keterangan'] ?? '', $accMatch);
                    $accVal = $accMatch[1] ?? null;
                ?>
                <tr class="<?= $rowCls ?>">
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($f['nama']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($f['nik']) ?></div>
                        <?php if ($f['karyawan_status'] === 'nonaktif'): ?>
                        <span style="font-size:10px;background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:4px;font-weight:600">DIBLOKIR</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= tglIndonesia($f['tanggal'], 'short') ?></div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-muted)">
                            <?= $f['waktu_masuk'] ? date('H:i:s', strtotime($f['waktu_masuk'])) : '-' ?>
                        </div>
                    </td>
                    <td>
                        <?php foreach ($flags as $flag):
                            [$lbl, $cls, $ico] = flagLabel($flag);
                        ?>
                        <span class="flag-badge <?= $cls ?>">
                            <i class="fas <?= $ico ?>"></i> <?= $lbl ?>
                        </span>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-center">
                        <span class="risk-badge <?= $riskCls ?>">
                            <i class="fas fa-<?= $riskCls==='danger'?'circle-exclamation':($riskCls==='warning'?'triangle-exclamation':'info-circle') ?>"></i>
                            <?= $riskLbl ?>
                        </span>
                    </td>
                    <td style="font-size:11.5px">
                        <?php if ($f['lat_masuk']): ?>
                        <div style="color:var(--text-muted);font-family:'JetBrains Mono',monospace">
                            <?= number_format($f['lat_masuk'],5) ?>, <?= number_format($f['lng_masuk'],5) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($accVal): ?>
                        <div style="color:<?= $accVal>60?'#dc2626':'#d97706' ?>">
                            <i class="fas fa-signal"></i> Akurasi ±<?= $accVal ?>m
                        </div>
                        <?php endif; ?>
                        <div style="color:var(--text-muted)"><?= htmlspecialchars($f['ip_address'] ?? '') ?></div>
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <!-- Tandai Aman -->
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_safe">
                                <input type="hidden" name="absensi_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm" style="width:100%;background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;font-size:11px;padding:4px 8px">
                                    <i class="fas fa-check"></i> Tandai Aman
                                </button>
                            </form>
                            <!-- Hapus Absensi -->
                            <form method="POST" onsubmit="return confirm('Hapus data absensi ini?')">
                                <input type="hidden" name="action" value="hapus_absensi">
                                <input type="hidden" name="absensi_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-outline" style="width:100%;font-size:11px;padding:4px 8px;color:var(--danger);border-color:var(--danger)">
                                    <i class="fas fa-trash"></i> Hapus Data
                                </button>
                            </form>
                            <?php if ($f['karyawan_status'] === 'aktif'): ?>
                            <!-- Blokir Karyawan -->
                            <form method="POST" onsubmit="return confirm('Blokir akun <?= addslashes($f['nama']) ?>?')">
                                <input type="hidden" name="action" value="blokir">
                                <input type="hidden" name="karyawan_id" value="<?= $f['karyawan_id'] ?>">
                                <button class="btn btn-sm btn-danger" style="width:100%;font-size:11px;padding:4px 8px">
                                    <i class="fas fa-ban"></i> Blokir Akun
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top Fraudster & Panduan -->
<div class="grid-2" style="align-items:start">
    <!-- Top Karyawan -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-ranking-star" style="color:var(--danger)"></i> Top Alert Karyawan</h3>
        </div>
        <div style="padding:0 20px">
            <?php if (empty($topFraud)): ?>
            <p style="padding:20px 0;text-align:center;color:var(--text-muted)">Tidak ada data</p>
            <?php else: foreach ($topFraud as $i => $t):
                $rankColors = ['#ef4444','#f97316','#f59e0b','#84cc16','#94a3b8'];
                $rc = $rankColors[$i] ?? '#94a3b8';
            ?>
            <div class="top-fraudster">
                <div class="rank-badge" style="background:<?= $rc ?>;color:#fff"><?= $i+1 ?></div>
                <div style="flex:1">
                    <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['nama']) ?></div>
                    <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($t['nik']) ?></div>
                </div>
                <div style="text-align:right">
                    <span style="font-size:16px;font-weight:800;color:<?= $rc ?>"><?= $t['total_fraud'] ?></span>
                    <div style="font-size:10px;color:var(--text-muted)">alert</div>
                </div>
                <?php if ($t['status'] === 'nonaktif'): ?>
                <span style="font-size:10px;background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;font-weight:600;margin-left:4px">BLOKIR</span>
                <?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Panduan Flag -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-circle-info" style="color:var(--primary)"></i> Panduan Tipe Alert</h3>
        </div>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">
            <?php
            $panduan = [
                ['fa-location-dot',    'danger',  'Anomali Lokasi',      'Koordinat absen hari ini berbeda >500m dari rata-rata historis karyawan. Kemungkinan absen dari lokasi berbeda atau manipulasi titik GPS.'],
                ['fa-signal',          'warning', 'GPS Tidak Akurat',    'Akurasi GPS >50m saat absen. Bisa karena sinyal lemah, di dalam gedung, atau menggunakan mock GPS yang tidak mengatur akurasi.'],
                ['fa-network-wired',   'info',    'IP Dipakai Bersama',  'Lebih dari 3 karyawan berbeda absen dari alamat IP yang sama dalam 1 hari. Bisa normal (WiFi kantor) atau titipan absen.'],
                ['fa-gauge-high',      'danger',  'Kecepatan Janggal',   'Terdeteksi bergerak >180 km/jam saat absen. Kemungkinan menggunakan GPS spoofing dengan animasi lokasi.'],
                ['fa-map-pin',         'danger',  'Koordinat Palsu',     'Koordinat GPS memiliki presisi desimal <4 angka. GPS asli selalu menghasilkan koordinat dengan 6+ desimal.'],
            ];
            foreach ($panduan as [$ico, $cls, $judul, $desc]):
                $bgMap = ['danger'=>'#fee2e2','warning'=>'#fef3c7','info'=>'#dbeafe'];
                $clrMap= ['danger'=>'#dc2626','warning'=>'#d97706','info'=>'#2563eb'];
            ?>
            <div style="display:flex;gap:10px;align-items:flex-start">
                <div style="width:30px;height:30px;border-radius:8px;background:<?= $bgMap[$cls] ?>;color:<?= $clrMap[$cls] ?>;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:1px">
                    <i class="fas <?= $ico ?>"></i>
                </div>
                <div>
                    <div style="font-weight:700;font-size:13px;color:<?= $clrMap[$cls] ?>;margin-bottom:2px"><?= $judul ?></div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.5"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>