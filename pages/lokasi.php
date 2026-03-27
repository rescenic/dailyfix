<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle  = 'Master Lokasi';
$activePage = 'lokasi';
$user       = currentUser();
$db         = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = sanitize($_POST['nama'] ?? '');
        $alamat   = sanitize($_POST['alamat'] ?? '');
        $lat      = (float)($_POST['latitude'] ?? 0);
        $lng      = (float)($_POST['longitude'] ?? 0);
        $radius   = (int)($_POST['radius_meter'] ?? 100);
        $status   = $_POST['status'] ?? 'aktif';

        if (!$nama || !$lat || !$lng) redirect(APP_URL.'/pages/lokasi.php','Field wajib diisi!','danger');

        if ($id) {
            $db->prepare("UPDATE lokasi SET nama=?,alamat=?,latitude=?,longitude=?,radius_meter=?,status=? WHERE id=? AND perusahaan_id=?")
               ->execute([$nama,$alamat,$lat,$lng,$radius,$status,$id,$user['perusahaan_id']]);
            redirect(APP_URL.'/pages/lokasi.php','Lokasi berhasil diperbarui.','success');
        } else {
            $db->prepare("INSERT INTO lokasi (perusahaan_id,nama,alamat,latitude,longitude,radius_meter,status) VALUES (?,?,?,?,?,?,?)")
               ->execute([$user['perusahaan_id'],$nama,$alamat,$lat,$lng,$radius,$status]);
            redirect(APP_URL.'/pages/lokasi.php','Lokasi berhasil ditambahkan.','success');
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM lokasi WHERE id=? AND perusahaan_id=?")->execute([(int)$_POST['id'],$user['perusahaan_id']]);
        redirect(APP_URL.'/pages/lokasi.php','Lokasi berhasil dihapus.','success');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM lokasi WHERE id=? AND perusahaan_id=?");
    $stmt->execute([(int)$_GET['edit'],$user['perusahaan_id']]);
    $edit = $stmt->fetch();
}

$lokasis = $db->prepare("SELECT * FROM lokasi WHERE perusahaan_id=? ORDER BY nama");
$lokasis->execute([$user['perusahaan_id']]);
$lokasis = $lokasis->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header flex justify-between items-center">
    <div><h2>Master Lokasi</h2><p>Atur titik koordinat dan radius area kerja</p></div>
    <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Tambah Lokasi</button>
</div>

