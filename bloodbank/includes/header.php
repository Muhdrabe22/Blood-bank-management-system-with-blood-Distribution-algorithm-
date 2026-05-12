<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Lugga Clinic Blood Bank' ?> | Lugga Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
    :root {
        --red:       #C1121F;
        --red-dark:  #890D14;
        --red-light: #FF4757;
        --red-glow:  rgba(193,18,31,0.18);
        --bg:        #0A0D13;
        --bg2:       #111520;
        --bg3:       #181D2A;
        --card:      #1C2130;
        --card2:     #222843;
        --border:    rgba(255,255,255,0.07);
        --text:      #EEF0F5;
        --text2:     #8B90A4;
        --text3:     #555B6E;
        --success:   #10B981;
        --warning:   #F59E0B;
        --info:      #3B82F6;
        --purple:    #8B5CF6;
        --cyan:      #06B6D4;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family:'DM Sans',sans-serif;
        background:var(--bg);
        color:var(--text);
        min-height:100vh;
        display:flex;
    }

    /* ---- SIDEBAR ---- */
    .sidebar {
        width: 260px;
        height: 100vh;              /* exact viewport height */
        background: var(--bg2);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;     /* brand | scroll-zone | footer stacked */
        position: fixed;
        left: 0; top: 0;
        z-index: 100;
        transition: .3s;
        overflow: hidden;           /* sidebar itself does NOT scroll */
    }

    /* ── Brand: pinned, never moves ── */
    .sidebar-brand {
        padding: 24px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;             /* never shrink */
    }

    /* ── THE SCROLL ZONE ── */
    .sidebar-scroll {
        flex: 1;                    /* takes all remaining height between brand & footer */
        min-height: 0;              /* CRITICAL — without this flex won't shrink & scroll won't engage */
        overflow-y: auto;
        overflow-x: hidden;

        /* Firefox */
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.10) transparent;
    }
    /* Chrome / Edge / Safari */
    .sidebar-scroll::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.10);
        border-radius: 4px;
    }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(193,18,31,0.55);
    }

    /* ── Footer: pinned, never moves ── */
    .sidebar-footer {
        padding: 16px 20px;
        border-top: 1px solid var(--border);
        flex-shrink: 0;             /* never shrink */
    }

    .sidebar-logo {
        width:42px; height:42px;
        background:var(--red);
        border-radius:10px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:20px;
        box-shadow:0 0 20px var(--red-glow);
        flex-shrink:0;
    }
    .sidebar-title { font-family:'Syne',sans-serif; font-size:15px; font-weight:800; line-height:1.2; }
    .sidebar-title span { color:var(--red); }
    .sidebar-subtitle { font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:.1em; margin-top:2px; }

    .sidebar-nav { padding:12px 0; }
    .nav-group { margin-bottom:4px; }
    .nav-label {
        font-size:10px;
        text-transform:uppercase;
        letter-spacing:.12em;
        color:var(--text3);
        padding:10px 20px 6px;
        font-weight:600;
    }
    .nav-item {
        display:flex;
        align-items:center;
        gap:12px;
        padding:10px 20px;
        color:var(--text2);
        text-decoration:none;
        font-size:14px;
        font-weight:500;
        transition:.2s;
        border-left:3px solid transparent;
        position:relative;
    }
    .nav-item:hover { background:var(--bg3); color:var(--text); }
    .nav-item.active {
        background:var(--red-glow);
        color:var(--red-light);
        border-left-color:var(--red);
    }
    .nav-item .nav-icon { width:18px; text-align:center; font-size:15px; }
    .nav-badge {
        margin-left:auto;
        background:var(--red);
        color:#fff;
        font-size:10px;
        padding:2px 6px;
        border-radius:20px;
        font-weight:700;
        min-width:20px;
        text-align:center;
    }
    .nav-badge.warn { background:var(--warning); color:#000; }
    .nav-badge.info { background:var(--info); }

    .user-card {
        display:flex;
        align-items:center;
        gap:10px;
        padding:10px;
        background:var(--bg3);
        border-radius:10px;
        border:1px solid var(--border);
    }
    .user-avatar {
        width:36px; height:36px;
        background:var(--red);
        border-radius:50%;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        font-size:14px;
        flex-shrink:0;
    }
    .user-name { font-size:13px; font-weight:600; }
    .user-role { font-size:11px; color:var(--text3); text-transform:capitalize; }
    .user-logout {
        margin-left:auto;
        color:var(--text3);
        text-decoration:none;
        font-size:16px;
        padding:4px;
        transition:.2s;
    }
    .user-logout:hover { color:var(--red-light); }

    /* ---- MAIN ---- */
    .main {
        margin-left:260px;
        flex:1;
        display:flex;
        flex-direction:column;
        min-height:100vh;
    }
    .topbar {
        background:var(--bg2);
        border-bottom:1px solid var(--border);
        padding:0 28px;
        height:64px;
        display:flex;
        align-items:center;
        gap:16px;
        position:sticky;
        top:0;
        z-index:50;
    }
    .topbar-title { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }
    .topbar-sep { color:var(--text3); }
    .topbar-sub { color:var(--text2); font-size:14px; }
    .topbar-right { margin-left:auto; display:flex; align-items:center; gap:12px; }
    .topbar-btn {
        width:38px; height:38px;
        border:1px solid var(--border);
        background:var(--bg3);
        border-radius:8px;
        color:var(--text2);
        display:flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        text-decoration:none;
        position:relative;
        transition:.2s;
    }
    .topbar-btn:hover { border-color:var(--red); color:var(--red-light); }
    .notif-dot {
        position:absolute;
        top:-3px; right:-3px;
        width:8px; height:8px;
        background:var(--red);
        border-radius:50%;
        border:2px solid var(--bg2);
    }
    .topbar-date { font-size:13px; color:var(--text2); }

    .content { padding:28px; flex:1; }

    /* ---- CARDS ---- */
    .card { background:var(--card); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .card-header {
        padding:18px 22px;
        border-bottom:1px solid var(--border);
        display:flex;
        align-items:center;
        gap:12px;
    }
    .card-header h3 { font-size:16px; font-weight:600; }
    .card-header .card-actions { margin-left:auto; display:flex; gap:8px; }
    .card-body { padding:22px; }

    /* ---- STAT CARDS ---- */
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
    .stat-card {
        background:var(--card);
        border:1px solid var(--border);
        border-radius:14px;
        padding:20px;
        position:relative;
        overflow:hidden;
        transition:.2s;
    }
    .stat-card:hover { border-color:rgba(255,255,255,0.15); transform:translateY(-2px); }
    .stat-card::before {
        content:'';
        position:absolute;
        top:0; right:0;
        width:80px; height:80px;
        background:var(--accent-color, var(--red));
        opacity:.06;
        border-radius:50%;
        transform:translate(20px,-20px);
    }
    .stat-icon {
        width:42px; height:42px;
        background:var(--accent-color, var(--red));
        border-radius:10px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:18px;
        margin-bottom:14px;
        box-shadow:0 0 16px color-mix(in srgb, var(--accent-color,var(--red)) 30%, transparent);
    }
    .stat-value { font-size:32px; font-weight:700; font-family:'Syne',sans-serif; line-height:1; margin-bottom:4px; }
    .stat-label { color:var(--text2); font-size:13px; }
    .stat-change { font-size:12px; margin-top:8px; }
    .stat-change.up { color:var(--success); }
    .stat-change.down { color:var(--red-light); }

    /* ---- TABLES ---- */
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    th {
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.08em;
        color:var(--text3);
        font-weight:600;
        padding:12px 16px;
        text-align:left;
        border-bottom:1px solid var(--border);
        white-space:nowrap;
    }
    td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:14px; color:var(--text2); }
    tr:hover td { background:rgba(255,255,255,.02); color:var(--text); }
    tr:last-child td { border-bottom:none; }

    /* ---- BADGES ---- */
    .badge {
        display:inline-flex;
        align-items:center;
        padding:3px 10px;
        border-radius:20px;
        font-size:11px;
        font-weight:600;
        text-transform:uppercase;
        letter-spacing:.05em;
    }
    .badge-success { background:rgba(16,185,129,.15); color:var(--success); border:1px solid rgba(16,185,129,.3); }
    .badge-danger  { background:rgba(239,68,68,.15);  color:#EF4444;        border:1px solid rgba(239,68,68,.3); }
    .badge-warning { background:rgba(245,158,11,.15); color:var(--warning); border:1px solid rgba(245,158,11,.3); }
    .badge-info    { background:rgba(59,130,246,.15); color:var(--info);    border:1px solid rgba(59,130,246,.3); }
    .badge-secondary{background:rgba(100,116,139,.15);color:#94A3B8;        border:1px solid rgba(100,116,139,.3); }
    .badge-purple  { background:rgba(139,92,246,.15); color:var(--purple);  border:1px solid rgba(139,92,246,.3); }

    .blood-badge {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:40px; height:26px;
        border-radius:6px;
        font-size:12px;
        font-weight:700;
        color:#fff;
        letter-spacing:.02em;
    }

    /* ---- BUTTONS ---- */
    .btn {
        display:inline-flex;
        align-items:center;
        gap:7px;
        padding:9px 16px;
        border-radius:8px;
        font-size:13px;
        font-weight:600;
        cursor:pointer;
        border:none;
        text-decoration:none;
        transition:.2s;
        font-family:'DM Sans',sans-serif;
    }
    .btn-primary   { background:var(--red);     color:#fff; }
    .btn-primary:hover { background:var(--red-dark); box-shadow:0 0 20px var(--red-glow); }
    .btn-secondary { background:var(--bg3);     color:var(--text); border:1px solid var(--border); }
    .btn-secondary:hover { border-color:rgba(255,255,255,.2); }
    .btn-success   { background:var(--success); color:#fff; }
    .btn-warning   { background:var(--warning); color:#000; }
    .btn-info      { background:var(--info);    color:#fff; }
    .btn-sm { padding:6px 12px; font-size:12px; }
    .btn-danger { background:#EF4444; color:#fff; }

    /* ---- FORMS ---- */
    .form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:16px; }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    label { font-size:12px; font-weight:600; color:var(--text2); text-transform:uppercase; letter-spacing:.06em; }
    input, select, textarea {
        background:var(--bg3);
        border:1px solid var(--border);
        border-radius:8px;
        padding:10px 12px;
        color:var(--text);
        font-size:14px;
        font-family:'DM Sans',sans-serif;
        outline:none;
        transition:.2s;
        width:100%;
    }
    input:focus, select:focus, textarea:focus { border-color:var(--red); box-shadow:0 0 0 3px var(--red-glow); }
    select option { background:var(--bg3); }
    textarea { resize:vertical; min-height:80px; }

    /* ---- ALERTS ---- */
    .alert {
        padding:14px 18px;
        border-radius:10px;
        margin-bottom:16px;
        display:flex;
        align-items:center;
        gap:12px;
        font-size:14px;
    }
    .alert-success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3); color:var(--success); }
    .alert-danger  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3);  color:#EF4444; }
    .alert-warning { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); color:var(--warning); }
    .alert-info    { background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.3); color:var(--info); }

    /* ---- TABS ---- */
    .tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:24px; }
    .tab-btn {
        padding:10px 18px;
        font-size:13px;
        font-weight:600;
        color:var(--text2);
        background:none;
        border:none;
        cursor:pointer;
        border-bottom:2px solid transparent;
        transition:.2s;
        font-family:'DM Sans',sans-serif;
        position:relative;
        top:1px;
    }
    .tab-btn.active { color:var(--red-light); border-bottom-color:var(--red); }
    .tab-btn:hover { color:var(--text); }
    .tab-content { display:none; }
    .tab-content.active { display:block; }

    /* ---- MODAL ---- */
    .modal-overlay {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.7);
        z-index:200;
        align-items:center;
        justify-content:center;
    }
    .modal-overlay.open { display:flex; }
    .modal {
        background:var(--card);
        border:1px solid var(--border);
        border-radius:16px;
        width:100%;
        max-width:600px;
        max-height:90vh;
        overflow-y:auto;
        animation:slideUp .3s ease;
    }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    .modal-header {
        padding:20px 24px;
        border-bottom:1px solid var(--border);
        display:flex;
        align-items:center;
        gap:12px;
    }
    .modal-header h3 { font-size:18px; font-weight:700; }
    .modal-close {
        margin-left:auto;
        background:none;
        border:none;
        color:var(--text3);
        font-size:20px;
        cursor:pointer;
        padding:4px;
        transition:.2s;
    }
    .modal-close:hover { color:var(--text); }
    .modal-body { padding:24px; }
    .modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }

    /* ---- PAGINATION ---- */
    .pagination { display:flex; gap:6px; align-items:center; justify-content:center; margin-top:20px; }
    .page-btn {
        padding:7px 13px;
        border:1px solid var(--border);
        background:var(--bg3);
        color:var(--text2);
        border-radius:7px;
        font-size:13px;
        cursor:pointer;
        text-decoration:none;
        transition:.2s;
    }
    .page-btn:hover { border-color:var(--red); color:var(--red-light); }
    .page-btn.active { background:var(--red); color:#fff; border-color:var(--red); }

    /* ---- BLOOD INVENTORY GRID ---- */
    .blood-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; }
    .blood-card {
        background:var(--card2);
        border:1px solid var(--border);
        border-radius:12px;
        padding:16px;
        text-align:center;
        position:relative;
        overflow:hidden;
        transition:.2s;
    }
    .blood-card:hover { transform:translateY(-2px); border-color:rgba(255,255,255,.15); }
    .blood-card.critical { border-color:rgba(239,68,68,.5); }
    .blood-card.low { border-color:rgba(245,158,11,.5); }
    .blood-card.normal { border-color:rgba(16,185,129,.3); }
    .blood-type { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; margin-bottom:4px; }
    .blood-count { font-size:22px; font-weight:700; margin:8px 0; }
    .blood-status-text { font-size:12px; color:var(--text2); }

    /* ---- PROGRESS BARS ---- */
    .progress-wrap { height:6px; background:var(--bg3); border-radius:3px; overflow:hidden; }
    .progress-bar { height:100%; border-radius:3px; transition:.3s; }

    /* ---- SEARCH BAR ---- */
    .search-bar { position:relative; flex:1; max-width:320px; }
    .search-bar input { padding-left:36px; }
    .search-bar .search-icon {
        position:absolute;
        left:12px; top:50%;
        transform:translateY(-50%);
        color:var(--text3);
        font-size:13px;
    }

    /* ---- FILTER BAR ---- */
    .filter-bar {
        display:flex;
        align-items:center;
        gap:12px;
        padding:16px 22px;
        border-bottom:1px solid var(--border);
        flex-wrap:wrap;
    }
    .filter-bar select { width:auto; }

    /* ---- RESPONSIVE ---- */
    @media(max-width:768px) {
        .sidebar { transform:translateX(-100%); }
        .sidebar.open { transform:translateX(0); }
        .main { margin-left:0; }
    }

    /* ---- UTILITY ---- */
    .d-flex { display:flex; }
    .align-center { align-items:center; }
    .gap-2 { gap:8px; }
    .gap-3 { gap:12px; }
    .mb-3 { margin-bottom:12px; }
    .mb-4 { margin-bottom:16px; }
    .mb-6 { margin-bottom:24px; }
    .text-muted { color:var(--text2); font-size:13px; }
    .text-danger { color:#EF4444; }
    .text-success { color:var(--success); }
    .fw-bold { font-weight:700; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; }
    @media(max-width:900px) { .grid-2,.grid-3 { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<?php
$pendingReq    = $db->fetch("SELECT COUNT(*) as n FROM blood_requests WHERE status='Pending'")['n'] ?? 0;
$expiringUnits = $db->fetch("SELECT COUNT(*) as n FROM blood_units WHERE status='Available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)")['n'] ?? 0;
$emergencyReq  = $db->fetch("SELECT COUNT(*) as n FROM blood_requests WHERE urgency='Emergency' AND status='Pending'")['n'] ?? 0;
?>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">

    <!-- Pinned top: never scrolls -->
    <div class="sidebar-brand">
        <div class="sidebar-logo">🩸</div>
        <div>
            <div class="sidebar-title">Lugga <span>Clinic</span></div>
            <div class="sidebar-subtitle">Blood Bank System</div>
        </div>
    </div>

    <!-- Scrollable nav zone only -->
    <div class="sidebar-scroll">
        <nav class="sidebar-nav">

            <div class="nav-group">
                <div class="nav-label">Overview</div>
                <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
                    <i class="fa-solid fa-gauge-high nav-icon"></i> Dashboard
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-label">Donors</div>
                <a href="donors.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='donors.php'?'active':'' ?>">
                    <i class="fa-solid fa-users nav-icon"></i> Donors
                </a>
                <a href="donations.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='donations.php'?'active':'' ?>">
                    <i class="fa-solid fa-droplet nav-icon"></i> Donations
                </a>
                <a href="donor_eligibility.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='donor_eligibility.php'?'active':'' ?>">
                    <i class="fa-solid fa-clipboard-check nav-icon"></i> Eligibility Check
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-label">Blood Inventory</div>
                <a href="inventory.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active':'' ?>">
                    <i class="fa-solid fa-boxes-stacked nav-icon"></i> Blood Units
                </a>
                <a href="screening.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='screening.php'?'active':'' ?>">
                    <i class="fa-solid fa-flask nav-icon"></i> Screening & Tests
                </a>
                <a href="stock_status.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='stock_status.php'?'active':'' ?>">
                    <i class="fa-solid fa-chart-bar nav-icon"></i> Stock Status
                    <?php if($expiringUnits>0): ?><span class="nav-badge warn"><?= $expiringUnits ?></span><?php endif; ?>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-label">Requests & Distribution</div>
                <a href="patients.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='patients.php'?'active':'' ?>">
                    <i class="fa-solid fa-hospital-user nav-icon"></i> Patients
                </a>
                <a href="requests.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='requests.php'?'active':'' ?>">
                    <i class="fa-solid fa-file-medical nav-icon"></i> Blood Requests
                    <?php if($pendingReq>0): ?><span class="nav-badge"><?= $pendingReq ?></span><?php endif; ?>
                </a>
                <a href="distribution.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='distribution.php'?'active':'' ?>">
                    <i class="fa-solid fa-truck-medical nav-icon"></i> Distribution
                    <?php if($emergencyReq>0): ?><span class="nav-badge"><?= $emergencyReq ?></span><?php endif; ?>
                </a>
                <a href="crossmatch.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='crossmatch.php'?'active':'' ?>">
                    <i class="fa-solid fa-vials nav-icon"></i> Crossmatch
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-label">Reports & Analytics</div>
                <a href="reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>">
                    <i class="fa-solid fa-chart-line nav-icon"></i> Reports
                </a>
                <a href="audit_log.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='audit_log.php'?'active':'' ?>">
                    <i class="fa-solid fa-scroll nav-icon"></i> Audit Log
                </a>
            </div>

            <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'],['admin'])): ?>
            <div class="nav-group">
                <div class="nav-label">Administration</div>
                <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='users.php'?'active':'' ?>">
                    <i class="fa-solid fa-user-shield nav-icon"></i> Users & Roles
                </a>
                <a href="settings.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active':'' ?>">
                    <i class="fa-solid fa-gear nav-icon"></i> Settings
                </a>
            </div>
            <?php endif; ?>

        </nav>
    </div><!-- /.sidebar-scroll -->

    <!-- Pinned bottom: never scrolls -->
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name']??'U',0,1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']??'User') ?></div>
                <div class="user-role"><?= str_replace('_',' ',$_SESSION['role']??'') ?></div>
            </div>
            <a href="logout.php" class="user-logout" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>

</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <?php if(isset($pageSubtitle)): ?>
        <span class="topbar-sep">/</span>
        <span class="topbar-sub"><?= $pageSubtitle ?></span>
        <?php endif; ?>
        <div class="topbar-right">
            <span class="topbar-date"><i class="fa-regular fa-calendar"></i> <?= date('d M Y') ?></span>
            <?php if($emergencyReq > 0): ?>
            <a href="requests.php?urgency=Emergency" class="topbar-btn" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.4);color:#EF4444;" title="Emergency Requests">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div class="notif-dot"></div>
            </a>
            <?php endif; ?>
            <a href="notifications.php" class="topbar-btn" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if($pendingReq > 0): ?><div class="notif-dot"></div><?php endif; ?>
            </a>
        </div>
    </div>

    <div class="content">

    <!-- Flash messages -->
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> <?= $_SESSION['warning']; unset($_SESSION['warning']); ?></div>
    <?php endif; ?>