<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'vet' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$vet_id   = $_SESSION['user_id'];
$vet_name = $_SESSION['full_name'] ?? "Doctor";
$msg      = "";

// ── FORM SUBMISSION: UPDATE PATIENT ──────────────────────────────────────────
if (isset($_POST['submit_clearance'])) {
    $rescue_id     = intval($_POST['rescue_id']);
    $db_status     = ($_POST['clearance_status'] === 'Cleared') ? 'Vet Cleared' : 'Assigned to Vet';
    $gender        = $_POST['gender'];
    $is_vaccinated = isset($_POST['is_vaccinated']) ? 1 : 0;
    $is_spayed     = isset($_POST['is_spayed'])     ? 1 : 0;
    $vet_notes     = trim($_POST['vet_notes'] ?? '');
    if ($vet_notes === 'Ready for forever home.') $vet_notes = '';
    $species = trim($_POST['species'] ?? '');
    $breed   = trim($_POST['breed']   ?? '');
    $age     = intval($_POST['age']   ?? 0);

    $stmt = $conn->prepare("UPDATE rescues SET status=?, gender=?, is_vaccinated=?, is_spayed_neutered=?, vet_notes=?, species=?, breed=?, age=? WHERE id=? AND assigned_vet=?");
    $stmt->bind_param("ssiisssiii",
        $db_status, $gender,
        $is_vaccinated, $is_spayed,
        $vet_notes, $species, $breed, $age,
        $rescue_id, $vet_id
    );
    $stmt->execute();
    $stmt->close();

    // Set vet_cleared_at only when clearing for the first time
    if ($db_status === 'Vet Cleared') {
        $ts = $conn->prepare("UPDATE rescues SET vet_cleared_at = NOW() WHERE id = ? AND assigned_vet = ? AND vet_cleared_at IS NULL");
        $ts->bind_param("ii", $rescue_id, $vet_id);
        $ts->execute();
        $ts->close();
    }

    // ── AUTO-LOG MEDICAL EXPENSE ──────────────────────────────
    if (!empty($_POST['treatment_cost']) && floatval($_POST['treatment_cost']) > 0) {
        require_once 'finance_auto_hooks.php';
        autoLogMedicalExpense($conn, $rescue_id, floatval($_POST['treatment_cost']),
            'Vet: ' . $vet_name, $_POST['vet_notes'] ?? '', $vet_id);
    }
    // ─────────────────────────────────────────────────────────
    $msg = "<div class='rq-alert'><i class='fas fa-check-circle me-2'></i>Patient #$rescue_id updated successfully!</div>";
}

// ── ACTIVE PATIENTS ───────────────────────────────────────────────────────────
$my_patients = $conn->prepare("SELECT * FROM rescues WHERE assigned_vet=? AND status='Assigned to Vet' ORDER BY id DESC");
$my_patients->bind_param("i", $vet_id);
$my_patients->execute();
$my_patients = $my_patients->get_result();

// ── CLEARANCE RECORDS: BUILD DYNAMIC FILTER ──────────────────────────────────
$f_species   = trim($_GET['f_species']   ?? '');
$f_search    = trim($_GET['f_search']    ?? '');
$f_date_from = trim($_GET['f_date_from'] ?? '');
$f_date_to   = trim($_GET['f_date_to']   ?? '');

$filter_where  = "r.assigned_vet=? AND r.status='Vet Cleared'";
$filter_params = [$vet_id];
$filter_types  = "i";

if ($f_species !== '') {
    $filter_where   .= " AND r.species LIKE ?";
    $filter_params[] = "%$f_species%";
    $filter_types   .= "s";
}
if ($f_search !== '') {
    $filter_where   .= " AND (r.id LIKE ? OR u.full_name LIKE ? OR r.breed LIKE ?)";
    $s = "%$f_search%";
    $filter_params   = array_merge($filter_params, [$s, $s, $s]);
    $filter_types   .= "sss";
}
if ($f_date_from !== '') {
    $filter_where   .= " AND DATE(r.vet_cleared_at) >= ?";
    $filter_params[] = $f_date_from;
    $filter_types   .= "s";
}
if ($f_date_to !== '') {
    $filter_where   .= " AND DATE(r.vet_cleared_at) <= ?";
    $filter_params[] = $f_date_to;
    $filter_types   .= "s";
}

