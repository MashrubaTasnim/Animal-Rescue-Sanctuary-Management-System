<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

if (isset($_GET['sack_id'])) {
    $sack_id = intval($_GET['sack_id']);
    if ($sack_id !== $_SESSION['user_id']) {
        $conn->query("UPDATE users SET role = 'user' WHERE id = $sack_id");
        header("Location: manage_users.php?view=admins&msg=demoted"); exit();
    }
}

if (isset($_GET['restrict_id'])) {
    $restrict_id = intval($_GET['restrict_id']);
    if ($restrict_id !== $_SESSION['user_id']) {
        $conn->query("UPDATE users SET status = 'restricted' WHERE id = $restrict_id");
        header("Location: manage_users.php?msg=restricted"); exit();
    }
}

if (isset($_GET['unrestrict_id'])) {
    $unrestrict_id = intval($_GET['unrestrict_id']);
    $conn->query("UPDATE users SET status = 'active' WHERE id = $unrestrict_id");
    header("Location: manage_users.php?msg=activated"); exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id !== $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id = $delete_id");
        header("Location: manage_users.php?msg=deleted"); exit();
    }
}

// ── VIEW & FILTER PARAMS ──────────────────────────────────────────────────────
$view          = $_GET['view']          ?? 'admins';
$search        = trim($_GET['search']   ?? '');
$filter_status = $_GET['filter_status'] ?? '';

// ── HELPER ────────────────────────────────────────────────────────────────────
function esc($conn, $v) { return $conn->real_escape_string($v); }

// ── ROLE MAP ──────────────────────────────────────────────────────────────────
$role_map = [
    'admins'   => 'admin',
    'vets'     => 'vet',
    'rescuers' => 'rescuer',
    'users'    => 'user',
];
$role = ($view !== 'leaderboard') ? ($role_map[$view] ?? 'admin') : 'admin';

// ── SIDEBAR COUNTS (unfiltered) ───────────────────────────────────────────────
$cnt_admins   = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'"   )->fetch_assoc()['c'];
$cnt_vets     = $conn->query("SELECT COUNT(*) c FROM users WHERE role='vet'"     )->fetch_assoc()['c'];
$cnt_rescuers = $conn->query("SELECT COUNT(*) c FROM users WHERE role='rescuer'" )->fetch_assoc()['c'];
$cnt_users    = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'"    )->fetch_assoc()['c'];