<div class="grid-2" style="margin-bottom:20px;align-items:start">
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Nama Lokasi</th><th>Koordinat</th><th>Radius</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($lokasis)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding:30px">Belum ada lokasi</td></tr>
                    <?php else: foreach ($lokasis as $i => $l): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($l['nama']) ?></strong>
                            <?php if($l['alamat']): ?><div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars(substr($l['alamat'],0,40)).'...' ?></div><?php endif; ?>
                        </td>
                        <td style="font-family:'JetBrains Mono',monospace;font-size:12px">
                            <?= number_format($l['latitude'],6) ?>,<br><?= number_format($l['longitude'],6) ?>
                        </td>
                        <td><?= $l['radius_meter'] ?>m</td>
                        <td><?= badgeStatus($l['status']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-outline btn-sm btn-icon" onclick="showOnMap(<?= $l['latitude'] ?>,<?= $l['longitude'] ?>,<?= $l['radius_meter'] ?>,'<?= addslashes($l['nama']) ?>')" title="Lihat Peta"><i class="fas fa-map"></i></button>
                                <a href="?edit=<?= $l['id'] ?>" class="btn btn-outline btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                                <form method="POST" onsubmit="return confirm('Hapus lokasi ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" id="previewMapWrap">
        <div class="card-header"><h3><i class="fas fa-map" style="color:var(--primary)"></i> Preview Peta</h3></div>
        <div class="card-body" style="padding:12px">
            <div id="mapPreview" style="height:320px;border-radius:8px"></div>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal-overlay <?= $edit?'open':'' ?>" id="modalLokasi">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><?= $edit?'Edit Lokasi':'Tambah Lokasi' ?></h3>
            <div class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Lokasi <span class="req">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($edit['nama']??'') ?>" placeholder="Kantor Pusat..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($edit['alamat']??'') ?></textarea>
                </div>
                <div style="margin-bottom:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px">
                        <label class="form-label" style="margin:0">Titik Koordinat <span class="req">*</span></label>
                        <button type="button" class="btn btn-outline btn-sm" id="btnGunakanLokasi" onclick="gunakanLokasiSekarang()">
                            <i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Sekarang
                        </button>
                    </div>
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Klik pada peta untuk memilih titik koordinat, klik tombol di atas untuk menggunakan GPS Anda, atau isi manual</p>
                    <div id="gpsStatus" style="display:none;font-size:12px;padding:7px 10px;border-radius:6px;margin-bottom:8px"></div>
                    <div id="mapPicker" style="height:260px;border-radius:8px;border:1.5px solid var(--border);margin-bottom:10px"></div>
                </div>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Latitude <span class="req">*</span></label>
                        <input type="number" name="latitude" id="inputLat" class="form-control" step="any" value="<?= $edit['latitude']??'' ?>" placeholder="-6.2088" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude <span class="req">*</span></label>
                        <input type="number" name="longitude" id="inputLng" class="form-control" step="any" value="<?= $edit['longitude']??'' ?>" placeholder="106.8456" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Radius (meter)</label>
                        <input type="number" name="radius_meter" id="inputRadius" class="form-control" value="<?= $edit['radius_meter']??100 ?>" min="10" max="5000">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif" <?= ($edit['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                        <option value="nonaktif" <?= ($edit['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let mapPreview, mapPicker, pickerMarker, pickerCircle;

// Preview map
mapPreview = L.map('mapPreview').setView([-6.2088, 106.8456], 10);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPreview);

<?php foreach($lokasis as $l): ?>
L.marker([<?= $l['latitude'] ?>, <?= $l['longitude'] ?>])
    .addTo(mapPreview)
    .bindPopup('<strong><?= addslashes($l['nama']) ?></strong><br>Radius: <?= $l['radius_meter'] ?>m');
L.circle([<?= $l['latitude'] ?>, <?= $l['longitude'] ?>], {radius: <?= $l['radius_meter'] ?>, color:'#0f4c81', fillOpacity:.1}).addTo(mapPreview);
<?php endforeach; ?>

function showOnMap(lat, lng, radius, nama) {
    mapPreview.setView([lat, lng], 16);
}

function openModal() { 
    document.getElementById('modalLokasi').classList.add('open');
    document.getElementById('previewMapWrap').style.visibility = 'hidden';
    initPickerMap();
}
function closeModal() { 
    document.getElementById('modalLokasi').classList.remove('open');
    document.getElementById('previewMapWrap').style.visibility = 'visible';
    history.replaceState(null,'',window.location.pathname);
}

function initPickerMap() {
    if (mapPicker) { mapPicker.invalidateSize(); return; }
    const initLat = parseFloat(document.getElementById('inputLat').value) || -6.2088;
    const initLng = parseFloat(document.getElementById('inputLng').value) || 106.8456;
    mapPicker = L.map('mapPicker').setView([initLat, initLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPicker);
    if (document.getElementById('inputLat').value) {
        setPickerMarker(initLat, initLng);
    }
    mapPicker.on('click', e => setPickerMarker(e.latlng.lat, e.latlng.lng));
    document.getElementById('inputRadius').addEventListener('input', () => {
        if (pickerCircle) pickerCircle.setRadius(parseInt(document.getElementById('inputRadius').value)||100);
    });
}

function gunakanLokasiSekarang() {
    const btn = document.getElementById('btnGunakanLokasi');
    const status = document.getElementById('gpsStatus');
    
    if (!navigator.geolocation) {
        status.style.display = 'block';
        status.style.background = 'rgba(220,53,69,.1)';
        status.style.color = 'var(--danger)';
        status.innerHTML = '<i class="fas fa-times-circle"></i> Browser Anda tidak mendukung GPS.';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendeteksi lokasi...';
    status.style.display = 'block';
    status.style.background = 'rgba(0,123,255,.08)';
    status.style.color = 'var(--primary)';
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang mendeteksi lokasi GPS Anda...';
    
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);
            
            // Init peta jika belum
            if (!mapPicker) initPickerMap();
            
            setPickerMarker(lat, lng);
            mapPicker.setView([lat, lng], 17);
            
            status.style.background = 'rgba(40,167,69,.1)';
            status.style.color = 'var(--success)';
            status.innerHTML = '<i class="fas fa-check-circle"></i> Lokasi berhasil dideteksi! Akurasi: ±' + acc + ' meter';
            
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Sekarang';
        },
        function(err) {
            let msg = 'Gagal mendapatkan lokasi.';
            if (err.code === 1) msg = 'Izin GPS ditolak. Aktifkan izin lokasi di browser Anda.';
            else if (err.code === 2) msg = 'Lokasi tidak tersedia. Pastikan GPS aktif.';
            else if (err.code === 3) msg = 'Waktu habis. Coba lagi.';
            
            status.style.background = 'rgba(220,53,69,.1)';
            status.style.color = 'var(--danger)';
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + msg;
            
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Sekarang';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function setPickerMarker(lat, lng) {
    document.getElementById('inputLat').value = lat.toFixed(8);
    document.getElementById('inputLng').value = lng.toFixed(8);
    if (pickerMarker) pickerMarker.setLatLng([lat, lng]);
    else pickerMarker = L.marker([lat, lng]).addTo(mapPicker);
    const r = parseInt(document.getElementById('inputRadius').value)||100;
    if (pickerCircle) pickerCircle.setLatLng([lat,lng]).setRadius(r);
    else pickerCircle = L.circle([lat,lng],{radius:r,color:'#0f4c81',fillOpacity:.1}).addTo(mapPicker);
}

<?php if ($edit): ?>
window.addEventListener('load', () => { 
    openModal();
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>