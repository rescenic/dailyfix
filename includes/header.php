<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($pageTitle ?? 'Dashboard') . ' — ' . APP_NAME ?></title>
    <!-- PWA -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#0f4c81">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DailyFix">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary:       #0f4c81;
            --primary-light: #1a6bb5;
            --primary-dark:  #0a3260;
            --accent:        #00c9a7;
            --accent-light:  #e0faf5;
            --danger:        #ef4444;
            --warning:       #f59e0b;
            --success:       #10b981;
            --info:          #3b82f6;
            --bg:            #f0f4f8;
            --surface:       #ffffff;
            --surface2:      #f8fafc;
            --border:        #e2e8f0;
            --text:          #1e293b;
            --text-muted:    #64748b;
            --text-light:    #94a3b8;
            --sidebar-w:     260px;
            --header-h:      64px;
            --radius:        12px;
            --radius-sm:     8px;
            --shadow:        0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
            --shadow-lg:     0 4px 6px rgba(0,0,0,.05), 0 10px 40px rgba(0,0,0,.10);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 15px; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--primary-dark);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            transition: transform .3s ease;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-brand .logo {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--accent), #0ea5e9);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 18px; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,201,167,.3);
        }
        .sidebar-brand .brand-text { color: #fff; }
        .sidebar-brand .brand-text h1 { font-size: 18px; font-weight: 800; letter-spacing: -.3px; }
        .sidebar-brand .brand-text p { font-size: 11px; color: rgba(255,255,255,.5); margin-top: 1px; }
        .sidebar-user {
            margin: 12px;
            padding: 12px;
            background: rgba(255,255,255,.06);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-user .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;
            overflow: hidden;
        }
        .sidebar-user .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-user .user-info { overflow: hidden; }
        .sidebar-user .user-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user .user-role { color: rgba(255,255,255,.5); font-size: 11px; text-transform: capitalize; }
        .sidebar-nav { flex: 1; padding: 8px 10px 10px; overflow-y: auto; }

        /* ── Group / Collapsible ── */
        .nav-group { margin-bottom: 4px; }
        .nav-group-header {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: var(--radius-sm);
            color: rgba(255,255,255,.75); font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all .2s; user-select: none;
            position: relative;
        }
        .nav-group-header:hover { background: rgba(255,255,255,.08); color: #fff; }
        .nav-group-header.has-active { color: #fff; }
        .nav-group-header i.nav-icon { width: 18px; text-align: center; font-size: 14px; flex-shrink: 0; }
        .nav-group-header .chevron {
            margin-left: auto; font-size: 11px; color: rgba(255,255,255,.35);
            transition: transform .25s ease;
        }
        .nav-group-header.open .chevron { transform: rotate(90deg); }
        .nav-group-header .nav-badge-dot {
            width: 7px; height: 7px; border-radius: 50%; background: #ef4444;
            flex-shrink: 0; box-shadow: 0 0 6px rgba(239,68,68,.6);
        }

        /* Submenu */
        .nav-submenu {
            max-height: 0; overflow: hidden;
            transition: max-height .3s ease, opacity .25s ease;
            opacity: 0;
        }
        .nav-submenu.open { max-height: 500px; opacity: 1; }
        .nav-sub-item {
            display: flex; align-items: center; gap: 9px;
            padding: 7px 12px 7px 38px;
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,.55); font-size: 13px; font-weight: 500;
            cursor: pointer; transition: all .2s; margin-bottom: 1px;
            position: relative;
        }
        .nav-sub-item::before {
            content: ''; position: absolute; left: 22px; top: 50%;
            width: 6px; height: 6px; border-radius: 50%;
            background: rgba(255,255,255,.2); transform: translateY(-50%);
            transition: background .2s;
        }
        .nav-sub-item:hover { color: #fff; background: rgba(255,255,255,.07); }
        .nav-sub-item:hover::before { background: var(--accent); }
        .nav-sub-item.active {
            color: #fff; background: rgba(0,201,167,.15);
            border-left: 2px solid var(--accent); padding-left: 36px;
        }
        .nav-sub-item.active::before { background: var(--accent); }
        .nav-sub-item i { width: 16px; text-align: center; font-size: 12px; opacity: .8; }

        /* Direct nav-item (non-group) */
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: var(--radius-sm);
            color: rgba(255,255,255,.65); font-size: 13.5px; font-weight: 500;
            cursor: pointer; transition: all .2s; margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,.08); color: #fff; }
        .nav-item.active { background: linear-gradient(135deg, var(--accent), #0ea5e9); color: #fff; box-shadow: 0 3px 12px rgba(0,201,167,.3); }
        .nav-item i { width: 18px; text-align: center; font-size: 14px; }

        /* Separator */
        .nav-sep { height: 1px; background: rgba(255,255,255,.07); margin: 6px 8px; }
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-footer a {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,.5);
            font-size: 13px; transition: all .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,0,0,.1); color: #fca5a5; }

        /* ===== MAIN CONTENT ===== */
        .main-wrap { margin-left: var(--sidebar-w); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .header {
            height: var(--header-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
            position: sticky; top: 0; z-index: 50;
            box-shadow: 0 1px 0 var(--border);
        }
        .header-left { display: flex; align-items: center; gap: 12px; }
        .btn-toggle {
            width: 36px; height: 36px; border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface2);
            display: none; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted);
            font-size: 16px; transition: all .2s;
        }
        .btn-toggle:hover { background: var(--border); }
        .header-title { font-size: 17px; font-weight: 700; color: var(--text); }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .header-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px; font-weight: 500;
            color: var(--text-muted);
            background: var(--surface2);
            padding: 6px 12px; border-radius: 20px;
            border: 1px solid var(--border);
        }
        .btn-notif {
            width: 36px; height: 36px; border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface2);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted); font-size: 15px;
            transition: all .2s; position: relative;
        }
        .btn-notif:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .content { flex: 1; padding: 24px; }
        .page-header {
            margin-bottom: 24px;
        }
        .page-header h2 { font-size: 22px; font-weight: 800; color: var(--text); }
        .page-header p { color: var(--text-muted); font-size: 14px; margin-top: 3px; }

        /* ===== CARDS ===== */
        .card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--surface2);
        }
        .card-header h3 { font-size: 15px; font-weight: 700; color: var(--text); }
        .card-body { padding: 20px; }

        /* ===== STAT CARDS ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 18px 20px;
            display: flex; align-items: center; gap: 14px;
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-icon.green  { background: #dcfce7; color: #16a34a; }
        .stat-icon.red    { background: #fee2e2; color: #dc2626; }
        .stat-icon.amber  { background: #fef3c7; color: #d97706; }
        .stat-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-icon.teal   { background: #ccfbf1; color: #0d9488; }
        .stat-value { font-size: 26px; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 3px; font-weight: 500; }

        /* ===== TABLES ===== */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th {
            text-align: left; padding: 10px 14px;
            font-size: 11.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            color: var(--text-muted);
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
        }
        td { padding: 11px 14px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--surface2); }

        /* ===== FORMS ===== */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .form-label span.req { color: var(--danger); margin-left: 2px; }
        .form-control, .form-select {
            width: 100%; padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px; font-family: inherit;
            color: var(--text); background: var(--surface);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15,76,129,.12);
        }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .form-row { display: grid; gap: 16px; }
        .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
        .form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px; border-radius: var(--radius-sm);
            font-size: 13.5px; font-weight: 600; font-family: inherit;
            cursor: pointer; border: none; transition: all .2s;
            white-space: nowrap;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-light); box-shadow: 0 4px 12px rgba(15,76,129,.3); }
        .btn-accent { background: var(--accent); color: #fff; }
        .btn-accent:hover { background: #00b898; box-shadow: 0 4px 12px rgba(0,201,167,.3); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline {
            background: transparent; color: var(--text-muted);
            border: 1.5px solid var(--border);
        }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: rgba(15,76,129,.05); }
        .btn-sm { padding: 5px 10px; font-size: 12.5px; }
        .btn-icon { padding: 7px; aspect-ratio: 1; }

        /* ===== BADGE ===== */
        .badge {
            display: inline-flex; align-items: center;
            padding: 3px 9px; border-radius: 99px;
            font-size: 12px; font-weight: 600;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,.4);
            backdrop-filter: blur(2px);
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%; max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp .25s ease;
        }
        .modal.modal-lg { max-width: 720px; }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { font-size: 16px; font-weight: 700; }
        .modal-close {
            width: 30px; height: 30px; border-radius: 6px;
            background: var(--surface2); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted); font-size: 14px;
            transition: all .2s;
        }
        .modal-close:hover { background: #fee2e2; color: var(--danger); border-color: #fecaca; }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 8px;
            background: var(--surface2);
        }

        /* ===== ALERT / FLASH ===== */
        .alert {
            padding: 12px 16px; border-radius: var(--radius-sm);
            display: flex; align-items: flex-start; gap: 10px;
            font-size: 14px; margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success { background: #ecfdf5; border-color: var(--success); color: #065f46; }
        .alert-danger  { background: #fef2f2; border-color: var(--danger); color: #991b1b; }
        .alert-warning { background: #fffbeb; border-color: var(--warning); color: #92400e; }
        .alert-info    { background: #eff6ff; border-color: var(--info); color: #1e40af; }

        /* ===== PAGINATION ===== */
        .pagination {
            display: flex; align-items: center; gap: 4px;
            padding: 14px 20px; border-top: 1px solid var(--border);
        }
        .page-btn {
            min-width: 32px; height: 32px; padding: 0 8px;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text-muted);
            font-size: 13px; font-weight: 500; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .2s;
        }
        .page-btn:hover { border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ===== MAP ===== */
        #map { width: 100%; height: 280px; border-radius: var(--radius-sm); border: 1px solid var(--border); }

        /* ===== ABSEN WIDGET ===== */
        .absen-widget {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, #1565c0 100%);
            border-radius: var(--radius);
            padding: 28px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .absen-widget::after {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
        }
        .absen-widget::before {
            content: '';
            position: absolute; bottom: -50px; left: -20px;
            width: 140px; height: 140px;
            border-radius: 50%;
            background: rgba(0,201,167,.12);
        }
        .absen-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 42px; font-weight: 700; letter-spacing: -1px;
            line-height: 1;
        }
        .absen-date { font-size: 14px; opacity: .8; margin-top: 4px; }
        .btn-absen {
            width: 100%; padding: 14px;
            border-radius: 10px; border: none;
            font-size: 16px; font-weight: 700;
            cursor: pointer; transition: all .3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-family: inherit;
            margin-top: 16px;
        }
        .btn-absen-masuk { background: var(--accent); color: #fff; }
        .btn-absen-masuk:hover { background: #00b898; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,201,167,.4); }
        .btn-absen-keluar { background: #ff6b6b; color: #fff; }
        .btn-absen-keluar:hover { background: #ee5a5a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,107,107,.4); }
        .btn-absen:disabled { opacity: .5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .loc-status {
            display: flex; align-items: center; gap: 8px;
            font-size: 12.5px; opacity: .9;
            background: rgba(255,255,255,.1); border-radius: 6px;
            padding: 8px 12px; margin-top: 12px;
        }
        .loc-dot { width: 8px; height: 8px; border-radius: 50%; background: #fbbf24; animation: pulse 1.5s infinite; }
        .loc-dot.ok { background: var(--accent); animation: none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

        /* ===== SEARCH BAR ===== */
        .search-bar {
            display: flex; align-items: center; gap: 8px;
            background: var(--surface2); border: 1.5px solid var(--border);
            border-radius: var(--radius-sm); padding: 7px 12px;
            transition: border-color .2s;
        }
        .search-bar:focus-within { border-color: var(--primary); }
        .search-bar input {
            border: none; background: transparent; outline: none;
            font-size: 13.5px; font-family: inherit; color: var(--text);
            flex: 1;
        }
        .search-bar i { color: var(--text-muted); font-size: 13px; }

        /* ===== RESPONSIVE ===== */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 99; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .main-wrap { margin-left: 0; }
            .btn-toggle { display: flex; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .content { padding: 14px; }
            .form-row.cols-2, .form-row.cols-3 { grid-template-columns: 1fr; }
            .header { padding: 0 14px; }
            .header-time { display: none; }
            /* Page header stack on mobile */
            .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
            .page-header h2 { font-size: 1.2rem; }
            /* Card & table */
            .card { border-radius: 10px; }
            .modal { width: 96vw !important; max-width: 96vw !important; margin: 10px; }
            .modal-body { padding: 14px !important; }
            /* Buttons */
            .btn { font-size: 13px; padding: 8px 14px; }
            .btn-sm { font-size: 12px; padding: 6px 10px; }
            /* Hide non-essential table columns */
            .hide-mobile { display: none !important; }
            /* Table scroll */
            .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .table-wrap table { min-width: 500px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .absen-time { font-size: 2.2rem; }
            .content { padding: 10px; }
            /* Stack action buttons */
            .page-header > div:last-child { width: 100%; }
            .page-header > div:last-child .btn { flex: 1; justify-content: center; }
            /* Card header stack */
            .card-header { flex-wrap: wrap; gap: 8px; }
        }

        /* ===== UTILITIES ===== */
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .text-sm { font-size: 13px; }
        .text-muted { color: var(--text-muted); }
        .font-bold { font-weight: 700; }
        .text-center { text-align: center; }
        .w-full { width: 100%; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        @media(max-width:768px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }
        /* Hide xs text labels on very small screens */
        @media(max-width:420px) { .hide-xs { display: none !important; } }
    </style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo">D</div>
        <div class="brand-text">
            <h1>DailyFix</h1>
            <p>Absen Digital</p>
        </div>
    </div>

    <?php $user = currentUser(); ?>
    <div class="sidebar-user">
        <div class="avatar">
            <?php if ($user['foto']): ?>
                <img src="<?= UPLOAD_URL . $user['foto'] ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($user['nama']) ?></div>
            <div class="user-role"><?= $user['role'] ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">

        <?php
        $ap = $activePage ?? '';
        $inMaster  = in_array($ap, ['perusahaan','lokasi','shift','jadwal','master_jabatan','master_departemen','karyawan']);
        $inLaporan = in_array($ap, ['rekap_admin','fraud_alert']);
        $inSetting = in_array($ap, ['smtp_gmail']);

        // Hitung fraud alert count
        $fraudCount = 0;
        try {
            $dbH = getDB(); $uH = currentUser();
            $cntF = $dbH->prepare("SELECT COUNT(*) FROM absensi a JOIN karyawan k ON k.id=a.karyawan_id WHERE k.perusahaan_id=? AND a.keterangan LIKE '%[FLAG:%' AND DATE_FORMAT(a.tanggal,'%Y-%m')=?");
            $cntF->execute([$uH['perusahaan_id'], date('Y-m')]);
            $fraudCount = (int)$cntF->fetchColumn();
        } catch(Exception $e) {}
        ?>

        <!-- ── Utama ── -->
        <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $ap==='dashboard'?'active':'' ?>">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= APP_URL ?>/pages/absen.php" class="nav-item <?= $ap==='absen'?'active':'' ?>">
            <i class="fas fa-fingerprint"></i> Absen Saya
        </a>
        <a href="<?= APP_URL ?>/pages/rekap.php" class="nav-item <?= $ap==='rekap'?'active':'' ?>">
            <i class="fas fa-chart-bar"></i> Rekap Absensi
        </a>

        <?php if (in_array($user['role'], ['admin','manager'])): ?>
        <div class="nav-sep"></div>

        <!-- ── Master Data (Collapsible) ── -->
        <div class="nav-group">
            <div class="nav-group-header <?= $inMaster?'has-active open':'' ?>" onclick="toggleGroup(this)">
                <i class="fas fa-database nav-icon"></i>
                Master Data
                <i class="fas fa-chevron-right chevron"></i>
            </div>
            <div class="nav-submenu <?= $inMaster?'open':'' ?>">
                <a href="<?= APP_URL ?>/pages/perusahaan.php" class="nav-sub-item <?= $ap==='perusahaan'?'active':'' ?>">
                    <i class="fas fa-building"></i> Perusahaan
                </a>
                <a href="<?= APP_URL ?>/pages/lokasi.php" class="nav-sub-item <?= $ap==='lokasi'?'active':'' ?>">
                    <i class="fas fa-map-pin"></i> Lokasi
                </a>
                <a href="<?= APP_URL ?>/pages/shift.php" class="nav-sub-item <?= $ap==='shift'?'active':'' ?>">
                    <i class="fas fa-clock"></i> Shift
                </a>
                <a href="<?= APP_URL ?>/pages/jadwal.php" class="nav-sub-item <?= $ap==='jadwal'?'active':'' ?>">
                    <i class="fas fa-calendar-days"></i> Jadwal
                </a>
                <a href="<?= APP_URL ?>/pages/master_jabatan.php" class="nav-sub-item <?= $ap==='master_jabatan'?'active':'' ?>">
                    <i class="fas fa-briefcase"></i> Jabatan
                </a>
                <a href="<?= APP_URL ?>/pages/master_departemen.php" class="nav-sub-item <?= $ap==='master_departemen'?'active':'' ?>">
                    <i class="fas fa-sitemap"></i> Departemen
                </a>
                <a href="<?= APP_URL ?>/pages/karyawan.php" class="nav-sub-item <?= $ap==='karyawan'?'active':'' ?>">
                    <i class="fas fa-users"></i> Data Karyawan
                </a>
            </div>
        </div>

        <!-- ── Laporan (Collapsible) ── -->
        <div class="nav-group">
            <div class="nav-group-header <?= $inLaporan?'has-active open':'' ?>" onclick="toggleGroup(this)">
                <i class="fas fa-chart-column nav-icon"></i>
                Laporan
                <?php if ($fraudCount > 0): ?>
                <div class="nav-badge-dot" title="<?= $fraudCount ?> fraud alert bulan ini"></div>
                <?php endif; ?>
                <i class="fas fa-chevron-right chevron"></i>
            </div>
            <div class="nav-submenu <?= $inLaporan?'open':'' ?>">
                <a href="<?= APP_URL ?>/pages/rekap_admin.php" class="nav-sub-item <?= $ap==='rekap_admin'?'active':'' ?>">
                    <i class="fas fa-file-chart-column"></i> Rekap Karyawan
                </a>
                <a href="<?= APP_URL ?>/pages/fraud_alert.php" class="nav-sub-item <?= $ap==='fraud_alert'?'active':'' ?>">
                    <i class="fas fa-shield-halved" style="color:<?= $ap==='fraud_alert'?'#fff':'#ef4444' ?>"></i>
                    Fraud Alert GPS
                    <?php if ($fraudCount > 0): ?>
                    <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px"><?= $fraudCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- ── Pengaturan (Collapsible) ── -->
        <div class="nav-group">
            <div class="nav-group-header <?= $inSetting?'has-active open':'' ?>" onclick="toggleGroup(this)">
                <i class="fas fa-gear nav-icon"></i>
                Pengaturan
                <i class="fas fa-chevron-right chevron"></i>
            </div>
            <div class="nav-submenu <?= $inSetting?'open':'' ?>">
                <a href="<?= APP_URL ?>/pages/smtp_gmail.php" class="nav-sub-item <?= $ap==='smtp_gmail'?'active':'' ?>">
                    <i class="fas fa-envelope-circle-check"></i> SMTP Gmail
                </a>
            </div>
        </div>

        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/pages/profil.php"><i class="fas fa-user-cog"></i> Profil Saya</a>
        <a href="<?= APP_URL ?>/logout.php"><i class="fas fa-right-from-bracket"></i> Keluar</a>
    </div>
</aside>

<!-- MAIN WRAP -->
<div class="main-wrap">
    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <button class="btn-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <span class="header-title"><?= $pageTitle ?? 'Dashboard' ?></span>
        </div>
        <div class="header-right">
            <div class="header-time" id="headerClock">--:--:--</div>
            <div class="btn-notif" title="Notifikasi">
                <i class="fas fa-bell"></i>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type']==='success' ? 'check-circle' : ($flash['type']==='danger' ? 'triangle-exclamation' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>