// ── LEADERBOARD DATA ─────────────────────────────────────────────────────────
if ($view === 'leaderboard') {
    $lb_filter = $_GET['lb_filter'] ?? 'all';

    $lb_data = $conn->query("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.profile_image,
            u.role,
            u.created_at,

            /* SOS reports — any logged-in user can report */
            (SELECT COUNT(*) FROM rescues WHERE user_id = u.id) AS sos_reports,

            /* Rescuer: resolved rescues they were assigned to */
            (SELECT COUNT(*) FROM rescues WHERE assigned_rescuer = u.id AND status = 'Resolved') AS rescues_resolved,

            /* Vet: cases currently assigned */
            (SELECT COUNT(*) FROM rescues WHERE assigned_vet = u.id AND status = 'Assigned to Vet') AS vet_active,

            /* Vet: cases they cleared */
            (SELECT COUNT(*) FROM rescues WHERE assigned_vet = u.id AND status = 'Vet Cleared') AS vet_cleared,

            /* Adoptions — regular users */
            (SELECT COUNT(*) FROM adoption_requests WHERE user_id = u.id) AS adoptions,

            /* Donations — any user */
            (SELECT COUNT(*) FROM donations WHERE user_id = u.id AND status = 'completed') AS donations_count,
            (SELECT COALESCE(SUM(amount),0) FROM donations WHERE user_id = u.id AND status = 'completed') AS donations_total,

            /* Events — any user */
            (SELECT COUNT(*) FROM event_interests WHERE user_id = u.id) AS events_joined,

            /* Job applications — rescuers/vets applied via vacancies */
            (SELECT COUNT(*) FROM job_applications WHERE user_id = u.id) AS job_apps,

            /* Surrenders — regular users surrendering animals */
            (SELECT COUNT(*) FROM surrender_requests WHERE user_id = u.id) AS surrenders,

            /* Sponsorships — any user */
            (SELECT COUNT(*) FROM resident_sponsorships WHERE user_id = u.id AND status = 'Active') AS sponsorships,

            /* Activity score — weighted by role-relevant actions */
            (
                (SELECT COUNT(*) FROM rescues WHERE user_id = u.id) * 3 +
                (SELECT COUNT(*) FROM rescues WHERE assigned_rescuer = u.id AND status = 'Resolved') * 5 +
                (SELECT COUNT(*) FROM rescues WHERE assigned_vet = u.id AND status = 'Vet Cleared') * 5 +
                (SELECT COUNT(*) FROM adoption_requests WHERE user_id = u.id) * 4 +
                (SELECT COUNT(*) FROM donations WHERE user_id = u.id AND status = 'completed') * 3 +
                (SELECT COUNT(*) FROM event_interests WHERE user_id = u.id) * 1 +
                (SELECT COUNT(*) FROM job_applications WHERE user_id = u.id) * 2 +
                (SELECT COUNT(*) FROM surrender_requests WHERE user_id = u.id) * 2 +
                (SELECT COUNT(*) FROM resident_sponsorships WHERE user_id = u.id AND status = 'Active') * 4
            ) AS activity_score

        FROM users u
        " . ($lb_filter !== 'all' ? "WHERE u.role = '" . esc($conn, $lb_filter) . "'" : "") . "
        ORDER BY activity_score DESC
        LIMIT 50
    ");
}

// ── FILTERED QUERY ────────────────────────────────────────────────────────────
$where = "WHERE role='".esc($conn,$role)."'";
if ($search)        $where .= " AND (full_name LIKE '%".esc($conn,$search)."%' OR email LIKE '%".esc($conn,$search)."%' OR phone LIKE '%".esc($conn,$search)."%')";
if ($filter_status) $where .= " AND status='".esc($conn,$filter_status)."'";

$result       = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
$result_count = $result->num_rows;

// ── ACTIVE FILTER LABEL (for print header) ────────────────────────────────────
$active_filters = [];
if ($search)        $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_status) $active_filters[] = 'Status: '  . ucfirst(htmlspecialchars($filter_status));
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Directory | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy:   #0a1329;
            --gold:   #B8860B;
            --green:  #198754;
            --blue:   #0d6efd;
            --purple: #6f42c1;
            --red:    #dc3545;
            --orange: #f97316;
        }

        body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }

        /* ── LAYOUT ── */
        .ud-wrapper { display:flex; min-height:calc(100vh - 70px); }
        .ud-sidebar {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .ud-content { flex:1; padding:32px 28px 60px; min-width:0; }

        /* ── SIDEBAR ── */
        .sidebar-head {
            padding:0 20px 20px;
            border-bottom:1px solid rgba(255,255,255,0.08);
            margin-bottom:12px;
        }
        .sidebar-head span {
            font-size:0.58rem; font-weight:800; letter-spacing:2.5px;
            text-transform:uppercase; color:rgba(255,193,7,0.6);
            display:block; margin-bottom:4px;
        }
        .sidebar-head p { color:#fff; font-weight:700; font-size:0.88rem; margin:0; }

        .sidebar-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 20px; text-decoration:none !important;
            color:rgba(255,255,255,0.55); font-size:0.78rem; font-weight:600;
            transition:all 0.2s ease; border-left:3px solid transparent;
        }
        .sidebar-item:hover  { color:#fff; background:rgba(255,255,255,0.05); }
        .sidebar-item.active { color:#fff; background:rgba(255,255,255,0.07); border-left-color:var(--gold); }

        .si-icon {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.8rem; flex-shrink:0; background:rgba(255,255,255,0.06);
        }
        .sidebar-item.active .si-icon { background:rgba(184,134,11,0.2); color:var(--gold); }

        .sidebar-badge {
            margin-left:auto; color:#fff;
            font-size:0.6rem; font-weight:800; padding:2px 7px;
            border-radius:20px; line-height:1.6; flex-shrink:0;
        }

        /* ── PAGE HEADER ── */
        .ud-page-header {
            margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;
        }
        .ud-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .ud-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .ud-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background:#fff; border-radius:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid #e2e8f0; overflow:hidden;
        }
        .glass-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .glass-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            background:#fff; border-radius:14px; padding:14px 18px;
            border:1px solid #e2e8f0; display:flex; gap:10px;
            flex-wrap:wrap; align-items:center; margin-bottom:20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.03);
        }
        .filter-bar input, .filter-bar select {
            padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:10px;
            font-size:0.82rem; font-family:'Inter',sans-serif;
            background:#fff; color:var(--navy); transition:border-color 0.2s;
        }
        .filter-bar input  { flex:1; min-width:200px; }
        .filter-bar input:focus, .filter-bar select:focus { outline:none; border-color:var(--gold); }
        .result-count { font-size:0.72rem; font-weight:700; color:#94a3b8; margin-left:auto; white-space:nowrap; }
        .result-count span { color:var(--navy); }

        /* ── TABLE ── */
        .user-img {
            width:44px; height:44px; border-radius:12px;
            object-fit:cover; background:#eee; flex-shrink:0;
        }
        .user-avatar-fallback {
            width:44px; height:44px; border-radius:12px;
            background:var(--navy); color:var(--gold);
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:1rem; flex-shrink:0;
        }

        .btn-cmd {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px; border:none;
            cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;
        }
        .btn-act {
            width:32px; height:32px;
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:8px; border:1px solid #e2e8f0;
            background:#f8fafc; color:#555;
            text-decoration:none; transition:all 0.2s; margin-left:4px;
            font-size:0.75rem;
        }
        .btn-act:hover               { background:var(--navy); border-color:var(--navy); color:var(--gold); }
        .btn-act.btn-sack:hover      { background:var(--orange); border-color:var(--orange); color:#fff; }
        .btn-act.btn-restrict:hover  { background:#ffc107; border-color:#ffc107; color:#000; }
        .btn-act.btn-unrestrict:hover{ background:var(--green); border-color:var(--green); color:#fff; }
        .btn-act.btn-delete:hover    { background:var(--red); border-color:var(--red); color:#fff; }

        .empty-msg { padding:40px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }

        .status-pill {
            font-size:0.62rem; font-weight:800; text-transform:uppercase;
            padding:4px 10px; border-radius:20px; letter-spacing:0.5px;
        }

        .btn-print {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px;
            border:1px solid #e2e8f0; background:#f8fafc;
            color:#475569; cursor:pointer;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.15s;
        }
        .btn-print:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--navy); }

        /* ── LEADERBOARD ── */
        .lb-header-row {
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px; margin-bottom:22px;
        }
        .lb-stat-strip {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:14px; margin-bottom:24px;
        }
        .lb-stat-card {
            background:#fff; border-radius:14px; padding:16px 18px;
            border:1px solid #e2e8f0; display:flex; align-items:center; gap:14px;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .lb-stat-icon {
            width:40px; height:40px; border-radius:12px;
            display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0;
        }
        .lb-stat-val  { font-size:1.4rem; font-weight:900; color:var(--navy); line-height:1; }
        .lb-stat-lbl  { font-size:0.65rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-top:3px; }

        .lb-filter-tabs { display:flex; gap:6px; flex-wrap:wrap; }
        .lb-tab {
            padding:7px 16px; border-radius:20px; font-size:0.72rem; font-weight:700;
            text-decoration:none; border:1.5px solid #e2e8f0; color:#64748b;
            transition:all 0.2s; text-transform:uppercase; letter-spacing:0.5px;
        }
        .lb-tab:hover  { border-color:var(--navy); color:var(--navy); }
        .lb-tab.active { background:var(--navy); border-color:var(--navy); color:var(--gold); }

        .lb-table { width:100%; border-collapse:collapse; }
        .lb-table thead th {
            background:#f8fafc; font-size:0.63rem; text-transform:uppercase;
            letter-spacing:0.8px; color:#94a3b8; font-weight:800; padding:12px 14px;
        }
        .lb-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
        .lb-table tbody tr:hover { background:#fafbff; }
        .lb-table tbody td { padding:12px 14px; font-size:0.78rem; vertical-align:middle; }

        .lb-rank {
            width:32px; height:32px; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center;
            font-weight:900; font-size:0.78rem; flex-shrink:0;
        }
        .lb-rank.rank-1 { background:linear-gradient(135deg,#ffd700,#f59e0b); color:#fff; font-size:0.9rem; }
        .lb-rank.rank-2 { background:linear-gradient(135deg,#c0c0c0,#94a3b8); color:#fff; }
        .lb-rank.rank-3 { background:linear-gradient(135deg,#cd7f32,#b45309); color:#fff; }
        .lb-rank.rank-other { background:#f1f5f9; color:#64748b; }

        .lb-score-bar-wrap { display:flex; align-items:center; gap:8px; min-width:120px; }
        .lb-score-bar-bg {
            flex:1; height:6px; background:#f1f5f9; border-radius:99px; overflow:hidden;
        }
        .lb-score-bar { height:100%; border-radius:99px; transition:width 0.4s ease; }

        .lb-badge-row { display:flex; flex-wrap:wrap; gap:4px; }
        .lb-badge {
            font-size:0.58rem; font-weight:800; padding:3px 8px;
            border-radius:10px; text-transform:uppercase; letter-spacing:0.4px;
            display:inline-flex; align-items:center; gap:4px; white-space:nowrap;
        }
        .role-pill {
            font-size:0.58rem; font-weight:800; padding:3px 9px; border-radius:10px;
            text-transform:uppercase; letter-spacing:0.5px;
        }

        /* ── PRINT ── */
        .print-only { display:none; }
        @media print {
            .ud-sidebar, nav, .navbar, footer, .btn-act, .btn-print, .btn-cmd,
            .alert, .filter-bar, .lb-filter-tabs, [class*="chat"] { display:none !important; }
            body { background:#fff !important; font-size:11pt; }
            .ud-wrapper { display:block; }
            .ud-content  { padding:0 !important; }
            .glass-card  { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
            .lb-stat-strip { display:none !important; }
            .lb-score-bar-bg { display:none !important; }
            thead { background:#f1f5f9 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            tr { page-break-inside:avoid; }
            .result-count { display:none !important; }
            .lb-header-row p { display:none !important; }
            .print-only { display:block !important; }
            .print-only-header {
                display:flex !important; align-items:center;
                justify-content:space-between; padding-bottom:12px;
                margin-bottom:18px; border-bottom:2px solid #0a1329;
            }
            .print-only-header h3   { font-size:14pt; font-weight:800; color:#0a1329; margin:0; }
            .print-only-header .print-meta { font-size:9pt; color:#64748b; text-align:right; }
            .print-only-header .print-filters { font-size:8pt; color:#94a3b8; margin-top:3px; }
        }
    </style>
</head>
<body>
<?php include 'chat_widget.php'; ?>
<?php include 'navbar.php'; ?>

<div class="ud-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="ud-sidebar">
        <div class="sidebar-head">
            <span>Security & Access</span>
            <p>User Directory</p>
        </div>

        <a href="?view=admins" class="sidebar-item <?= $view=='admins' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-shield-halved"></i></div>
            Admins
            <?php if($cnt_admins > 0): ?>
                <span class="sidebar-badge" style="background:var(--gold);"><?= $cnt_admins ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=vets" class="sidebar-item <?= $view=='vets' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-stethoscope"></i></div>
            Veterinarians
            <?php if($cnt_vets > 0): ?>
                <span class="sidebar-badge" style="background:var(--green);"><?= $cnt_vets ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=rescuers" class="sidebar-item <?= $view=='rescuers' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-person-running"></i></div>
            Rescuers
            <?php if($cnt_rescuers > 0): ?>
                <span class="sidebar-badge" style="background:var(--orange);"><?= $cnt_rescuers ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=users" class="sidebar-item <?= $view=='users' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-users"></i></div>
            General Users
            <?php if($cnt_users > 0): ?>
                <span class="sidebar-badge" style="background:var(--blue);"><?= $cnt_users ?></span>
            <?php endif; ?>
        </a>

        <div style="padding:16px 20px 6px; margin-top:4px; border-top:1px solid rgba(255,255,255,0.08);">
            <span style="font-size:0.55rem; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:rgba(255,193,7,0.45);">Analytics</span>
        </div>
        <a href="?view=leaderboard" class="sidebar-item <?= $view=='leaderboard' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-trophy"></i></div>
            Leaderboard
        </a>

    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="ud-content">

        <?php
        $view_meta = [
            'admins'   => ['color'=>'#B8860B', 'icon'=>'fa-shield-halved',  'title'=>'Administrators', 'can_sack'=>false],
            'vets'     => ['color'=>'#198754', 'icon'=>'fa-stethoscope',    'title'=>'Veterinarians',  'can_sack'=>true],
            'rescuers' => ['color'=>'#f97316', 'icon'=>'fa-person-running', 'title'=>'Rescuers',       'can_sack'=>true],
            'users'    => ['color'=>'#0d6efd', 'icon'=>'fa-users',          'title'=>'General Users',  'can_sack'=>false],
            'leaderboard' => ['color'=>'#B8860B', 'icon'=>'fa-trophy', 'title'=>'Leaderboard', 'can_sack'=>false],
        ];
        $meta = $view_meta[$view] ?? $view_meta['admins'];
        ?>

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; User Directory: <?= $meta['title'] ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Security & Access &rsaquo; <?= $meta['title'] ?></div>
                    <?php if($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <?php if ($view === 'leaderboard'): ?>

        <!-- ════════════ LEADERBOARD VIEW ════════════ -->
        <?php
        $lb_filter = $_GET['lb_filter'] ?? 'all';
        $lb_rows = [];
        while ($r = $lb_data->fetch_assoc()) $lb_rows[] = $r;
        $max_score = !empty($lb_rows) ? max(array_column($lb_rows, 'activity_score')) : 1;
        $total_users_lb = count($lb_rows);

        // Aggregate stats
        $total_sos      = array_sum(array_column($lb_rows, 'sos_reports'));
        $total_rescues  = array_sum(array_column($lb_rows, 'rescues_resolved'));
        $total_adopt    = array_sum(array_column($lb_rows, 'adoptions'));
        $total_don      = array_sum(array_column($lb_rows, 'donations_count'));
        $total_events   = array_sum(array_column($lb_rows, 'events_joined'));
        $lb_filter_label = ['all'=>'All Roles','user'=>'Users','rescuer'=>'Rescuers','vet'=>'Vets','admin'=>'Admins'][$lb_filter] ?? 'All Roles';
        ?>

        <!-- PRINT-ONLY LEADERBOARD HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; User Activity Leaderboard</h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Filter: <?= $lb_filter_label ?> &nbsp;|&nbsp; <?= $total_users_lb ?> member<?= $total_users_lb!=1?'s':'' ?></div>
                    <div id="printDateStampLb"></div>
                </div>
            </div>
        </div>

        <div class="lb-header-row">
            <div>
                <div class="ud-accent-bar" style="background:#B8860B;"></div>
                <h5 style="font-weight:800;font-size:1rem;color:var(--navy);margin:0 0 4px;">
                    <i class="fas fa-trophy me-2" style="color:#B8860B;"></i>User Activity Leaderboard
                </h5>
                <p style="font-size:0.75rem;color:#94a3b8;margin:0;">Central activity report — ranked by engagement score</p>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="lb-filter-tabs">
                <?php foreach(['all'=>'All Roles','user'=>'Users','rescuer'=>'Rescuers','vet'=>'Vets','admin'=>'Admins'] as $k=>$lbl): ?>
                    <a href="?view=leaderboard&lb_filter=<?= $k ?>"
                       class="lb-tab <?= $lb_filter===$k?'active':'' ?>"><?= $lbl ?></a>
                <?php endforeach; ?>
                </div>
                <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <!-- STAT STRIP -->
        <div class="lb-stat-strip">
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fas fa-users"></i></div>
                <div><div class="lb-stat-val"><?= $total_users_lb ?></div><div class="lb-stat-lbl">Ranked Members</div></div>
            </div>
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#fee2e2;color:#dc3545;"><i class="fas fa-bell"></i></div>
                <div><div class="lb-stat-val"><?= $total_sos ?></div><div class="lb-stat-lbl">SOS Reports</div></div>
            </div>
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-person-running"></i></div>
                <div><div class="lb-stat-val"><?= $total_rescues ?></div><div class="lb-stat-lbl">Rescues Handled</div></div>
            </div>
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-paw"></i></div>
                <div><div class="lb-stat-val"><?= $total_adopt ?></div><div class="lb-stat-lbl">Adoptions</div></div>
            </div>
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-hand-holding-heart"></i></div>
                <div><div class="lb-stat-val"><?= $total_don ?></div><div class="lb-stat-lbl">Donations</div></div>
            </div>
            <div class="lb-stat-card">
                <div class="lb-stat-icon" style="background:#fce7f3;color:#db2777;"><i class="fas fa-calendar-check"></i></div>
                <div><div class="lb-stat-val"><?= $total_events ?></div><div class="lb-stat-lbl">Events Joined</div></div>
            </div>
        </div>

        <!-- LEADERBOARD TABLE -->
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #B8860B;">
                <i class="fas fa-ranking-star" style="color:#B8860B;"></i>
                <h6>Activity Rankings &mdash; <?= $total_users_lb ?> member<?= $total_users_lb!=1?'s':'' ?></h6>
                <span style="font-size:0.65rem;color:#94a3b8;margin-left:auto;">Score = weighted sum of all activity</span>
            </div>

            <?php if (empty($lb_rows)): ?>
                <div class="empty-msg">
                    <i class="fas fa-trophy d-block mb-2" style="font-size:2rem;color:#cbd5e1;"></i>
                    No activity data found.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="lb-table">
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center;">#</th>
                            <th>Member</th>
                            <th class="text-center">SOS Reports</th>
                            <th class="text-center">Rescuer: Resolved</th>
                            <th class="text-center">Vet: Active / Cleared</th>
                            <th class="text-center">Adoptions</th>
                            <th class="text-center">Surrenders</th>
                            <th class="text-center">Donations</th>
                            <th class="text-center">Events</th>
                            <th class="text-center">Jobs Applied</th>
                            <th class="text-center">Sponsorships</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lb_rows as $i => $r):
                        $rank = $i + 1;
                        $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                        $score = (int)$r['activity_score'];
                        $pct   = $max_score > 0 ? round($score / $max_score * 100) : 0;
                        $barColor = $rank===1 ? '#f59e0b' : ($rank===2 ? '#94a3b8' : ($rank===3 ? '#b45309' : '#6366f1'));
                        $initial = strtoupper(substr($r['full_name'], 0, 1));

                        $roleColors = [
                            'admin'   => ['bg'=>'#fef3c7','color'=>'#92400e'],
                            'vet'     => ['bg'=>'#dcfce7','color'=>'#166534'],
                            'rescuer' => ['bg'=>'#ffedd5','color'=>'#9a3412'],
                            'user'    => ['bg'=>'#dbeafe','color'=>'#1e40af'],
                        ];
                        $rc = $roleColors[$r['role']] ?? ['bg'=>'#f1f5f9','color'=>'#475569'];
                    ?>
                    <tr>
                        <td style="text-align:center;">
                            <span class="lb-rank <?= $rankClass ?>">
                                <?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($r['profile_image'])): ?>
                                    <img src="<?= htmlspecialchars($r['profile_image']) ?>" class="user-img">
                                <?php else: ?>
                                    <div class="user-avatar-fallback"><?= $initial ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:0.83rem;"><?= htmlspecialchars($r['full_name']) ?></div>
                                    <div class="text-muted" style="font-size:0.68rem;"><?= htmlspecialchars($r['email']) ?></div>
                                    <span class="role-pill mt-1 d-inline-block" style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;"><?= ucfirst($r['role']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if ($r['sos_reports'] > 0): ?>
                                <span class="lb-badge" style="background:#fee2e2;color:#dc3545;"><i class="fas fa-bell"></i><?= $r['sos_reports'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <!-- Rescuer: resolved rescues -->
                        <td class="text-center">
                            <?php if ($r['role'] === 'rescuer' && $r['rescues_resolved'] > 0): ?>
                                <span class="lb-badge" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-person-running"></i><?= $r['rescues_resolved'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <!-- Vet: active + cleared -->
                        <td class="text-center">
                            <?php if ($r['role'] === 'vet' && ($r['vet_active'] > 0 || $r['vet_cleared'] > 0)): ?>
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <?php if ($r['vet_active'] > 0): ?>
                                        <span class="lb-badge" style="background:#fef3c7;color:#92400e;" title="Assigned to Vet">
                                            <i class="fas fa-stethoscope"></i><?= $r['vet_active'] ?> active
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($r['vet_cleared'] > 0): ?>
                                        <span class="lb-badge" style="background:#dcfce7;color:#166534;" title="Vet Cleared">
                                            <i class="fas fa-circle-check"></i><?= $r['vet_cleared'] ?> cleared
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['adoptions'] > 0): ?>
                                <span class="lb-badge" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-paw"></i><?= $r['adoptions'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['surrenders'] > 0): ?>
                                <span class="lb-badge" style="background:#fff1f2;color:#be123c;"><i class="fas fa-box-open"></i><?= $r['surrenders'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['donations_count'] > 0): ?>
                                <span class="lb-badge" style="background:#dbeafe;color:#2563eb;" title="Total: ৳<?= number_format($r['donations_total'],2) ?>">
                                    <i class="fas fa-hand-holding-heart"></i><?= $r['donations_count'] ?>
                                </span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['events_joined'] > 0): ?>
                                <span class="lb-badge" style="background:#fce7f3;color:#db2777;"><i class="fas fa-calendar-check"></i><?= $r['events_joined'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['job_apps'] > 0): ?>
                                <span class="lb-badge" style="background:#f0fdf4;color:#15803d;"><i class="fas fa-briefcase"></i><?= $r['job_apps'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['sponsorships'] > 0): ?>
                                <span class="lb-badge" style="background:#fff7ed;color:#c2410c;"><i class="fas fa-heart"></i><?= $r['sponsorships'] ?></span>
                            <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="lb-score-bar-wrap">
                                <div class="lb-score-bar-bg">
                                    <div class="lb-score-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                                </div>
                                <span style="font-weight:900;font-size:0.8rem;color:var(--navy);min-width:30px;text-align:right;"><?= $score ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- SCORING LEGEND -->
            <div style="padding:14px 20px; border-top:1px solid #f1f5f9; background:#fafbff; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <span style="font-size:0.65rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Score weights:</span>
                <?php foreach([
                    ['SOS Report','3 pts','#fee2e2','#dc3545'],
                    ['Rescue Resolved','5 pts','#dcfce7','#16a34a'],
                    ['Vet Cleared','5 pts','#dcfce7','#166534'],
                    ['Adoption','4 pts','#ede9fe','#7c3aed'],
                    ['Surrender','2 pts','#fff1f2','#be123c'],
                    ['Donation','3 pts','#dbeafe','#2563eb'],
                    ['Event Join','1 pt','#fce7f3','#db2777'],
                    ['Job Application','2 pts','#f0fdf4','#15803d'],
                    ['Sponsorship','4 pts','#fff7ed','#c2410c'],
                ] as [$lbl, $pts, $bg, $col]): ?>
                    <span class="lb-badge" style="background:<?= $bg ?>;color:<?= $col ?>;"><?= $lbl ?>: <strong><?= $pts ?></strong></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: /* ════════ EXISTING DIRECTORY VIEWS ════════ */ ?>

        <!-- PAGE HEADER -->
        <div class="ud-page-header">
            <div>
                <div class="ud-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
                <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
                <p>User Directory &rsaquo; <?= $meta['title'] ?></p>
            </div>
            <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
        </div>

        <!-- ALERT -->
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success border-0 rounded-3 shadow-sm mb-4 fw-bold" style="font-size:0.82rem;">
                <i class="fas fa-check-circle me-2"></i>Action completed successfully.
            </div>
        <?php endif; ?>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search name, email, phone...">
            <select name="filter_status">
                <option value="">All Statuses</option>
                <option value="active"     <?= $filter_status==='active'     ?'selected':'' ?>>Active</option>
                <option value="restricted" <?= $filter_status==='restricted' ?'selected':'' ?>>Restricted</option>
            </select>
            <button type="submit" class="btn-cmd text-white" style="background:var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="?view=<?= $view ?>" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
                <i class="fas fa-times"></i> Clear
            </a>
            <div class="result-count">
                <span><?= $result_count ?></span> member<?= $result_count != 1 ? 's' : '' ?>
                <?= $filter_label ? ' &nbsp;<span style="color:#B8860B;">— filtered</span>' : '' ?>
            </div>
        </form>

        <!-- TABLE CARD -->
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $meta['color'] ?>;">
                <i class="fas <?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                <h6><?= $meta['title'] ?> &mdash; <?= $result_count ?> record<?= $result_count != 1 ? 's' : '' ?></h6>
            </div>

            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <thead style="background:#f8fafc; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; font-weight:800;">
                        <tr>
                            <th class="ps-4 py-3">Member</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-msg">
                                    <i class="fas fa-users-slash d-block mb-2" style="font-size:2rem; color:#cbd5e1;"></i>
                                    <?= ($search || $filter_status) ? 'No members match your filter.' : 'No members found in this category.' ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: while($row = $result->fetch_assoc()):
                        $is_self       = ($row['id'] == $_SESSION['user_id']);
                        $is_restricted = (isset($row['status']) && $row['status'] == 'restricted');
                        $img           = !empty($row['profile_image']) ? $row['profile_image'] : null;
                        $initial       = strtoupper(substr($row['full_name'], 0, 1));
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <?php if($img): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" class="user-img">
                                <?php else: ?>
                                    <div class="user-avatar-fallback"><?= $initial ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:0.85rem;">
                                        <?= htmlspecialchars($row['full_name']) ?>
                                        <?php if($is_self): ?>
                                            <span class="badge ms-1" style="background:var(--gold); color:#fff; font-size:0.55rem; border-radius:6px; padding:2px 6px;">YOU</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($row['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.78rem;">
                                <?php if(!empty($row['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($row['phone']) ?>"
                                       class="fw-bold text-dark text-decoration-none d-flex align-items-center gap-1 mb-1"
                                       style="font-size:0.78rem;">
                                        <i class="fas fa-phone" style="font-size:0.65rem;color:#94a3b8;"></i>
                                        <?= htmlspecialchars($row['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="fw-bold text-muted" style="font-size:0.78rem;">—</span>
                                <?php endif; ?>
                                <a href="mailto:<?= htmlspecialchars($row['email']) ?>"
                                   class="text-muted text-decoration-none d-flex align-items-center gap-1"
                                   style="font-size:0.7rem;">
                                    <i class="fas fa-envelope" style="font-size:0.65rem;color:#94a3b8;"></i>
                                    <?= htmlspecialchars($row['email']) ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <?php if($is_restricted): ?>
                                <span class="status-pill" style="background:#fee2e2; color:#dc3545;">
                                    <i class="fas fa-lock me-1"></i>Restricted
                                </span>
                            <?php else: ?>
                                <span class="status-pill" style="background:#dcfce7; color:#16a34a;">
                                    <i class="fas fa-circle me-1" style="font-size:0.5rem;"></i>Active
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.75rem; color:#94a3b8;">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?= date('M d, Y', strtotime($row['created_at'])) ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if(!$is_self): ?>
                                <?php if($is_restricted): ?>
                                    <a href="?unrestrict_id=<?= $row['id'] ?>&view=<?= $view ?>" class="btn-act btn-unrestrict" title="Unrestrict Access">
                                        <i class="fas fa-user-check"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?restrict_id=<?= $row['id'] ?>&view=<?= $view ?>" class="btn-act btn-restrict" title="Restrict Access"
                                       onclick="return confirm('Restrict this user?')">
                                        <i class="fas fa-user-lock"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if($meta['can_sack']): ?>
                                    <a href="?sack_id=<?= $row['id'] ?>&view=<?= $view ?>" class="btn-act btn-sack" title="Demote to User"
                                       onclick="return confirm('Sack this member? They will become a regular user.')">
                                        <i class="fas fa-user-minus"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn-act" title="Edit User">
                                <i class="fas fa-pen"></i>
                            </a>

                            <?php if(!$is_self): ?>
                                <a href="?delete_id=<?= $row['id'] ?>&view=<?= $view ?>" class="btn-act btn-delete" title="Delete User"
                                   onclick="return confirm('Permanently delete this user?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; /* end leaderboard / directory toggle */ ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
<script>
function printPage() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var stamp = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    var el  = document.getElementById('printDateStamp');
    var el2 = document.getElementById('printDateStampLb');
    if (el)  el.textContent  = stamp;
    if (el2) el2.textContent = stamp;
    window.print();
}
(function() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var stamp = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    var el  = document.getElementById('printDateStamp');
    var el2 = document.getElementById('printDateStampLb');
    if (el)  el.textContent  = stamp;
    if (el2) el2.textContent = stamp;
})();
</script>
</body>
</html>