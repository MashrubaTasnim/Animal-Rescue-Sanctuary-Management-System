<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// ── VIEW & FILTER PARAMETERS ──────────────────────────────────────────────────
$view             = $_GET['view']             ?? 'sos';
$search           = trim($_GET['search']      ?? '');
$filter_severity  = $_GET['filter_severity']  ?? '';
$filter_rescuer   = $_GET['filter_rescuer']   ?? '';
$filter_vet       = $_GET['filter_vet']       ?? '';

function esc($conn, $v) { return $conn->real_escape_string($v); }

$low_t = (int) setting('triage_low_threshold',    '2');
$mid_t = (int) setting('triage_medium_threshold', '3');

function severityWhere($conn, $filter_severity, $low_t, $mid_t, $alias = 'r') {
    if ($filter_severity === 'high')   return " AND {$alias}.severity_score > " . (int)$mid_t;
    if ($filter_severity === 'medium') return " AND {$alias}.severity_score > " . (int)$low_t  . " AND {$alias}.severity_score <= " . (int)$mid_t;
    if ($filter_severity === 'low')    return " AND {$alias}.severity_score <= " . (int)$low_t;
    return '';
}

function searchWhere($conn, $search, $alias = 'r', $extras = []) {
    if (!$search) return '';
    $s = esc($conn, $search);
    $parts = [
        "{$alias}.species  LIKE '%{$s}%'",
        "{$alias}.location LIKE '%{$s}%'",
    ];
    foreach ($extras as $col) $parts[] = "{$col} LIKE '%{$s}%'";
    return ' AND (' . implode(' OR ', $parts) . ')';
}

$sev_frag = severityWhere($conn, $filter_severity, $low_t, $mid_t);

