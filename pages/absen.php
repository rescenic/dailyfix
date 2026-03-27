<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Absen Saya';
$activePage = 'absen';
$user       = currentUser();
$db         = getDB();
$today      = date('Y-m-d');

// Ambil semua jadwal aktif, lalu pilih yang sesuai hari ini
$stmtJadwal = $db->prepare("SELECT jk.*, j.nama as jadwal_nama, j.hari_kerja,
    s.id as shift_id, s.nama as shift_nama, s.jam_masuk, s.jam_keluar, s.toleransi_terlambat_detik,
    l.id as lokasi_id, l.nama as lokasi_nama, l.latitude, l.longitude, l.radius_meter
    FROM jadwal_karyawan jk 
    JOIN jadwal j ON j.id = jk.jadwal_id 
    JOIN shift s ON s.id = j.shift_id 
    JOIN lokasi l ON l.id = j.lokasi_id 
    WHERE jk.karyawan_id = ? 
    AND jk.berlaku_dari <= CURDATE() 
    AND (jk.berlaku_sampai IS NULL OR jk.berlaku_sampai >= CURDATE())
    ORDER BY s.jam_masuk ASC");
$stmtJadwal->execute([$user['id']]);
$semuaJadwal = $stmtJadwal->fetchAll();

$hariIni = (int)date('N');
$jadwal  = null;
$isHariKerja = false;
foreach ($semuaJadwal as $j) {
    $hk = json_decode($j['hari_kerja'], true) ?? [];
    if (in_array($hariIni, $hk)) { $jadwal = $j; $isHariKerja = true; break; }
}
// Jika tidak ada yang cocok hari ini, ambil yang pertama saja untuk ditampilkan info shift
$jadwalInfo = $jadwal ?? ($semuaJadwal[0] ?? null);

$stmtToday = $db->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
$stmtToday->execute([$user['id'], $today]);
$absenToday = $stmtToday->fetch();

include __DIR__ . '/../includes/header.php';

?>

<style>
/* Fix Leaflet z-index agar tidak menembus modal */
.leaflet-pane         { z-index: 4 !important; }
.leaflet-control      { z-index: 8 !important; }
.leaflet-top, .leaflet-bottom { z-index: 8 !important; }
.modal-overlay        { z-index: 1000 !important; }

#map {
    width: 100%; height: 300px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    position: relative; z-index: 1;
}
.absen-widget {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, #1e7ab8 100%);
    border-radius: var(--radius); padding: 24px; color: #fff; box-shadow: var(--shadow-lg);
}
.absen-time { font-family:'JetBrains Mono',monospace; font-size:2.6rem; font-weight:700; letter-spacing:2px; line-height:1; margin-bottom:4px; }
.absen-date { font-size:13px; opacity:.7; margin-bottom:16px; }
.loc-status { display:flex; align-items:center; gap:8px; margin-top:14px; padding:10px 12px; background:rgba(255,255,255,.1); border-radius:8px; font-size:13px; }
.loc-dot { width:10px; height:10px; border-radius:50%; background:#f59e0b; flex-shrink:0; box-shadow:0 0 0 3px rgba(245,158,11,.3); animation:pulse 1.5s infinite; }
.loc-dot.ok { background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.3); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
.btn-absen { width:100%; margin-top:14px; padding:14px; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .2s; }
.btn-absen:disabled { opacity:.4; cursor:not-allowed; }
.btn-absen:not(:disabled):hover { transform:translateY(-1px); }
.btn-absen-masuk { background:#10b981; color:#fff; }
.btn-absen-masuk:not(:disabled):hover { background:#059669; }
.btn-absen-keluar { background:#f59e0b; color:#fff; }
.btn-absen-keluar:not(:disabled):hover { background:#d97706; }

/* Kamera */
.camera-wrap { position:relative; background:#000; border-radius:10px; overflow:hidden; aspect-ratio:4/3; width:100%; }
.camera-wrap video { width:100%; height:100%; object-fit:cover; display:block; transform:scaleX(-1); }
.face-guide {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-52%);
    width:140px; height:175px;
    border:2px dashed rgba(255,255,255,.7);
    border-radius:50% 50% 50% 50% / 60% 60% 40% 40%;
    pointer-events:none;
}
.face-guide-label {
    position:absolute; bottom:-22px; left:50%; transform:translateX(-50%);
    font-size:11px; color:rgba(255,255,255,.8); white-space:nowrap;
}
.selfie-step { display:none; }
.selfie-step.active { display:block; }
.modal-absen { max-width:480px; width:96vw; }
</style>

<div class="page-header">
    <h2>Absen Saya</h2>
    <p>Lakukan absensi masuk dan keluar menggunakan GPS &amp; foto wajah</p>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <div>
        <div class="absen-widget" style="margin-bottom:16px">
            <div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Waktu Sekarang</div>
            <div class="absen-time" id="bigClock">--:--:--</div>
            <div class="absen-date"><?= tglIndonesia() ?></div>

            <?php if ($jadwalInfo): ?>
            <div style="padding:10px;background:rgba(255,255,255,.1);border-radius:8px;font-size:13px">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="opacity:.7">Shift</span><span style="font-weight:600"><?= htmlspecialchars($jadwalInfo['shift_nama']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="opacity:.7">Jam Masuk</span>
                    <span style="font-weight:600;font-family:'JetBrains Mono',monospace"><?= substr($jadwalInfo['jam_masuk'],0,5) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="opacity:.7">Jam Keluar</span>
                    <span style="font-weight:600;font-family:'JetBrains Mono',monospace"><?= substr($jadwalInfo['jam_keluar'],0,5) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="opacity:.7">Toleransi</span>
                    <span style="font-weight:600"><?= formatTerlambat($jadwalInfo['toleransi_terlambat_detik']) ?></span>
                </div>
            </div>

            <div class="loc-status" id="locStatus">
                <div class="loc-dot" id="locDot"></div>
                <span id="locText">Mendeteksi lokasi GPS...</span>
            </div>

            <?php if ($isHariKerja): ?>
                <?php if (!$absenToday || !$absenToday['waktu_masuk']): ?>
                <button class="btn-absen btn-absen-masuk" id="btnAbsenMasuk" onclick="startAbsen('masuk')" disabled>
                    <i class="fas fa-fingerprint"></i> Absen Masuk
                </button>
                <?php elseif ($absenToday['waktu_masuk'] && !$absenToday['waktu_keluar']): ?>
                <button class="btn-absen btn-absen-keluar" id="btnAbsenKeluar" onclick="startAbsen('keluar')" disabled>
                    <i class="fas fa-door-open"></i> Absen Keluar
                </button>
                <?php else: ?>
                <div style="margin-top:16px;padding:12px;background:rgba(255,255,255,.15);border-radius:8px;text-align:center;font-size:14px">
                    <i class="fas fa-check-circle" style="color:#00c9a7"></i> Absensi hari ini sudah lengkap
                </div>
                <?php endif; ?>
            <?php else: ?>
            <div style="margin-top:16px;padding:12px;background:rgba(255,255,255,.1);border-radius:8px;text-align:center;font-size:13px;opacity:.8">
                <i class="fas fa-calendar-xmark"></i> Hari ini bukan hari kerja
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="margin-top:16px;padding:12px;background:rgba(255,0,0,.2);border-radius:8px;text-align:center;font-size:13px">
                <i class="fas fa-triangle-exclamation"></i> Tidak ada jadwal aktif. Hubungi admin.
            </div>
            <?php endif; ?>
        </div>

        <?php if ($absenToday): ?>
        <div class="card">
            <div class="card-header"><h3>Status Absen Hari Ini</h3></div>
            <div class="card-body">
                <div style="display:grid;gap:10px">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface2);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <i class="fas fa-sign-in-alt" style="color:var(--success)"></i>
                            <span style="font-size:13px;font-weight:600">Waktu Masuk</span>
                        </div>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:700">
                            <?= $absenToday['waktu_masuk'] ? date('H:i:s', strtotime($absenToday['waktu_masuk'])) : '--:--:--' ?>
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface2);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <i class="fas fa-sign-out-alt" style="color:var(--danger)"></i>
                            <span style="font-size:13px;font-weight:600">Waktu Keluar</span>
                        </div>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:700">
                            <?= $absenToday['waktu_keluar'] ? date('H:i:s', strtotime($absenToday['waktu_keluar'])) : '--:--:--' ?>
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface2);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <i class="fas fa-circle-info" style="color:var(--primary)"></i>
                            <span style="font-size:13px;font-weight:600">Status</span>
                        </div>
                        <?= badgeStatus($absenToday['status_kehadiran']) ?>
                    </div>
                    <?php if ($absenToday['terlambat_detik'] > 0): ?>
                    <div style="padding:10px 14px;background:#fffbeb;border-radius:8px;border-left:3px solid var(--warning);font-size:13px;color:#92400e">
                        <i class="fas fa-clock"></i> Terlambat: <strong><?= formatTerlambat($absenToday['terlambat_detik']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($absenToday['durasi_kerja']): ?>
                    <div style="padding:10px 14px;background:#ecfdf5;border-radius:8px;border-left:3px solid var(--success);font-size:13px;color:#065f46">
                        <i class="fas fa-stopwatch"></i> Durasi Kerja: <strong><?= formatDurasi($absenToday['durasi_kerja']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($absenToday['foto_masuk']) || !empty($absenToday['foto_keluar'])): ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <?php if (!empty($absenToday['foto_masuk'])): ?>
                        <div style="text-align:center">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><i class="fas fa-sign-in-alt"></i> Foto Masuk</div>
                            <img src="<?= htmlspecialchars($absenToday['foto_masuk']) ?>" style="width:100%;border-radius:8px;border:2px solid var(--success)">
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($absenToday['foto_keluar'])): ?>
                        <div style="text-align:center">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><i class="fas fa-sign-out-alt"></i> Foto Keluar</div>
                            <img src="<?= htmlspecialchars($absenToday['foto_keluar']) ?>" style="width:100%;border-radius:8px;border:2px solid var(--warning)">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-map-location-dot" style="color:var(--primary)"></i> Peta Lokasi</h3></div>
            <div class="card-body" style="padding:12px">
                <div id="map"></div>
                <div style="margin-top:10px;font-size:12.5px;color:var(--text-muted);display:grid;gap:4px">
                    <div id="coordInfo"><i class="fas fa-crosshairs"></i> Koordinat Anda: mendeteksi...</div>
                    <div id="distInfo"><i class="fas fa-ruler"></i> Jarak ke lokasi: menghitung...</div>
                    <?php if ($jadwalInfo): ?>
                    <div style="color:var(--primary)"><i class="fas fa-building"></i> <?= htmlspecialchars($jadwalInfo['lokasi_nama']) ?> (radius <?= $jadwalInfo['radius_meter'] ?>m)</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL ABSEN ===== -->
<div class="modal-overlay" id="modalAbsen">
    <div class="modal modal-absen">
        <div class="modal-header">
            <h3 id="modalTitle">Konfirmasi Absen</h3>
            <div class="modal-close" onclick="closeAbsenModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <div class="modal-body" style="padding:16px 20px">

            <!-- Step 1: Info GPS -->
            <div class="selfie-step active" id="step1">
                <div id="gpsInfo" style="display:grid;gap:8px;margin-bottom:16px"></div>
                <div style="text-align:center;padding:16px;background:var(--surface2);border-radius:10px;margin-bottom:14px">
                    <i class="fas fa-camera" style="font-size:2.2rem;color:var(--primary);display:block;margin-bottom:8px"></i>
                    <div style="font-size:14px;font-weight:600;margin-bottom:4px">Verifikasi Wajah Diperlukan</div>
                    <div style="font-size:12px;color:var(--text-muted)">Ambil foto wajah untuk melanjutkan absensi</div>
                </div>
                <button class="btn btn-primary" style="width:100%;padding:12px" onclick="goToStep2()">
                    <i class="fas fa-camera"></i> Buka Kamera &amp; Ambil Foto
                </button>
            </div>

            <!-- Step 2: Kamera -->
            <div class="selfie-step" id="step2">
                <div style="margin-bottom:8px;font-size:13px;color:var(--text-muted);text-align:center">
                    <i class="fas fa-circle-info"></i> Posisikan wajah di dalam panduan oval, lalu klik Ambil Foto
                </div>
                <div class="camera-wrap" id="cameraWrap">
                    <video id="videoEl" autoplay playsinline muted></video>
                    <div class="face-guide"><div class="face-guide-label">Posisikan wajah di sini</div></div>
                </div>
                <div id="cameraError" style="display:none;padding:12px;background:#fef2f2;border-radius:8px;color:#991b1b;font-size:13px;margin-top:8px;text-align:center">
                    <i class="fas fa-video-slash"></i> Kamera tidak dapat diakses. Pastikan izin kamera diaktifkan di browser.
                </div>
                <canvas id="canvasEl" style="display:none"></canvas>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
                    <button class="btn btn-outline" onclick="backToStep1()"><i class="fas fa-arrow-left"></i> Kembali</button>
                    <button class="btn btn-primary" id="btnCapture" onclick="capturePhoto()"><i class="fas fa-camera"></i> Ambil Foto</button>
                </div>
            </div>

            <!-- Step 3: Preview + Submit -->
            <div class="selfie-step" id="step3">
                <div style="margin-bottom:8px;font-size:13px;font-weight:600;text-align:center;color:var(--success)">
                    <i class="fas fa-check-circle"></i> Foto berhasil diambil
                </div>
                <img id="selfiePreview" style="width:100%;border-radius:10px;border:3px solid var(--success);margin-bottom:12px;display:block">
                <div id="finalInfo" style="display:grid;gap:8px;margin-bottom:14px"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <button class="btn btn-outline" onclick="retakePhoto()"><i class="fas fa-rotate-left"></i> Ulangi Foto</button>
                    <button class="btn btn-primary" id="btnSubmit" onclick="submitAbsen()">
                        <i class="fas fa-check"></i> Konfirmasi Absen
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let myLat = null, myLng = null;
let map, myMarker;
let absenType = null, photoDataURL = null, cameraStream = null;

<?php if ($jadwalInfo): ?>
const officeLat = <?= $jadwalInfo['latitude'] ?>;
const officeLng = <?= $jadwalInfo['longitude'] ?>;
const officeRadius = <?= $jadwalInfo['radius_meter'] ?>;
<?php else: ?>
const officeLat = -6.2088, officeLng = 106.8456, officeRadius = 100;
<?php endif; ?>

map = L.map('map').setView([officeLat, officeLng], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([officeLat, officeLng], {
    icon: L.divIcon({ html: '<div style="background:#0f4c81;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap">📍 Kantor</div>', className: '' })
}).addTo(map);
L.circle([officeLat, officeLng], { radius: officeRadius, color:'#0f4c81', fillColor:'#0f4c81', fillOpacity:.1 }).addTo(map);

setInterval(() => {
    const n = new Date();
    document.getElementById('bigClock').textContent =
        String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
}, 1000);

function haversine(lat1,lng1,lat2,lng2) {
    const R=6371000, dL=(lat2-lat1)*Math.PI/180, dG=(lng2-lng1)*Math.PI/180;
    const a=Math.sin(dL/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dG/2)**2;
    return Math.round(R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a)));
}

let myAccuracy = 9999, mySpeed = null, myGpsUpdatedAt = 0;
let gpsWatchId = null;

function updateGPS(pos) {
    myLat          = pos.coords.latitude;
    myLng          = pos.coords.longitude;
    myAccuracy     = pos.coords.accuracy ?? 9999;
    mySpeed        = pos.coords.speed;
    myGpsUpdatedAt = Date.now();

    const jarak = haversine(myLat, myLng, officeLat, officeLng);
    const dalam = jarak <= officeRadius;

    const accColor = myAccuracy < 20 ? '#10b981' : myAccuracy < 60 ? '#f59e0b' : '#ef4444';
    const accIcon  = myAccuracy < 20 ? '🎯' : myAccuracy < 60 ? '📡' : '⚠️';

    document.getElementById('coordInfo').innerHTML =
        `<i class="fas fa-crosshairs"></i> Koordinat: ${myLat.toFixed(6)}, ${myLng.toFixed(6)} ` +
        `<span style="color:${accColor};font-weight:600">${accIcon} ±${Math.round(myAccuracy)}m</span>`;
    document.getElementById('distInfo').innerHTML =
        `<i class="fas fa-ruler"></i> Jarak: <strong>${jarak}m</strong> ${dalam ? '✅ Dalam zona' : '❌ Di luar zona'}`;

    if (myMarker) myMarker.setLatLng([myLat, myLng]);
    else myMarker = L.marker([myLat, myLng], {
        icon: L.divIcon({ html: '<div style="background:#00c9a7;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700">📱 Anda</div>', className: '' })
    }).addTo(map);

    document.getElementById('locDot').classList.toggle('ok', dalam);
    const gpsOk = dalam && myAccuracy <= 150; // sedikit lebih toleran untuk HP

    if (!gpsOk && myAccuracy > 150)
        document.getElementById('locText').textContent = `GPS kurang akurat (±${Math.round(myAccuracy)}m). Pindah ke luar ruangan.`;
    else if (dalam)
        document.getElementById('locText').textContent = `Dalam zona kerja (${jarak}m)`;
    else
        document.getElementById('locText').textContent = `Di luar zona (${jarak}m dari kantor)`;

    const bM = document.getElementById('btnAbsenMasuk');
    const bK = document.getElementById('btnAbsenKeluar');
    if (bM) bM.disabled = !gpsOk;
    if (bK) bK.disabled = !gpsOk;
}

function onGpsError(err) {
    const dot  = document.getElementById('locDot');
    const text = document.getElementById('locText');
    dot.style.background = '#ef4444';
    dot.style.boxShadow  = '0 0 0 3px rgba(239,68,68,.3)';

    if (err.code === 1) {
        // Permission denied — tampilkan panduan cara mengaktifkan
        text.textContent = 'Izin lokasi ditolak.';
        showGpsBanner();
    } else if (err.code === 2) {
        text.textContent = 'GPS tidak tersedia. Aktifkan GPS di perangkat Anda.';
    } else {
        text.textContent = 'Timeout GPS. Pastikan berada di area terbuka.';
        // Coba lagi dengan akurasi lebih rendah
        setTimeout(startGPS, 3000);
    }
}

function startGPS() {
    if (!navigator.geolocation) {
        document.getElementById('locText').textContent = 'Browser tidak mendukung GPS.';
        return;
    }
    // Minta izin dulu secara eksplisit
    navigator.permissions && navigator.permissions.query({name:'geolocation'}).then(result => {
        if (result.state === 'denied') {
            showGpsBanner();
            return;
        }
    }).catch(()=>{});

    if (gpsWatchId) navigator.geolocation.clearWatch(gpsWatchId);
    gpsWatchId = navigator.geolocation.watchPosition(
        updateGPS,
        onGpsError,
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 5000 }
    );
}

function showGpsBanner() {
    // Tampilkan banner panduan aktifkan GPS
    const existing = document.getElementById('gpsBanner');
    if (existing) return;

    const isAndroid = /Android/i.test(navigator.userAgent);
    const isIOS     = /iPhone|iPad/i.test(navigator.userAgent);
    const isChrome  = /Chrome/i.test(navigator.userAgent);

    let panduan = '';
    if (isAndroid && isChrome) {
        panduan = `<ol style="margin:8px 0 0 16px;font-size:12px;line-height:1.8">
            <li>Ketuk ikon 🔒 atau ⓘ di sebelah kiri address bar</li>
            <li>Pilih <b>Izin</b> atau <b>Permissions</b></li>
            <li>Aktifkan <b>Lokasi</b></li>
            <li>Refresh halaman ini</li>
        </ol>`;
    } else if (isIOS) {
        panduan = `<ol style="margin:8px 0 0 16px;font-size:12px;line-height:1.8">
            <li>Buka <b>Pengaturan → Safari</b></li>
            <li>Pilih <b>Lokasi</b> → <b>Izinkan</b></li>
            <li>Refresh halaman ini</li>
        </ol>`;
    } else {
        panduan = `<p style="font-size:12px;margin-top:6px">Aktifkan izin Lokasi di pengaturan browser Anda, lalu refresh halaman.</p>`;
    }

    const banner = document.createElement('div');
    banner.id = 'gpsBanner';
    banner.style.cssText = `
        position:fixed; bottom:0; left:0; right:0; z-index:9999;
        background:#1e293b; color:#fff; padding:16px 20px;
        box-shadow: 0 -4px 24px rgba(0,0,0,.3);
        border-radius:16px 16px 0 0;
        animation: slideUp .3s ease;
    `;
    banner.innerHTML = `
        <style>@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}</style>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <span style="font-weight:700;font-size:14px">
                📍 Izin Lokasi Diperlukan
            </span>
            <button onclick="document.getElementById('gpsBanner').remove()"
                style="background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;line-height:1">
                ✕
            </button>
        </div>
        <div style="font-size:13px;color:rgba(255,255,255,.7);margin-bottom:6px">
            Cara mengaktifkan izin lokasi di browser Anda:
        </div>
        ${panduan}
        <button onclick="location.reload()"
            style="margin-top:14px;width:100%;padding:11px;background:#0f4c81;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">
            🔄 Sudah diaktifkan — Refresh
        </button>
    `;
    document.body.appendChild(banner);
}

// Cek juga apakah akses HTTP (bukan HTTPS) — GPS butuh HTTPS di HP
if (location.protocol === 'http:' && !['localhost','127.0.0.1'].includes(location.hostname)) {
    const warn = document.createElement('div');
    warn.style.cssText = 'background:#fffbeb;border:1px solid #f59e0b;border-left:4px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#92400e';
    warn.innerHTML = '⚠️ <strong>GPS membutuhkan HTTPS.</strong> Akses via <b>http://</b> di HP dapat memblokir GPS. Gunakan HTTPS atau akses via localhost.';
    const mapCard = document.querySelector('.card');
    if (mapCard) mapCard.insertAdjacentElement('beforebegin', warn);
}

startGPS();

function showStep(n) {
    ['step1','step2','step3'].forEach((id,i)=>document.getElementById(id).classList.toggle('active',i+1===n));
}

function buildInfoHTML() {
    const jarak = haversine(myLat,myLng,officeLat,officeLng);
    const n = new Date();
    const jam = String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
    return `
        <div style="padding:10px 14px;background:var(--surface2);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:13px;color:var(--text-muted)"><i class="fas fa-clock"></i> Waktu</span>
            <span style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700">${jam}</span>
        </div>
        <div style="padding:10px 14px;background:var(--surface2);border-radius:8px;display:flex;justify-content:space-between">
            <span style="font-size:13px;color:var(--text-muted)"><i class="fas fa-location-dot"></i> Koordinat</span>
            <span style="font-size:12px;font-family:'JetBrains Mono',monospace">${myLat.toFixed(6)}, ${myLng.toFixed(6)}</span>
        </div>
        <div style="padding:10px 14px;background:var(--surface2);border-radius:8px;display:flex;justify-content:space-between">
            <span style="font-size:13px;color:var(--text-muted)"><i class="fas fa-ruler"></i> Jarak ke Kantor</span>
            <span style="font-weight:700;color:${jarak<=officeRadius?'#16a34a':'#dc2626'}">${jarak}m</span>
        </div>`;
}

function startAbsen(type) {
    if (!myLat||!myLng) { alert('GPS belum terdeteksi. Tunggu sebentar.'); return; }
    absenType = type; photoDataURL = null;
    document.getElementById('modalTitle').textContent = type==='masuk' ? '🟢 Absen Masuk' : '🟡 Absen Keluar';
    const info = buildInfoHTML();
    document.getElementById('gpsInfo').innerHTML  = info;
    document.getElementById('finalInfo').innerHTML = info;
    showStep(1);
    document.getElementById('modalAbsen').classList.add('open');
}

function closeAbsenModal() {
    document.getElementById('modalAbsen').classList.remove('open');
    stopCamera(); showStep(1);
}

async function goToStep2() {
    showStep(2);
    document.getElementById('cameraError').style.display='none';
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:640},height:{ideal:480}},audio:false});
        document.getElementById('videoEl').srcObject = cameraStream;
    } catch(e) {
        document.getElementById('cameraError').style.display='block';
    }
}

