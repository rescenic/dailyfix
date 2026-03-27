</main>

    <footer style="padding: 16px 24px; border-top: 1px solid var(--border); background: var(--surface); text-align: center;">
        <p style="font-size: 12px; color: var(--text-muted);">
            &copy; <?= date('Y') ?> <strong>DailyFix</strong> — Sistem Absensi Digital v<?= APP_VERSION ?>
        </p>
    </footer>
</div>

<script>
// ===== CLOCK =====
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    const el = document.getElementById('headerClock');
    if (el) el.textContent = h + ':' + m + ':' + s;
}
setInterval(updateClock, 1000);
updateClock();

// ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// ===== NAV GROUP COLLAPSIBLE =====
function toggleGroup(header) {
    const isOpen = header.classList.contains('open');
    // Tutup semua grup lain
    document.querySelectorAll('.nav-group-header.open').forEach(h => {
        if (h !== header) {
            h.classList.remove('open');
            h.nextElementSibling.classList.remove('open');
        }
    });
    // Toggle grup ini
    header.classList.toggle('open', !isOpen);
    header.nextElementSibling.classList.toggle('open', !isOpen);
    // Simpan state ke localStorage
    const key = 'nav_open_' + header.textContent.trim().replace(/\s+/g,'_');
    localStorage.setItem(key, !isOpen ? '1' : '0');
}

// Restore state on load (jika tidak ada halaman aktif yang memaksanya terbuka)
document.querySelectorAll('.nav-group-header:not(.has-active)').forEach(header => {
    const key = 'nav_open_' + header.textContent.trim().replace(/\s+/g,'_');
    if (localStorage.getItem(key) === '1') {
        header.classList.add('open');
        header.nextElementSibling.classList.add('open');
    }
});

// ===== AUTO HIDE ALERT =====
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= APP_URL ?>/sw.js')
            .catch(err => console.log('SW:', err));
    });
}
</script>
</body>
</html>