// ── DATA QUERIES ──────────────────────────────────────────────────────────────
$pending_sos = $conn->query("
    SELECT r.*, u.full_name, u.email, u.phone
    FROM rescues r LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'Pending'
    " . searchWhere($conn, $search, 'r', ['u.full_name','u.email']) . $sev_frag . "
    ORDER BY r.severity_score DESC
");

$clinic_cases = $conn->query("
    SELECT r.*,
        u.full_name, u.email, u.phone,
        COALESCE(vc.name,    r.clinic_name) AS vc_name,
        COALESCE(vc.phone,   '')            AS vc_phone,
        COALESCE(vc.address, '')            AS vc_addr
    FROM rescues r
    LEFT JOIN users u        ON r.user_id   = u.id
    LEFT JOIN vet_clinics vc ON r.clinic_id = vc.id
    WHERE r.status = 'At Clinic'
    " . searchWhere($conn, $search, 'r', ['u.full_name', 'COALESCE(vc.name, r.clinic_name)']) . $sev_frag . "
    ORDER BY r.severity_score DESC
");

$rescuer_frag = $filter_rescuer ? " AND r.assigned_rescuer = " . (int)$filter_rescuer : '';
$in_progress = $conn->query("
    SELECT r.*,
        u_rep.full_name      AS reporter_name,  u_rep.email AS reporter_email, u_rep.phone AS reporter_phone,
        u_res.full_name      AS rescuer_name,   u_res.email AS rescuer_email,  u_res.phone AS rescuer_phone,
        u_res.profile_image  AS rescuer_avatar, u_res.address AS rescuer_address,
        u_res.latitude       AS rescuer_lat,    u_res.longitude AS rescuer_lng,
        u_res.status         AS rescuer_status, u_res.bio AS rescuer_bio
    FROM rescues r
    LEFT JOIN users u_rep ON r.user_id          = u_rep.id
    LEFT JOIN users u_res ON r.assigned_rescuer = u_res.id
    WHERE r.status = 'In Progress' AND r.assigned_rescuer IS NOT NULL
    " . searchWhere($conn, $search, 'r', ['u_rep.full_name','u_res.full_name']) . $sev_frag . $rescuer_frag . "
    ORDER BY r.severity_score DESC, r.created_at ASC
");

$under_review = $conn->query("
    SELECT r.*,
        u_rep.full_name AS reporter_name, u_rep.email AS reporter_email, u_rep.phone AS reporter_phone,
        u_res.full_name AS rescuer_name
    FROM rescues r
    LEFT JOIN users u_rep ON r.user_id          = u_rep.id
    LEFT JOIN users u_res ON r.assigned_rescuer = u_res.id
    WHERE r.status = 'Under Review'
    " . searchWhere($conn, $search, 'r', ['u_rep.full_name','u_res.full_name']) . $sev_frag . "
    ORDER BY r.severity_score DESC
");

// ── NEW: UNDER TREATMENT (Assigned to Vet) ────────────────────────────────────
$treatment_vet_frag = $filter_vet ? " AND r.assigned_vet = " . (int)$filter_vet : '';
$under_treatment = $conn->query("
    SELECT r.*,
        u_rep.full_name     AS reporter_name,  u_rep.email AS reporter_email, u_rep.phone AS reporter_phone,
        u_res.full_name     AS rescuer_name,
        u_vet.full_name     AS vet_name,       u_vet.email AS vet_email,      u_vet.phone AS vet_phone,
        u_vet.profile_image AS vet_avatar,     u_vet.address AS vet_address,  u_vet.bio AS vet_bio
    FROM rescues r
    LEFT JOIN users u_rep ON r.user_id          = u_rep.id
    LEFT JOIN users u_res ON r.assigned_rescuer = u_res.id
    LEFT JOIN users u_vet ON r.assigned_vet     = u_vet.id
    WHERE r.status = 'Assigned to Vet'
    " . searchWhere($conn, $search, 'r', ['u_rep.full_name','u_vet.full_name']) . $sev_frag . $treatment_vet_frag . "
    ORDER BY r.severity_score DESC, r.created_at ASC
");

$vet_frag = $filter_vet ? " AND r.assigned_vet = " . (int)$filter_vet : '';
$final_review = $conn->query("
    SELECT r.*, u_vet.full_name AS vet_name
    FROM rescues r LEFT JOIN users u_vet ON r.assigned_vet = u_vet.id
    WHERE r.status = 'Vet Cleared'
    " . searchWhere($conn, $search, 'r', ['u_vet.full_name']) . $sev_frag . $vet_frag . "
    ORDER BY r.severity_score DESC
");

$rejected_sos = $conn->query("
    SELECT r.*, u.full_name
    FROM rescues r LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'Rejected'
    " . searchWhere($conn, $search, 'r', ['u.full_name']) . $sev_frag . "
    ORDER BY r.severity_score DESC
");

$vets_list = $conn->query("SELECT id, full_name FROM users WHERE role = 'vet'");

$rescuers_dropdown = $conn->query("
    SELECT DISTINCT u.id, u.full_name
    FROM users u
    INNER JOIN rescues r ON r.assigned_rescuer = u.id
    WHERE r.status = 'In Progress'
    ORDER BY u.full_name ASC
");
$vets_dropdown = $conn->query("
    SELECT DISTINCT u.id, u.full_name
    FROM users u
    INNER JOIN rescues r ON r.assigned_vet = u.id
    WHERE r.status IN ('Assigned to Vet', 'Vet Cleared')
    ORDER BY u.full_name ASC
");

// ── COUNTS ────────────────────────────────────────────────────────────────────
$cnt_sos        = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='Pending'")->fetch_assoc()['c'];
$cnt_clinic     = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='At Clinic'")->fetch_assoc()['c'];
$cnt_inprogress = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='In Progress' AND assigned_rescuer IS NOT NULL")->fetch_assoc()['c'];
$cnt_review     = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='Under Review'")->fetch_assoc()['c'];
$cnt_treatment  = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='Assigned to Vet'")->fetch_assoc()['c'];
$cnt_vet        = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='Vet Cleared'")->fetch_assoc()['c'];
$cnt_rejected   = $conn->query("SELECT COUNT(*) c FROM rescues WHERE status='Rejected'")->fetch_assoc()['c'];

$view_result_map = [
    'sos'        => $pending_sos,
    'clinic'     => $clinic_cases,
    'inprogress' => $in_progress,
    'review'     => $under_review,
    'treatment'  => $under_treatment,
    'vet'        => $final_review,
    'rejected'   => $rejected_sos,
];
$result_count = isset($view_result_map[$view]) ? $view_result_map[$view]->num_rows : 0;

$active_filters = [];
if ($search)          $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_severity) $active_filters[] = 'Severity: ' . ucfirst($filter_severity);
if ($filter_rescuer) {
    $rn = $conn->query("SELECT full_name FROM users WHERE id=" . (int)$filter_rescuer);
    if ($rn && $rn->num_rows) $active_filters[] = 'Rescuer: ' . htmlspecialchars($rn->fetch_assoc()['full_name']);
}
if ($filter_vet) {
    $vn = $conn->query("SELECT full_name FROM users WHERE id=" . (int)$filter_vet);
    if ($vn && $vn->num_rows) $active_filters[] = 'Vet: ' . htmlspecialchars($vn->fetch_assoc()['full_name']);
}
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Command HQ | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy:#0a1329; --gold:#B8860B; --sos:#ff4d4d;
            --clinic:#0d6efd; --shelter:#6f42c1; --vet:#198754; --orange:#f97316;
            --teal:#0891b2;
        }
        body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }

        .rcd-wrapper { display:flex; min-height:calc(100vh - 70px); }
        .rcd-sidebar {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .rcd-content { flex:1; padding:32px 28px 60px; min-width:0; }

        .sidebar-head {
            padding:0 20px 20px;
            border-bottom:1px solid rgba(255,255,255,0.08);
            margin-bottom:12px;
        }
        .sidebar-head span {
            font-size:0.58rem; font-weight:800; letter-spacing:2.5px;
            text-transform:uppercase; color:rgba(255,193,7,0.6); display:block; margin-bottom:4px;
        }
        .sidebar-head p { color:#fff; font-weight:700; font-size:0.88rem; margin:0; }

        .sidebar-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 20px; text-decoration:none !important;
            color:rgba(255,255,255,0.55); font-size:0.78rem; font-weight:600;
            transition:all 0.2s ease; border-left:3px solid transparent;
        }
        .sidebar-item:hover { color:#fff; background:rgba(255,255,255,0.05); }
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
        .badge-sos      { background:var(--sos); }
        .badge-clinic   { background:var(--clinic); }
        .badge-shelter  { background:var(--shelter); }
        .badge-vet      { background:var(--vet); }
        .badge-grey     { background:#6c757d; }
        .badge-teal     { background:var(--teal); }

        .rcd-page-header {
            margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
        }
        .rcd-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .rcd-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .rcd-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

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

        .asset-img { width:60px; height:60px; object-fit:cover; border-radius:12px; }
        .btn-cmd   {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer;
            text-decoration:none; display:inline-flex; align-items:center; gap:6px;
            transition:all 0.2s;
        }

        .sev-indicator { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:8px; }
        .sev-high   { background:var(--sos);    box-shadow:0 0 8px var(--sos); }
        .sev-mid    { background:#ff9800;        box-shadow:0 0 8px #ff9800; }
        .sev-low    { background:var(--vet);     box-shadow:0 0 8px var(--vet); }

        .empty-msg { padding:40px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }

        .filter-bar {
            background:#fff; border-radius:14px; padding:14px 18px;
            border:1px solid #e2e8f0; display:flex; gap:10px;
            flex-wrap:wrap; align-items:center; margin-bottom:20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.03);
        }
        .filter-bar input, .filter-bar select {
            padding:9px 13px; border:1.5px solid #e2e8f0;
            border-radius:10px; font-size:0.82rem;
            font-family:'Inter',sans-serif; background:#fff;
            color:var(--navy); transition:border-color 0.2s;
        }
        .filter-bar input  { flex:1; min-width:200px; }
        .filter-bar input:focus, .filter-bar select:focus { outline:none; border-color:var(--gold); }
        .result-count {
            font-size:0.72rem; font-weight:700; color:#94a3b8;
            margin-left:auto; white-space:nowrap;
        }
        .result-count span { color:var(--navy); }

        .btn-print {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px;
            border:1px solid #e2e8f0; background:#f8fafc;
            color:#475569; cursor:pointer;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.15s;
        }
        .btn-print:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--navy); }

        /* ── VET CARD (treatment view) ── */
        .vet-info-chip {
            display:inline-flex; align-items:center; gap:8px;
            background:#f0fdfa; border:1px solid #99f6e4;
            border-radius:12px; padding:8px 12px;
        }
        .vet-avatar {
            width:40px; height:40px; border-radius:50%;
            object-fit:cover; border:2px solid var(--teal); flex-shrink:0;
        }
        .treatment-badge {
            display:inline-flex; align-items:center; gap:5px;
            background:#f0fdfa; border:1px solid #99f6e4;
            border-radius:8px; padding:4px 10px;
            font-size:0.65rem; font-weight:800; color:#0e7490;
            text-transform:uppercase; letter-spacing:0.5px;
        }

        .modal-content  { border-radius:28px !important; border:none; overflow:hidden; }
        .modal-photo-side { background:#000; min-height:500px; display:flex; align-items:center; justify-content:center; }
        .modal-photo-side img { width:100%; height:100%; object-fit:contain; }
        .info-block { background:#f8fafc; border:1px solid #edf2f7; border-radius:16px; padding:16px; margin-bottom:10px; }
        .gold-label { font-size:0.6rem; letter-spacing:2px; font-weight:800; color:var(--gold); text-transform:uppercase; display:block; margin-bottom:6px; }

        .print-only { display:none; }
        @media print {
            .rcd-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print, button,
            .modal, [class*="chat"], .filter-bar { display:none !important; }
            body { background:#fff !important; font-size:11pt; }
            .rcd-wrapper { display:block; }
            .rcd-content  { padding:0 !important; }
            .glass-card   { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
            .asset-img    { width:48px !important; height:48px !important; }
            .sev-indicator { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
            tr { page-break-inside:avoid; }
            .print-only { display:block !important; }
            .print-only-header {
                display:flex !important; align-items:center;
                justify-content:space-between; padding-bottom:12px;
                margin-bottom:18px; border-bottom:2px solid #0a1329;
            }
            .print-only-header h3   { font-size:14pt; font-weight:800; color:#0a1329; margin:0; }
            .print-only-header .print-meta { font-size:9pt; color:#64748b; text-align:right; }
            .print-only-header .print-filters { font-size:8pt; color:#94a3b8; margin-top:3px; }
            .result-count { display:none !important; }
        }
    </style>
</head>
<body>
<?php include 'chat_widget.php'; ?>
<?php include 'navbar.php'; ?>

<div class="rcd-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="rcd-sidebar">
        <div class="sidebar-head">
            <span>Intelligence Bureau</span>
            <p>Rescue Command</p>
        </div>

        <a href="?view=sos" class="sidebar-item <?= $view=='sos' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-bullhorn"></i></div>
            Pending SOS
            <?php if($cnt_sos > 0): ?>
                <span class="sidebar-badge badge-sos"><?= $cnt_sos ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=clinic" class="sidebar-item <?= $view=='clinic' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-hospital"></i></div>
            At Clinics
            <?php if($cnt_clinic > 0): ?>
                <span class="sidebar-badge badge-clinic"><?= $cnt_clinic ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=inprogress" class="sidebar-item <?= $view=='inprogress' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-person-running"></i></div>
            In Progress
            <?php if($cnt_inprogress > 0): ?>
                <span class="sidebar-badge" style="background:#f97316;"><?= $cnt_inprogress ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=review" class="sidebar-item <?= $view=='review' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-shield-dog"></i></div>
            Under Review
            <?php if($cnt_review > 0): ?>
                <span class="sidebar-badge badge-shelter"><?= $cnt_review ?></span>
            <?php endif; ?>
        </a>

        <!-- NEW: Under Treatment -->
        <a href="?view=treatment" class="sidebar-item <?= $view=='treatment' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-stethoscope"></i></div>
            Under Treatment
            <?php if($cnt_treatment > 0): ?>
                <span class="sidebar-badge badge-teal"><?= $cnt_treatment ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=vet" class="sidebar-item <?= $view=='vet' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-check-double"></i></div>
            Vet Cleared
            <?php if($cnt_vet > 0): ?>
                <span class="sidebar-badge badge-vet"><?= $cnt_vet ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=rejected" class="sidebar-item <?= $view=='rejected' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-ban"></i></div>
            Rejected
            <?php if($cnt_rejected > 0): ?>
                <span class="sidebar-badge badge-grey"><?= $cnt_rejected ?></span>
            <?php endif; ?>
        </a>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="rcd-content">

        <?php
        $view_meta = [
            'sos'        => ['color'=>'#ff4d4d',  'icon'=>'fa-bullhorn',       'title'=>'Street SOS Alerts',        'sub'=>'Pending rescue requests awaiting action'],
            'clinic'     => ['color'=>'#0d6efd',  'icon'=>'fa-hospital',       'title'=>'Clinic Admissions',         'sub'=>'Animals currently receiving clinic care'],
            'inprogress' => ['color'=>'#f97316',  'icon'=>'fa-person-running', 'title'=>'In Progress Rescues',       'sub'=>'Active operations currently being handled by rescuers'],
            'review'     => ['color'=>'#6f42c1',  'icon'=>'fa-shield-dog',     'title'=>'Under Review',              'sub'=>'Cases being assessed at the shelter'],
            'treatment'  => ['color'=>'#0891b2',  'icon'=>'fa-stethoscope',    'title'=>'Under Treatment',           'sub'=>'Animals currently receiving veterinary treatment'],
            'vet'        => ['color'=>'#198754',  'icon'=>'fa-check-double',   'title'=>'Vet Cleared',               'sub'=>'Animals cleared and ready for finalization'],
            'rejected'   => ['color'=>'#6c757d',  'icon'=>'fa-ban',            'title'=>'Rejected Requests',         'sub'=>'Cases that were declined'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['sos'];
        ?>

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; Rescue Command: <?= $meta['title'] ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Intelligence Bureau &rsaquo; <?= $meta['title'] ?></div>
                    <?php if($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="rcd-page-header">
            <div>
                <div class="rcd-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
                <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
                <p>Intelligence Bureau &rsaquo; <?= $meta['title'] ?></p>
            </div>
            <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?= $view ?>">

            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="<?php
                    if ($view === 'clinic')       echo 'Search species, clinic name...';
                    elseif ($view === 'inprogress')  echo 'Search species, location, rescuer name...';
                    elseif ($view === 'treatment')   echo 'Search species, location, vet name...';
                    elseif ($view === 'vet')      echo 'Search species, vet name...';
                    elseif ($view === 'rejected') echo 'Search species, reporter name...';
                    else                          echo 'Search species, location, reporter name...';
                ?>">

            <?php if ($view === 'inprogress'): ?>
                <select name="filter_rescuer">
                    <option value="">All Rescuers</option>
                    <?php $rescuers_dropdown->data_seek(0); while($rd = $rescuers_dropdown->fetch_assoc()): ?>
                        <option value="<?= $rd['id'] ?>" <?= $filter_rescuer == $rd['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rd['full_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

            <?php elseif ($view === 'treatment' || $view === 'vet'): ?>
                <select name="filter_vet">
                    <option value="">All Vets</option>
                    <?php $vets_dropdown->data_seek(0); while($vd = $vets_dropdown->fetch_assoc()): ?>
                        <option value="<?= $vd['id'] ?>" <?= $filter_vet == $vd['id'] ? 'selected' : '' ?>>
                            Dr. <?= htmlspecialchars($vd['full_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

            <?php else: ?>
                <select name="filter_severity">
                    <option value="">All Severities</option>
                    <option value="high"   <?= $filter_severity==='high'   ? 'selected':'' ?>>🔴 High</option>
                    <option value="medium" <?= $filter_severity==='medium' ? 'selected':'' ?>>🟡 Medium</option>
                    <option value="low"    <?= $filter_severity==='low'    ? 'selected':'' ?>>🟢 Low</option>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-cmd text-white" style="background:var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="?view=<?= $view ?>" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
                <i class="fas fa-times"></i> Clear
            </a>

            <div class="result-count">
                <span><?= $result_count ?></span> record<?= $result_count != 1 ? 's' : '' ?>
                <?= $filter_label ? ' &nbsp;<span style="color:#B8860B;">— filtered</span>' : '' ?>
            </div>
        </form>

        <!-- GLASS CARD -->
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $meta['color'] ?>;">
                <i class="fas <?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                <h6><?= $meta['title'] ?> &mdash; <?= $result_count ?> Record<?= $result_count != 1 ? 's' : '' ?></h6>
            </div>

            <div class="table-responsive" id="main-table-wrapper">

            <?php
            // ═══ SOS ═══
            if ($view === 'sos'):
                if ($pending_sos->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $pending_sos->data_seek(0); while($row = $pending_sos->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;"><img src="<?= $row['media_path'] ?>" class="asset-img"></td>
                    <td>
                        <div class="fw-bold"><span class="sev-indicator <?= $glow ?>"></span><?= htmlspecialchars($row['species']) ?></div>
                        <small class="text-muted"><i class="fas fa-map-marker-alt me-1 text-warning"></i><?= htmlspecialchars($row['location']) ?></small>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white"         onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                        <button class="btn-cmd bg-success text-white mx-1" onclick="updateAction(<?= $row['id'] ?>, 'Approved', this)">Process</button>
                        <button class="btn-cmd bg-danger text-white"       onclick="updateAction(<?= $row['id'] ?>, 'Rejected', this)">Reject</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_severity) ? 'No SOS alerts match your filter.' : 'No pending SOS alerts.' ?>
                </div>
            <?php endif;

            // ═══ CLINIC ═══
            elseif ($view === 'clinic'):
                if ($clinic_cases->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $clinic_cases->data_seek(0); while($row = $clinic_cases->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;"><img src="<?= $row['media_path'] ?>" class="asset-img"></td>
                    <td>
                        <div class="fw-bold">
                            <span class="sev-indicator <?= $glow ?>"></span>
                            <?= htmlspecialchars($row['species']) ?>
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-clinic-medical me-1 text-primary"></i>
                            <strong><?= htmlspecialchars($row['vc_name'] ?: 'Unknown Clinic') ?></strong>
                        </small>
                        <?php if (!empty($row['vc_addr'])): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-map-marker-alt me-1 text-secondary"></i>
                            <?= htmlspecialchars($row['vc_addr']) ?>
                        </small>
                        <?php endif; ?>
                        <?php if (!empty($row['vc_phone'])): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-phone me-1 text-secondary"></i>
                            <?= htmlspecialchars($row['vc_phone']) ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white"         onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                        <button class="btn-cmd bg-primary text-white mx-1" onclick="updateAction(<?= $row['id'] ?>, 'Approved', this)">Pickup</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_severity) ? 'No clinic cases match your filter.' : 'No cases at clinic right now.' ?>
                </div>
            <?php endif;

            // ═══ IN PROGRESS ═══
            elseif ($view === 'inprogress'):
                if ($in_progress->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $in_progress->data_seek(0); while($row = $in_progress->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                    $avatar = !empty($row['rescuer_avatar'])
                        ? $row['rescuer_avatar']
                        : 'https://ui-avatars.com/api/?name='.urlencode($row['rescuer_name'] ?? 'R').'&background=0a1329&color=f97316&size=64&bold=true';
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;">
                        <img src="<?= htmlspecialchars($row['media_path']) ?>" class="asset-img">
                    </td>
                    <td>
                        <div class="fw-bold">
                            <span class="sev-indicator <?= $glow ?>"></span>
                            <?= htmlspecialchars($row['species']) ?>
                            <?php if($row['breed']): ?>
                                <span class="text-muted fw-normal" style="font-size:0.75rem;"> &middot; <?= htmlspecialchars($row['breed']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1 text-warning"></i><?= htmlspecialchars($row['location']) ?>
                        </small>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= $avatar ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #f97316;flex-shrink:0;">
                            <div>
                                <div style="font-size:0.78rem;font-weight:700;color:#0a1329;">
                                    <?= htmlspecialchars($row['rescuer_name'] ?? '—') ?>
                                </div>
                                <div style="font-size:0.68rem;color:#64748b;">
                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($row['rescuer_phone'] ?? '—') ?>
                                </div>
                                <div style="font-size:0.68rem;color:#64748b;">
                                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($row['rescuer_email'] ?? '—') ?>
                                </div>
                                <?php if(!empty($row['rescuer_address'])): ?>
                                <div style="font-size:0.65rem;color:#94a3b8;">
                                    <i class="fas fa-location-dot me-1"></i><?= htmlspecialchars($row['rescuer_address']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="width:120px;">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:4px 10px;font-size:0.65rem;font-weight:800;color:#c2410c;text-transform:uppercase;letter-spacing:0.5px;">
                            <i class="fas fa-circle-notch fa-spin" style="font-size:0.6rem;"></i> In Progress
                        </span>
                        <?php if($row['created_at']): ?>
                        <div style="font-size:0.63rem;color:#94a3b8;margin-top:4px;">
                            <i class="fas fa-clock me-1"></i><?= date('d M, H:i', strtotime($row['created_at'])) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white" onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_severity) ? 'No in-progress rescues match your filter.' : 'No rescues currently in progress.' ?>
                </div>
            <?php endif;

            // ═══ UNDER REVIEW ═══
            elseif ($view === 'review'):
                if ($under_review->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $under_review->data_seek(0); while($row = $under_review->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;"><img src="<?= $row['media_path'] ?>" class="asset-img"></td>
                    <td>
                        <div class="fw-bold"><span class="sev-indicator <?= $glow ?>"></span><?= htmlspecialchars($row['species']) ?></div>
                        <small class="text-muted"><i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($row['rescuer_name']) ?></small>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white"  onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                        <button class="btn-cmd text-white mx-1"     style="background:var(--clinic)" onclick="openVetModal(<?= $row['id'] ?>, this)">Assign Vet</button>
                        <button class="btn-cmd text-white"          style="background:var(--gold)"   onclick="location.href='finalize_animal.php?id=<?= $row['id'] ?>'">Finalize</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_severity) ? 'No cases under review match your filter.' : 'No cases under review.' ?>
                </div>
            <?php endif;

            // ═══ UNDER TREATMENT (NEW) ═══
            elseif ($view === 'treatment'):
                if ($under_treatment->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $under_treatment->data_seek(0); while($row = $under_treatment->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                    $vet_avatar = !empty($row['vet_avatar'])
                        ? $row['vet_avatar']
                        : 'https://ui-avatars.com/api/?name='.urlencode($row['vet_name'] ?? 'V').'&background=0891b2&color=ffffff&size=64&bold=true';
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <!-- Animal Photo -->
                    <td class="ps-4" style="width:90px;">
                        <img src="<?= htmlspecialchars($row['media_path']) ?>" class="asset-img">
                    </td>

                    <!-- Animal Info -->
                    <td>
                        <div class="fw-bold">
                            <span class="sev-indicator <?= $glow ?>"></span>
                            <?= htmlspecialchars($row['species']) ?>
                            <?php if(!empty($row['breed'])): ?>
                                <span class="text-muted fw-normal" style="font-size:0.75rem;"> &middot; <?= htmlspecialchars($row['breed']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-map-marker-alt me-1 text-warning"></i><?= htmlspecialchars($row['location']) ?>
                        </small>
                        <?php if(!empty($row['reporter_name'])): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($row['reporter_name']) ?>
                        </small>
                        <?php endif; ?>
                    </td>

                    <!-- Vet Info -->
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= $vet_avatar ?>" class="vet-avatar">
                            <div>
                                <div style="font-size:0.78rem;font-weight:700;color:#0a1329;">
                                    Dr. <?= htmlspecialchars($row['vet_name'] ?? '—') ?>
                                </div>
                                <?php if(!empty($row['vet_phone'])): ?>
                                <div style="font-size:0.68rem;color:#64748b;">
                                    <i class="fas fa-phone me-1 text-teal" style="color:#0891b2;"></i>
                                    <?= htmlspecialchars($row['vet_phone']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($row['vet_email'])): ?>
                                <div style="font-size:0.68rem;color:#64748b;">
                                    <i class="fas fa-envelope me-1" style="color:#0891b2;"></i>
                                    <?= htmlspecialchars($row['vet_email']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($row['vet_address'])): ?>
                                <div style="font-size:0.65rem;color:#94a3b8;">
                                    <i class="fas fa-location-dot me-1"></i>
                                    <?= htmlspecialchars($row['vet_address']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Status Badge + Timestamp -->
                    <td style="width:140px;">
                        <span class="treatment-badge">
                            <i class="fas fa-heart-pulse" style="font-size:0.6rem;"></i> Under Treatment
                        </span>
                        <?php if(!empty($row['created_at'])): ?>
                        <div style="font-size:0.63rem;color:#94a3b8;margin-top:4px;">
                            <i class="fas fa-clock me-1"></i><?= date('d M, H:i', strtotime($row['created_at'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($row['vet_notes'])): ?>
                        <div style="font-size:0.62rem;color:#0e7490;margin-top:4px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($row['vet_notes']) ?>">
                            <i class="fas fa-notes-medical me-1"></i><?= htmlspecialchars($row['vet_notes']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white" onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_vet) ? 'No treatment cases match your filter.' : 'No animals currently under treatment.' ?>
                </div>
            <?php endif;

            // ═══ VET CLEARED ═══
            elseif ($view === 'vet'):
                if ($final_review->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $final_review->data_seek(0); while($row = $final_review->fetch_assoc()):
                    $glow = ($row['severity_score'] > $mid_t) ? 'sev-high' : (($row['severity_score'] > $low_t) ? 'sev-mid' : 'sev-low');
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;"><img src="<?= $row['media_path'] ?>" class="asset-img"></td>
                    <td>
                        <div class="fw-bold"><span class="sev-indicator <?= $glow ?>"></span><?= htmlspecialchars($row['species']) ?></div>
                        <small class="text-muted"><i class="fas fa-user-md me-1 text-success"></i>Dr. <?= htmlspecialchars($row['vet_name']) ?></small>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white"         onclick='openModal(<?= json_encode($row) ?>)'>Details</button>
                        <button class="btn-cmd bg-success text-white mx-1" onclick="location.href='finalize_animal.php?id=<?= $row['id'] ?>'">Finalize Final</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_vet) ? 'No vet-cleared cases match your filter.' : 'No cases cleared by vet.' ?>
                </div>
            <?php endif;

            // ═══ REJECTED ═══
            elseif ($view === 'rejected'):
                if ($rejected_sos->num_rows > 0):
            ?>
            <table class="table align-middle m-0 table-hover" id="main-table">
                <tbody>
                <?php $rejected_sos->data_seek(0); while($row = $rejected_sos->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="ps-4" style="width:90px;">
                        <img src="<?= $row['media_path'] ?>" class="asset-img" style="filter:grayscale(100%);">
                    </td>
                    <td>
                        <div class="fw-bold text-muted"><?= htmlspecialchars($row['species']) ?></div>
                        <small class="text-muted"><i class="fas fa-user me-1"></i>By: <?= htmlspecialchars($row['full_name'] ?? 'Guest') ?></small>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn-cmd bg-dark text-white"         onclick='openModal(<?= json_encode($row) ?>)'>Review Why</button>
                        <button class="btn-cmd bg-success text-white ms-1" onclick="updateAction(<?= $row['id'] ?>, 'Pending', this)">Restore</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    <?= ($search || $filter_severity) ? 'No rejected requests match your filter.' : 'No rejected requests found.' ?>
                </div>
            <?php endif;
            endif; ?>

            </div>
        </div>

    </main>
</div>

<!-- ══════ VET ASSIGN MODAL ══════ -->
<div class="modal fade" id="vetAssignModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="padding:24px;">
            <h6 class="fw-bold text-center mb-3">Assign Medical Officer</h6>
            <input type="hidden" id="assign_case_id">
            <select id="vet_dropdown" class="form-select mb-3" style="border-radius:12px;">
                <option value="">Select Vet...</option>
                <?php $vets_list->data_seek(0); while($v = $vets_list->fetch_assoc()): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['full_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <button class="btn btn-dark w-100 rounded-pill fw-bold" onclick="confirmVetAssign()">Assign Now</button>
        </div>
    </div>
</div>

<!-- ══════ CASE DETAIL MODAL ══════ -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-lg-6 modal-photo-side"><img id="m_img" src=""></div>
                    <div class="col-lg-6 p-5 bg-white">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="gold-label">Intelligence Dossier</span>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <h2 id="m_species" class="fw-bold mb-3 text-dark"></h2>

                        <div id="m_reporter_sec" class="info-block">
                            <span class="gold-label">Reporter Info</span>
                            <div id="m_rep_name"          class="fw-bold text-dark"></div>
                            <div id="m_rep_phone"         class="small text-muted"></div>
                            <div id="m_rep_email"         class="small text-muted"></div>
                            <div id="m_rep_contact_phone" class="small text-muted"></div>
                            <div id="m_rep_contact_email" class="small text-muted"></div>
                        </div>

                        <!-- NEW: Vet Treatment Section for modal -->
                        <div id="m_treatment_sec" class="info-block" style="display:none; border:1px solid rgba(8,145,178,0.2); background:#f0fdfa;">
                            <span class="gold-label" style="color:#0891b2;">Attending Veterinarian</span>
                            <div class="d-flex align-items-center gap-3 mt-2">
                                <img id="m_vet_avatar_img" src="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #0891b2;">
                                <div>
                                    <div id="m_treat_vet_name"  class="fw-bold text-dark"></div>
                                    <div id="m_treat_vet_phone" class="small text-muted"></div>
                                    <div id="m_treat_vet_email" class="small text-muted"></div>
                                    <div id="m_treat_vet_addr"  class="small text-muted"></div>
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-top" id="m_treat_notes_wrap" style="display:none;">
                                <strong class="small">Treatment Notes:</strong>
                                <p id="m_treat_notes" class="text-muted mb-0 small mt-1"></p>
                            </div>
                        </div>

                        <div id="m_medical_sec" class="info-block" style="display:none; border:1px solid rgba(25,135,84,0.2);">
                            <span class="gold-label text-success">Medical Dossier</span>
                            <div class="row small mt-2">
                                <div class="col-6 mb-1"><strong>Breed:</strong>      <span id="m_breed"></span></div>
                                <div class="col-6 mb-1"><strong>Gender:</strong>     <span id="m_gender"></span></div>
                                <div class="col-6 mb-1"><strong>Age:</strong>        <span id="m_age"></span></div>
                                <div class="col-6"><strong>Vaccinated:</strong>      <span id="m_vac"></span></div>
                                <div class="col-6"><strong>Spayed:</strong>          <span id="m_spayed"></span></div>
                            </div>
                            <div class="mt-3 pt-2 border-top">
                                <strong>Vet in Charge:</strong> <span id="m_vet_name" class="text-success fw-bold"></span><br>
                                <strong>Vet Notes:</strong> <p id="m_vet_notes" class="text-muted mb-0 small mt-1"></p>
                            </div>
                        </div>

                        <div id="m_clinic_sec" class="info-block" style="display:none;">
                            <span class="gold-label text-primary">Clinic Info</span>
                            <div id="m_vc_name"    class="fw-bold text-primary"></div>
                            <div id="m_vc_contact" class="small text-muted"></div>
                            <div id="m_vc_addr"    class="small text-muted"></div>
                        </div>

                        <div class="mb-4">
                            <span class="gold-label">Public Description</span>
                            <p id="m_desc" class="text-secondary small"></p>
                        </div>
                        <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const caseModal      = new bootstrap.Modal(document.getElementById('caseModal'));
const vetAssignModal = new bootstrap.Modal(document.getElementById('vetAssignModal'));
let _vetTriggerBtn   = null;

const currentView = '<?= $view ?>';

function removeRow(btn) {
    const row   = btn.closest('tr');
    const tbody = row.closest('tbody');
    row.style.transition = 'opacity 0.3s, transform 0.3s';
    row.style.opacity    = '0';
    row.style.transform  = 'translateX(30px)';
    setTimeout(() => {
        row.remove();
        const badge = document.querySelector('.sidebar-item.active .sidebar-badge');
        if (badge) {
            const n = Math.max(0, parseInt(badge.innerText) - 1);
            if (n === 0) badge.remove();
            else badge.innerText = n;
        }
        const countEl = document.querySelector('.result-count span');
        if (countEl) {
            const cur = Math.max(0, parseInt(countEl.innerText) - 1);
            countEl.innerText = cur;
        }
        if (tbody.querySelectorAll('tr').length === 0) {
            document.getElementById('main-table-wrapper').innerHTML =
                "<div class='empty-msg'>No more records in this view.</div>";
        }
    }, 300);
}

function updateAction(id, status, btn) {
    if (!confirm("Proceed with " + status + "?")) return;
    const f = new FormData();
    f.append('id', id);
    f.append('status', status);
    fetch('update_status.php', { method:'POST', body:f })
        .then(r => r.text())
        .then(d => {
            if (d.trim() === 'success') removeRow(btn);
        });
}

function openVetModal(id, btn) {
    document.getElementById('assign_case_id').value = id;
    _vetTriggerBtn = btn || null;
    vetAssignModal.show();
}

function confirmVetAssign() {
    const id     = document.getElementById('assign_case_id').value;
    const vet_id = document.getElementById('vet_dropdown').value;
    if (!vet_id) { alert('Select a vet!'); return; }
    const f = new FormData();
    f.append('id', id);
    f.append('status', 'Assigned to Vet');
    f.append('vet_id', vet_id);
    fetch('update_status.php', { method:'POST', body:f })
        .then(r => r.text())
        .then(d => {
            if (d.trim() === 'success') {
                vetAssignModal.hide();
                if (_vetTriggerBtn) { removeRow(_vetTriggerBtn); _vetTriggerBtn = null; }
            } else { alert(d); }
        });
}

function openModal(data) {
    document.getElementById('m_img').src           = data.media_path;
    document.getElementById('m_species').innerText = data.species;
    document.getElementById('m_desc').innerText    = data.description || "No description.";

    const repSec       = document.getElementById('m_reporter_sec');
    const medSec       = document.getElementById('m_medical_sec');
    const clinicSec    = document.getElementById('m_clinic_sec');
    const treatmentSec = document.getElementById('m_treatment_sec');

    // Reset all sections
    repSec.style.display       = 'none';
    medSec.style.display       = 'none';
    clinicSec.style.display    = 'none';
    treatmentSec.style.display = 'none';

    if (data.vc_name) {
        clinicSec.style.display = 'block';
        document.getElementById('m_vc_name').innerText    = data.vc_name;
        document.getElementById('m_vc_contact').innerText = data.vc_phone ? "Contact: " + data.vc_phone : "";
        document.getElementById('m_vc_addr').innerText    = data.vc_addr  ? "Address: " + data.vc_addr  : "";
    }

    if (data.status === 'Vet Cleared') {
        medSec.style.display = 'block';
        document.getElementById('m_breed').innerText     = data.breed             || "N/A";
        document.getElementById('m_gender').innerText    = data.gender            || "N/A";
        document.getElementById('m_age').innerText       = data.age               || "Unknown";
        document.getElementById('m_vac').innerText       = data.is_vaccinated == 1    ? "Yes" : "No";
        document.getElementById('m_spayed').innerText    = data.is_spayed == 1        ? "Yes" : "No";
        document.getElementById('m_vet_name').innerText  = "Dr. " + (data.vet_name   || "N/A");
        document.getElementById('m_vet_notes').innerText = data.vet_notes             || "No additional notes.";

    } else if (data.status === 'Assigned to Vet') {
        // Show treatment vet section
        treatmentSec.style.display = 'block';
        repSec.style.display       = 'block';

        const vetAvatar = data.vet_avatar
            ? data.vet_avatar
            : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.vet_name || 'V') + '&background=0891b2&color=ffffff&size=96&bold=true';

        document.getElementById('m_vet_avatar_img').src        = vetAvatar;
        document.getElementById('m_treat_vet_name').innerText  = "Dr. " + (data.vet_name   || "N/A");
        document.getElementById('m_treat_vet_phone').innerText = data.vet_phone ? "📞 " + data.vet_phone : "";
        document.getElementById('m_treat_vet_email').innerText = data.vet_email ? "✉ "  + data.vet_email : "";
        document.getElementById('m_treat_vet_addr').innerText  = data.vet_address ? "📍 " + data.vet_address : "";

        const notesWrap = document.getElementById('m_treat_notes_wrap');
        if (data.vet_notes) {
            notesWrap.style.display = 'block';
            document.getElementById('m_treat_notes').innerText = data.vet_notes;
        } else {
            notesWrap.style.display = 'none';
        }

        // Reporter info
        document.getElementById('m_rep_name').innerText  = data.reporter_name  || data.full_name  || "Guest";
        document.getElementById('m_rep_phone').innerText = "Phone: " + (data.reporter_phone || data.phone || "N/A");
        document.getElementById('m_rep_email').innerText = "Email: " + (data.reporter_email || data.email || "N/A");
        document.getElementById('m_rep_contact_phone').innerText = data.contact_phone ? "Alt Phone: " + data.contact_phone : "";
        document.getElementById('m_rep_contact_email').innerText = data.contact_email ? "Alt Email: " + data.contact_email : "";

    } else {
        repSec.style.display = 'block';
        document.getElementById('m_rep_name').innerText  = data.full_name    || data.reporter_name  || "Guest";
        document.getElementById('m_rep_phone').innerText = "Phone: "  + (data.phone         || data.reporter_phone || "N/A");
        document.getElementById('m_rep_email').innerText = "Email: "  + (data.email         || data.reporter_email || "N/A");
        const cpEl = document.getElementById('m_rep_contact_phone');
        const ceEl = document.getElementById('m_rep_contact_email');
        cpEl.innerText = data.contact_phone ? "Alt Phone: " + data.contact_phone : "";
        ceEl.innerText = data.contact_email ? "Alt Email: " + data.contact_email : "";
    }

    caseModal.show();
}

function printPage() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var el   = document.getElementById('printDateStamp');
    if (el) el.textContent = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    window.print();
}
(function() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var el   = document.getElementById('printDateStamp');
    if (el) el.textContent = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
})();
</script>

<?php include 'footer.php'; ?>
</body>
</html>