function stopCamera() {
    if(cameraStream){ cameraStream.getTracks().forEach(t=>t.stop()); cameraStream=null; }
    const v=document.getElementById('videoEl'); if(v) v.srcObject=null;
}

function backToStep1() { stopCamera(); showStep(1); }

function capturePhoto() {
    const video=document.getElementById('videoEl'), canvas=document.getElementById('canvasEl');
    if(!video.srcObject){ alert('Kamera belum siap.'); return; }

    // Kompres: maksimal 480px lebar agar ukuran file kecil
    const MAX_W = 480;
    const ratio = Math.min(1, MAX_W / (video.videoWidth || 640));
    canvas.width  = Math.round((video.videoWidth  || 640) * ratio);
    canvas.height = Math.round((video.videoHeight || 480) * ratio);

    const ctx = canvas.getContext('2d');
    // Mirror flip agar natural
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Quality 0.65 → ukuran ~30-60KB
    photoDataURL = canvas.toDataURL('image/jpeg', 0.65);

    // Tampilkan ukuran di console untuk debug
    const kb = Math.round(photoDataURL.length * 0.75 / 1024);
    console.log('Foto ukuran:', kb, 'KB');

    document.getElementById('selfiePreview').src = photoDataURL;
    stopCamera();
    showStep(3);
}