// Build active filter label for print header
$active_filters = [];
if ($f_species)   $active_filters[] = 'Species: ' . htmlspecialchars($f_species);
if ($f_search)    $active_filters[] = 'Search: "' . htmlspecialchars($f_search) . '"';
if ($f_date_from) $active_filters[] = 'From: ' . date('d M Y', strtotime($f_date_from));
if ($f_date_to)   $active_filters[] = 'To: ' . date('d M Y', strtotime($f_date_to));
$filter_label = implode(' · ', $active_filters);

$cleared_sql = "SELECT r.id, r.species, r.breed, r.age, r.gender, r.is_vaccinated,
                r.is_spayed_neutered, r.vet_notes, r.media_path, r.location,
                r.created_at, r.vet_cleared_at, u.full_name AS reporter_name
                FROM rescues r LEFT JOIN users u ON r.user_id = u.id
                WHERE $filter_where ORDER BY r.vet_cleared_at DESC";

$cleared_q = $conn->prepare($cleared_sql);
$cleared_q->bind_param($filter_types, ...$filter_params);
$cleared_q->execute();
$cleared_records = $cleared_q->get_result();
$cleared_count   = $cleared_records->num_rows;

// ── HELPERS ───────────────────────────────────────────────────────────────────
function gender_select(string $fid, string $current): string {
    $opts = ['Unknown' => 'Unknown Gender', 'Male' => 'Male', 'Female' => 'Female'];
    $html = "<select name='gender' form='{$fid}' class='vet-select'>";
    foreach ($opts as $val => $label)
        $html .= "<option value='{$val}'" . ($current === $val ? ' selected' : '') . ">{$label}</option>";
    return $html . "</select>";
}

function flabel(string $text): string {
    return "<span class='field-label'>{$text}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Command | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=1.1">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 110px; }
        body { background-color: #f0f2f7; font-family: 'Montserrat', sans-serif; min-height: 100vh; margin: 0; }

        /* HERO */
        .hq-header {
            margin-top: -1px;
            background: linear-gradient(135deg, rgba(6,10,22,.95) 0%, rgba(10,19,41,.90) 100%),
                        url('https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?q=80&w=2070&auto=format&fit=crop') center/cover;
            padding: 90px 0 130px; color: white; text-align: center;
            border-bottom: 3px solid rgba(8,145,178,.4); position: relative; overflow: hidden;
        }
        .hq-header::before { content: ''; position: absolute; top: -80px; right: -80px; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(8,145,178,.1) 0%, transparent 70%); pointer-events: none; }
        .hq-header::after  { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 60px; background: linear-gradient(to bottom, transparent, #f0f2f7); pointer-events: none; }
        .hq-header .container { position: relative; z-index: 2; }
        .hq-eyebrow  { font-size: .62rem; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: rgba(255,193,7,.7); display: block; margin-bottom: 12px; }
        .hq-header h1{ font-family: 'Cinzel', serif !important; font-weight: 700 !important; font-size: clamp(1.8rem,3.5vw,2.8rem) !important; color: #fff !important; letter-spacing: 3px !important; margin: 0 0 12px !important; line-height: 1.2 !important; }
        .hq-header p { font-size: .82rem; font-weight: 500; color: rgba(255,239,194,.5); letter-spacing: .5px; margin: 0; }
        .hq-divider  { width: 50px; height: 2px; background: linear-gradient(90deg,#ffc107,transparent); margin: 16px auto; border-radius: 2px; }

        /* LAYOUT */
        .stats-overlap { margin-top: -80px; position: relative; z-index: 5; padding-bottom: 20px; }
        .main-content  { background-color: #f0f2f7; position: relative; z-index: 1; padding-bottom: 60px; }

        /* NAV CARD */
        .nav-card { background: #fff; border-radius: 18px; padding: 24px 16px 20px; transition: transform .3s cubic-bezier(.165,.84,.44,1), box-shadow .3s ease; box-shadow: 0 8px 28px rgba(10,19,41,.1); text-decoration: none !important; display: block; border-bottom: 3px solid transparent; text-align: center; position: relative; overflow: hidden; }
        .nav-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 18px 18px 0 0; opacity: 0; transition: opacity .3s ease; }
        .nav-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(10,19,41,.14); }
        .nav-card:hover::before { opacity: 1; }
        .nav-card-vet { border-bottom-color: #0891b2 !important; }
        .nav-card-vet::before { background: #0891b2; }
        .nc-icon     { width: 46px; height: 46px; border-radius: 13px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.1rem; }
        .nc-icon-vet { background: rgba(8,145,178,.1); color: #0891b2; }
        .nc-label    { font-size: .68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #4b5563; display: block; margin-bottom: 6px; }
        .nc-count    { font-family: 'Cinzel', serif; font-size: 2rem; font-weight: 700; line-height: 1; margin: 0; color: #0891b2; }

        /* SECTION LABEL */
        .section-label { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; margin-top: 48px; }
        .section-label::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .section-label-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
        .sl-vet  { background: rgba(8,145,178,.12); color: #0891b2; }
        .sl-text { font-size: .65rem; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #4b5563; }

        /* SECTION CARD */
        .section-card { border: 1px solid rgba(255,255,255,.9) !important; border-radius: 20px !important; box-shadow: 0 4px 20px rgba(10,19,41,.07) !important; background: #fff; overflow: hidden; }
        .card-header-custom { padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: #fff; border-top: 3px solid #0891b2; }
        .card-header-custom h5 { font-family: 'Montserrat', sans-serif !important; font-size: .85rem !important; font-weight: 700 !important; letter-spacing: .5px !important; margin: 0 !important; color: #0891b2; }

        /* BADGE */
        .rq-badge     { font-family: 'Montserrat', sans-serif; font-size: .6rem; font-weight: 700; letter-spacing: 1px; padding: 5px 12px; border-radius: 20px; }
        .rq-badge-vet { background: rgba(8,145,178,.1); color: #0891b2; border: 1px solid rgba(8,145,178,.2); }

        /* TABLE */
        .table { margin: 0 !important; }
        .table > thead > tr > th { font-size: .68rem !important; font-weight: 700 !important; letter-spacing: 2px !important; text-transform: uppercase !important; color: #4b5563 !important; padding: 14px 20px !important; border: none !important; background: #fafbfd !important; border-bottom: 1px solid #f1f5f9 !important; }
        .table > tbody > tr { border-bottom: 1px solid #f1f5f9 !important; transition: background .2s ease; }
        .table > tbody > tr:last-child { border-bottom: none !important; }
        .table > tbody > tr:hover { background: #fafbfd; }
        .table td { padding: 20px !important; vertical-align: middle !important; border: none !important; }
        .td-divider { border-left: 1px solid #f1f5f9; }

        /* PATIENT IMAGE */
        .patient-img { width: 68px; height: 58px; object-fit: cover; border-radius: 12px; border: 2px solid #f1f5f9; flex-shrink: 0; }

        /* ROW TEXT */
        .row-species { font-size: .9rem; font-weight: 700; color: #0a1329; margin-bottom: 3px; }
        .row-id      { font-size: .72rem; font-weight: 500; color: #4b5563; }
        .field-label { font-size: .62rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #4b5563; display: block; margin-bottom: 4px; }

        /* FORM CONTROLS */
        .vet-select, .vet-textarea {
            border: 1px solid #e2e8f0 !important; border-radius: 8px !important;
            font-size: .78rem !important; font-family: 'Montserrat', sans-serif !important;
            font-weight: 500 !important; background: #fafbfd !important; color: #0a1329 !important;
            transition: border-color .2s ease, box-shadow .2s ease !important; width: 100%;
        }
        .vet-select  { padding: 7px 10px !important; margin-bottom: 10px; }
        .vet-textarea{ padding: 8px 10px !important; resize: vertical; }
        .vet-select:focus, .vet-textarea:focus { border-color: #0891b2 !important; box-shadow: 0 0 0 3px rgba(8,145,178,.1) !important; outline: none !important; }
        .check-group label { font-size: .75rem; font-weight: 600; color: #374151; cursor: pointer; }
        .form-check-input:checked { background-color: #0891b2 !important; border-color: #0891b2 !important; }
        .form-check-input:focus   { box-shadow: 0 0 0 3px rgba(8,145,178,.15) !important; border-color: #0891b2 !important; }

        /* UPDATE BUTTON */
        .btn-update { font-family: 'Montserrat', sans-serif !important; font-size: .65rem !important; font-weight: 700 !important; letter-spacing: 1.5px !important; text-transform: uppercase !important; padding: 10px 20px !important; border-radius: 8px !important; border: none !important; cursor: pointer; transition: transform .2s ease, box-shadow .2s ease !important; white-space: nowrap; width: 100%; background: #0a1329 !important; color: #0891b2 !important; box-shadow: 0 3px 10px rgba(10,19,41,.2) !important; }
        .btn-update:hover  { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(10,19,41,.3) !important; background: #0891b2 !important; color: #fff !important; }
        .btn-update:active { transform: scale(.97); }

        /* PRINT BUTTON */
        .btn-print-vet { font-family: 'Montserrat', sans-serif; font-size: .65rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 8px 18px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s ease; white-space: nowrap; }
        .btn-print-vet:hover { background: #0a1329; color: #fff; border-color: #0a1329; }

        /* FILTER BAR */
        .filter-bar { background: #fff; border-radius: 14px; padding: 14px 18px; border: 1px solid #e2e8f0; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,.03); }
        .filter-bar .fb-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-bar .fb-label { font-size: .6rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #4b5563; }
        .filter-bar input, .filter-bar select { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: .8rem; font-family: 'Montserrat', sans-serif; background: #fff; color: #0a1329; transition: border-color .2s; min-width: 130px; }
        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: #0891b2; }
        .filter-bar .fb-search { flex: 1; min-width: 200px; }
        .result-count { font-size: .72rem; font-weight: 700; color: #94a3b8; margin-left: auto; white-space: nowrap; align-self: center; }
        .result-count span { color: #0a1329; }

        /* DATE BADGE */
        .date-badge { display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; color: #64748b; font-size: .63rem; font-weight: 600; padding: 3px 8px; border-radius: 6px; margin-top: 3px; }
        .date-badge i { font-size: .6rem; }

        /* ALERT / EMPTY */
        .rq-alert    { background: rgba(34,197,94,.08); border: 1px solid rgba(34,197,94,.2); color: #16a34a; border-radius: 12px; padding: 14px 20px; font-size: .8rem; font-weight: 600; margin-bottom: 20px; }
        .empty-state { padding: 60px 0 !important; text-align: center !important; color: #cbd5e1 !important; }
        .empty-state i    { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .4; }
        .empty-state span { font-size: .8rem; font-weight: 600; letter-spacing: .5px; display: block; }

        /* ANIMATIONS */
        .rq-section, .stat-col { opacity: 0; transform: translateY(20px); animation: rq-rise .45s ease forwards; }
        .stat-col { transform: translateY(24px); animation-duration: .4s; }
        .rq-section  { animation-delay: .1s; }
        .stat-col:nth-child(1) { animation-delay: .05s; }
        .stat-col:nth-child(2) { animation-delay: .12s; }
        @keyframes rq-rise { to { opacity: 1; transform: translateY(0); } }

        /* PRINT STYLES */
        .print-only  { display: none; }
        .screen-only { display: block; }
        @media print {
            .hq-header, .stats-overlap, nav, .navbar, footer,
            .btn-update, .btn-print-vet, .filter-bar, button,
            [class*="chat"], .section-label, .rq-alert { display: none !important; }
            body { background: #fff !important; font-size: 11pt; }
            .main-content { padding: 0 !important; }
            .section-card { box-shadow: none !important; border: 1px solid #ccc !important; border-radius: 0 !important; }
            .table > tbody > tr:hover { background: transparent !important; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            /* show/hide driven by JS adding .printing-active / .printing-cleared */
            .print-only  { display: none !important; }
            .screen-only { display: none !important; }
            /* Active patients print */
            body.printing-active  #print-header-active  { display: block !important; }
            body.printing-active  #print-active-table   { display: block !important; }
            body.printing-active  #section-cleared      { display: none  !important; }
            body.printing-active  #section-active       { display: block !important; }
            /* Cleared records print */
            body.printing-cleared #print-header-cleared { display: block !important; }
            body.printing-cleared #section-cleared      { display: block !important; }
            body.printing-cleared #section-active       { display: none  !important; }
            /* shared print header styles */
            .print-only-header { display: flex !important; align-items: center; justify-content: space-between; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #0a1329; }
            .print-only-header h3 { font-size: 14pt; font-weight: 800; color: #0a1329; margin: 0; font-family: 'Montserrat', sans-serif; }
            .print-meta { font-size: 9pt; color: #64748b; text-align: right; }
            .print-filters { font-size: 8pt; color: #94a3b8; margin-top: 3px; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="hq-header">
    <div class="container">
        <span class="hq-eyebrow">Medical Logistics HQ</span>
        <h1>Dr. <?= htmlspecialchars($vet_name) ?></h1>
        <div class="hq-divider"></div>
        <p>Review and provide medical clearance for rescued animals</p>
    </div>
</header>

<div class="container stats-overlap">
    <div class="row g-4 justify-content-center">
        <div class="col-md-3 col-6 stat-col">
            <div class="nav-card nav-card-vet" style="cursor:default;">
                <div class="nc-icon nc-icon-vet"><i class="fas fa-stethoscope"></i></div>
                <span class="nc-label">Active Patients</span>
                <p class="nc-count"><?= $my_patients->num_rows ?></p>
            </div>
        </div>
        <div class="col-md-3 col-6 stat-col">
            <a href="#cleared-anchor" class="nav-card nav-card-vet" style="border-bottom-color:#16a34a !important;">
                <div class="nc-icon" style="background:rgba(22,163,74,.15);color:#16a34a;"><i class="fas fa-award"></i></div>
                <span class="nc-label">Total Cleared</span>
                <p class="nc-count" style="color:#16a34a;"><?= $cleared_count ?></p>
            </a>
        </div>
    </div>
</div>

<div class="main-content">
<div class="container">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- ACTIVE PATIENTS SECTION                                     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="rq-section">
        <div class="section-label">
            <div class="section-label-icon sl-vet"><i class="fas fa-stethoscope"></i></div>
            <span class="sl-text">Active Medical Cases</span>
        </div>

        <?= $msg ?>

        <!-- PRINT-ONLY HEADER for Active Patients -->
        <div class="print-only" id="print-header-active">
            <div class="print-only-header">
                <h3>Active Medical Cases — Dr. <?= htmlspecialchars($vet_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Medical Command</div>
                    <div id="printDateStampActive" style="font-size:8pt;color:#94a3b8;margin-top:2px;"></div>
                </div>
            </div>
        </div>

        <div class="section-card" id="section-active">
            <div class="card-header-custom">
                <h5><i class="fas fa-notes-medical me-2"></i>My Patients</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge rq-badge-vet"><?= $my_patients->num_rows ?> Assigned</span>
                    <button class="btn-print-vet" onclick="printSection('active')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- PRINT-ONLY: Clean read-only active patients table -->
            <div class="print-only" id="print-active-table">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#fafbfd;border-bottom:1px solid #e2e8f0;">
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;">Animal</th>
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;border-left:1px solid #e2e8f0;">Species / Breed / Age</th>
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;border-left:1px solid #e2e8f0;">Gender</th>
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;border-left:1px solid #e2e8f0;">Medical Status</th>
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;border-left:1px solid #e2e8f0;">Location</th>
                            <th style="padding:10px 14px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#4b5563;border-left:1px solid #e2e8f0;">Clinical Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($my_patients->num_rows > 0):
                        $my_patients->data_seek(0);
                        while ($pet = $my_patients->fetch_assoc()):
                    ?>
                    <tr style="border-bottom:1px solid #f1f5f9;page-break-inside:avoid;">
                        <td style="padding:10px 14px;">
                            <div style="font-weight:700;font-size:10pt;color:#0a1329;"><?= htmlspecialchars($pet['species'] ?? 'Unknown') ?></div>
                            <div style="font-size:8pt;color:#64748b;">Rescue #<?= $pet['id'] ?></div>
                        </td>
                        <td style="padding:10px 14px;border-left:1px solid #f1f5f9;">
                            <div style="font-size:9pt;font-weight:600;color:#0a1329;"><?= htmlspecialchars($pet['species'] ?? '—') ?></div>
                            <div style="font-size:8pt;color:#64748b;"><?= htmlspecialchars($pet['breed'] ?? '—') ?></div>
                            <div style="font-size:8pt;color:#64748b;"><?= !empty($pet['age']) ? $pet['age'].' yr'.($pet['age'] != 1 ? 's' : '') : '—' ?></div>
                        </td>
                        <td style="padding:10px 14px;border-left:1px solid #f1f5f9;font-size:9pt;color:#0a1329;"><?= htmlspecialchars($pet['gender'] ?? 'Unknown') ?></td>
                        <td style="padding:10px 14px;border-left:1px solid #f1f5f9;font-size:8.5pt;color:#0a1329;line-height:1.6;">
                            <?= $pet['is_vaccinated']      ? '✓ Vaccinated'     : '✗ Not Vaccinated' ?><br>
                            <?= $pet['is_spayed_neutered'] ? '✓ Spayed/Neutered' : '✗ Not Spayed' ?>
                        </td>
                        <td style="padding:10px 14px;border-left:1px solid #f1f5f9;font-size:8pt;color:#64748b;"><?= htmlspecialchars($pet['location'] ?? '—') ?></td>
                        <td style="padding:10px 14px;border-left:1px solid #f1f5f9;font-size:8pt;color:#64748b;max-width:200px;">
                            <?= !empty($pet['vet_notes']) && $pet['vet_notes'] !== 'Ready for forever home.' ? nl2br(htmlspecialchars($pet['vet_notes'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" style="padding:20px;text-align:center;color:#94a3b8;font-size:9pt;">No active patients assigned.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- SCREEN-ONLY: Editable form table -->
            <div class="screen-only">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Animal Details</th>
                            <th class="td-divider">Animal Info</th>
                            <th class="td-divider">Medical Status &amp; Notes</th>
                            <th class="td-divider text-center pe-4">Command</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($my_patients->num_rows > 0):
                        $my_patients->data_seek(0);
                        while ($pet = $my_patients->fetch_assoc()):
                            $fid = 'f' . $pet['id'];
                            $img = htmlspecialchars($pet['media_path'] ?: 'assets/no-image.jpg');
                    ?>
                    <form id="<?= $fid ?>" action="" method="POST">
                        <input type="hidden" name="rescue_id"        value="<?= $pet['id'] ?>" form="<?= $fid ?>">
                        <input type="hidden" name="submit_clearance" value="1"                  form="<?= $fid ?>">
                    </form>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $img ?>" class="patient-img" alt="patient">
                                <div>
                                    <div class="row-species"><?= htmlspecialchars($pet['species'] ?? 'Unknown') ?></div>
                                    <div class="row-id"><i class="fas fa-hashtag me-1" style="color:#0891b2;"></i>Rescue #<?= $pet['id'] ?></div>
                                    <div class="row-id"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($pet['location'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="td-divider" style="min-width:200px;">
                            <?= flabel('Species') ?>
                            <input type="text" name="species" form="<?= $fid ?>" class="vet-select" placeholder="e.g. Dog, Cat" value="<?= htmlspecialchars($pet['species'] ?? '') ?>">
                            <?= flabel('Breed') ?>
                            <input type="text" name="breed" form="<?= $fid ?>" class="vet-select" placeholder="e.g. Labrador, Persian" value="<?= htmlspecialchars($pet['breed'] ?? '') ?>">
                            <?= flabel('Age (years)') ?>
                            <input type="number" name="age" form="<?= $fid ?>" class="vet-select" placeholder="e.g. 2" min="0" step="1" value="<?= htmlspecialchars($pet['age'] ?? '') ?>" style="margin-bottom:12px;">
                            <?= flabel('Gender') ?>
                            <?= gender_select($fid, $pet['gender'] ?? 'Unknown') ?>
                            <div class="check-group">
                                <div class="form-check mb-1">
                                    <input type="checkbox" name="is_vaccinated" form="<?= $fid ?>" class="form-check-input" id="vac<?= $pet['id'] ?>" <?= $pet['is_vaccinated'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="vac<?= $pet['id'] ?>">Vaccinated</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_spayed" form="<?= $fid ?>" class="form-check-input" id="spay<?= $pet['id'] ?>" <?= $pet['is_spayed_neutered'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="spay<?= $pet['id'] ?>">Spayed / Neutered</label>
                                </div>
                            </div>
                        </td>
                        <td class="td-divider">
                            <?= flabel('Clearance Status') ?>
                            <select name="clearance_status" form="<?= $fid ?>" class="vet-select">
                                <option value="Under Treatment">Under Treatment</option>
                                <option value="Cleared">Ready for Release (Cleared)</option>
                            </select>
                            <?= flabel('Clinical Notes') ?>
                            <textarea name="vet_notes" form="<?= $fid ?>" class="vet-textarea" rows="3"
                                      placeholder="Write clinical notes..."><?= htmlspecialchars($pet['vet_notes'] !== 'Ready for forever home.' ? ($pet['vet_notes'] ?? '') : '') ?></textarea>
                            <?= flabel('Treatment Cost (৳) — optional') ?>
                            <input type="number" name="treatment_cost" form="<?= $fid ?>"
                                   class="vet-select" placeholder="e.g. 1500.00" min="0" step="0.01">
                        </td>
                        <td class="td-divider pe-4 text-center" style="min-width:130px;">
                            <button type="submit" form="<?= $fid ?>" class="btn-update">
                                <i class="fas fa-save me-1"></i>Update
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="empty-state"><i class="fas fa-clipboard-check"></i><span>No active medical cases assigned to you right now.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div><!-- end .screen-only -->
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CLEARANCE RECORDS SECTION                                   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div id="cleared-anchor" class="rq-section">
        <div class="section-label">
            <div class="section-label-icon" style="background:rgba(34,197,94,.12);color:#16a34a;"><i class="fas fa-award"></i></div>
            <span class="sl-text">Clearance Records</span>
        </div>

        <!-- PRINT-ONLY HEADER (hidden on screen, visible when printing) -->
        <div class="print-only" id="print-header-cleared">
            <div class="print-only-header">
                <h3>Medical Clearance Records — Dr. <?= htmlspecialchars($vet_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Medical Command</div>
                    <?php if ($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp" style="font-size:8pt;color:#94a3b8;margin-top:2px;"></div>
                </div>
            </div>
        </div>

        <!-- CARD HEADER with Print Button -->
        <div class="section-card" id="section-cleared">
            <div class="card-header-custom" style="border-top-color:#16a34a;">
                <h5 style="color:#16a34a;"><i class="fas fa-check-double me-2"></i>Animals Cleared by Me</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge" style="background:rgba(34,197,94,.1);color:#16a34a;border:1px solid rgba(34,197,94,.2);"><?= $cleared_count ?> Cleared</span>
                    <button class="btn-print-vet" onclick="printSection('cleared')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- FILTER BAR -->
            <form method="GET" action="" class="filter-bar" style="border-radius:0;border-left:none;border-right:none;border-top:none;border-bottom:1px solid #f1f5f9;margin-bottom:0;box-shadow:none;">
                <input type="hidden" name="_section" value="cleared">

                <div class="fb-group fb-search">
                    <span class="fb-label">Search</span>
                    <input type="text" name="f_search" placeholder="Rescue ID, reporter name, breed..." value="<?= htmlspecialchars($f_search) ?>">
                </div>

                <div class="fb-group">
                    <span class="fb-label">Species</span>
                    <select name="f_species">
                        <option value="">All Species</option>
                        <option value="dog"   <?= $f_species === 'dog'   ? 'selected' : '' ?>>Dog</option>
                        <option value="cat"   <?= $f_species === 'cat'   ? 'selected' : '' ?>>Cat</option>
                        <option value="bird"  <?= $f_species === 'bird'  ? 'selected' : '' ?>>Bird</option>
                        <option value="other" <?= $f_species === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="fb-group">
                    <span class="fb-label">Cleared From</span>
                    <input type="date" name="f_date_from" value="<?= htmlspecialchars($f_date_from) ?>">
                </div>

                <div class="fb-group">
                    <span class="fb-label">Cleared To</span>
                    <input type="date" name="f_date_to" value="<?= htmlspecialchars($f_date_to) ?>">
                </div>

                <div class="d-flex gap-2 align-self-end">
                    <button type="submit" class="btn-update" style="width:auto;padding:9px 18px !important;">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>#cleared-anchor"
                       class="btn-update" style="width:auto;padding:9px 14px !important;text-decoration:none;text-align:center;">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                <div class="result-count">
                    <span><?= $cleared_count ?></span> record<?= $cleared_count != 1 ? 's' : '' ?>
                    <?php if ($filter_label): ?>
                        &nbsp;<span style="color:#16a34a;">— filtered</span>
                    <?php endif; ?>
                </div>
            </form>

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Animal</th>
                            <th class="td-divider">Reported By</th>
                            <th class="td-divider">Biological Info</th>
                            <th class="td-divider">Vet Notes</th>
                            <th class="td-divider text-center pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($cleared_count > 0):
                        $cleared_records->data_seek(0);
                        while ($rec = $cleared_records->fetch_assoc()):
                            $img = htmlspecialchars($rec['media_path'] ?: 'assets/no-image.jpg');
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $img ?>" class="patient-img" alt="animal">
                                <div>
                                    <div class="row-species" style="color:#16a34a;"><?= htmlspecialchars($rec['species'] ?? 'Unknown') ?></div>
                                    <div class="row-id"><i class="fas fa-hashtag me-1" style="color:#0891b2;"></i>Rescue #<?= $rec['id'] ?></div>
                                    <div class="row-id"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($rec['location'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="td-divider">
                            <div style="font-size:.8rem;font-weight:600;color:#0a1329;"><?= htmlspecialchars($rec['reporter_name'] ?? 'Unknown') ?></div>
                            <span class="date-badge d-block" style="margin-top:5px;">
                                <i class="fas fa-calendar-plus"></i>
                                SOS: <?= $rec['created_at'] ? date('d M Y', strtotime($rec['created_at'])) : '—' ?>
                            </span>
                            <span class="date-badge d-block" style="background:rgba(22,163,74,.08);color:#16a34a;border:1px solid rgba(22,163,74,.2);margin-top:4px;">
                                <i class="fas fa-check-circle"></i>
                                Cleared: <?= $rec['vet_cleared_at'] ? date('d M Y, h:i A', strtotime($rec['vet_cleared_at'])) : '—' ?>
                            </span>
                        </td>
                        <td class="td-divider">
                            <div style="font-size:.75rem;font-weight:600;color:#0a1329;margin-bottom:5px;">
                                <i class="fas fa-paw me-1" style="color:#0891b2;"></i>
                                <?= htmlspecialchars($rec['species'] ?? '—') ?>
                                <?php if (!empty($rec['breed'])): ?>
                                    <span style="color:#4b5563;font-weight:500;"> · <?= htmlspecialchars($rec['breed']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($rec['age'])): ?>
                            <div style="font-size:.72rem;color:#64748b;margin-bottom:5px;">
                                <i class="fas fa-birthday-cake me-1" style="color:#8b5cf6;"></i>
                                <?= htmlspecialchars($rec['age']) ?> yr<?= $rec['age'] != 1 ? 's' : '' ?> old
                            </div>
                            <?php endif; ?>
                            <div style="font-size:.75rem;font-weight:600;color:#475569;margin-bottom:4px;">
                                <i class="fas fa-venus-mars me-1" style="color:#8b5cf6;"></i><?= htmlspecialchars($rec['gender'] ?? 'Unknown') ?>
                            </div>
                            <div style="font-size:.72rem;color:#4b5563;">
                                <?php
                                $vac_icon  = $rec['is_vaccinated']      ? ['#16a34a','syringe','Vaccinated']    : ['#ef4444','times','Not Vaccinated'];
                                $spay_icon = $rec['is_spayed_neutered']  ? ['#16a34a','cut','Spayed/Neutered']  : ['#4b5563','minus','Not Spayed'];
                                foreach ([$vac_icon, $spay_icon] as [$color, $icon, $label]):
                                ?>
                                    <span style="color:<?= $color ?>;"><i class="fas fa-<?= $icon ?> me-1"></i><?= $label ?></span><br>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="td-divider" style="max-width:220px;">
                            <div style="font-size:.75rem;color:#64748b;line-height:1.5;">
                                <?= !empty($rec['vet_notes']) ? htmlspecialchars($rec['vet_notes']) : '<span style="color:#cbd5e1;">No notes recorded.</span>' ?>
                            </div>
                        </td>
                        <td class="td-divider text-center pe-4">
                            <span class="rq-badge" style="background:rgba(34,197,94,.08);color:#16a34a;border:1px solid rgba(34,197,94,.2);">
                                <i class="fas fa-check-circle me-1"></i>Vet Cleared
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-award"></i>
                            <span><?= ($f_search || $f_species || $f_date_from || $f_date_to) ? 'No records match your filter.' : 'No animals cleared yet. Keep up the great work!' ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function printSection(section) {
    // Stamp date/time on the relevant header
    var now  = new Date();
    var opts = { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
    var dateStr = now.toLocaleDateString('en-GB', opts);

    if (section === 'active') {
        var el = document.getElementById('printDateStampActive');
        if (el) el.textContent = 'Printed: ' + dateStr;
        document.body.classList.add('printing-active');
        document.body.classList.remove('printing-cleared');
    } else {
        var el = document.getElementById('printDateStamp');
        if (el) el.textContent = 'Printed: ' + dateStr;
        document.body.classList.add('printing-cleared');
        document.body.classList.remove('printing-active');
    }

    window.print();

    // Clean up classes after print dialog closes
    window.addEventListener('afterprint', function cleanup() {
        document.body.classList.remove('printing-active', 'printing-cleared');
        window.removeEventListener('afterprint', cleanup);
    });
}

// Stamp date on load (for Ctrl+P fallback — defaults to cleared)
(function () {
    var now  = new Date();
    var opts = { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
    var dateStr = now.toLocaleDateString('en-GB', opts);
    var el1 = document.getElementById('printDateStamp');
    var el2 = document.getElementById('printDateStampActive');
    if (el1) el1.textContent = 'Printed: ' + dateStr;
    if (el2) el2.textContent = 'Printed: ' + dateStr;
})();
</script>
</body>
</html>