function retakePhoto() { photoDataURL=null; goToStep2(); }

function submitAbsen() {
    if(!photoDataURL){ alert('Foto wajah belum diambil.'); return; }
    const btn=document.getElementById('btnSubmit');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    fetch('../api/absen.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            type:     absenType,
            lat:      myLat,
            lng:      myLng,
            foto:     photoDataURL,
            accuracy: myAccuracy,
            gps_age:  myGpsUpdatedAt > 0 ? Math.round((Date.now() - myGpsUpdatedAt) / 1000) : 0,
            speed:    mySpeed
        })
    })
    .then(r=>{
        // Cek apakah response bisa di-parse sebagai JSON
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            // Server mengembalikan HTML (kemungkinan PHP error)
            return r.text().then(txt=>{
                console.error('Server response (non-JSON):', txt.substring(0,500));
                throw new Error('Server error: ' + txt.substring(0,200));
            });
        }
        return r.json();
    })
    .then(data=>{
        closeAbsenModal();
        if(data.success){
            const t=document.createElement('div');
            t.style.cssText='position:fixed;top:80px;right:20px;background:#10b981;color:#fff;padding:14px 20px;border-radius:10px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);font-size:14px;display:flex;align-items:center;gap:8px';
            t.innerHTML='<i class="fas fa-check-circle"></i> '+data.message;
            document.body.appendChild(t);
            if (data.warning) {
                setTimeout(()=>{
                    const w=document.createElement('div');
                    w.style.cssText='position:fixed;top:140px;right:20px;background:#f59e0b;color:#fff;padding:12px 18px;border-radius:10px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);font-size:13px;max-width:300px';
                    w.innerHTML='<i class="fas fa-triangle-exclamation"></i> '+data.warning;
                    document.body.appendChild(w);
                    setTimeout(()=>w.remove(),5000);
                },500);
            }
            setTimeout(()=>{t.remove();location.reload();},2200);
        } else {
            alert('❌ '+(data.message||'Terjadi kesalahan'));
            btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Konfirmasi Absen';
        }
    })
    .catch(err=>{
        console.error('Absen error:', err);
        alert('❌ ' + (err.message || 'Gagal menghubungi server. Cek konsol browser untuk detail.'));
        btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Konfirmasi Absen';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>