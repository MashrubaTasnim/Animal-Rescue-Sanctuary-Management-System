<?php
session_start();
include 'db_config.php';

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'rescuer' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$rescuer_id = $_SESSION['user_id'];
$rescuer_name = $_SESSION['full_name'] ?? 'Rescuer';

// ── FILTER PARAMS ─────────────────────────────────────────────────────────────
$f_sos_species   = trim($_GET['f_sos_species']   ?? '');
$f_sos_location  = trim($_GET['f_sos_location']  ?? '');
$f_cli_species   = trim($_GET['f_cli_species']   ?? '');
$f_cli_location  = trim($_GET['f_cli_location']  ?? '');
$f_act_species   = trim($_GET['f_act_species']   ?? '');
$f_act_location  = trim($_GET['f_act_location']  ?? '');
$f_his_species   = trim($_GET['f_his_species']   ?? '');
$f_his_location  = trim($_GET['f_his_location']  ?? '');
$f_his_from      = trim($_GET['f_his_from']      ?? '');
$f_his_to        = trim($_GET['f_his_to']        ?? '');

function resc($conn, $v) { return $conn->real_escape_string($v); }
function likeC($conn, $col, $val) { return " AND $col LIKE '%" . resc($conn, $val) . "%'"; }

// ── STREET SOS ────────────────────────────────────────────────────────────────
$sos_where = "r.status = 'Approved' AND r.assigned_rescuer IS NULL AND (r.clinic_name IS NULL OR r.clinic_name = '')";
if ($f_sos_species  !== '') $sos_where .= likeC($conn, 'r.species',  $f_sos_species);
if ($f_sos_location !== '') $sos_where .= likeC($conn, 'r.location', $f_sos_location);
$sos_query = "SELECT r.*, u.full_name FROM rescues r LEFT JOIN users u ON r.user_id = u.id WHERE $sos_where ORDER BY CAST(r.severity_score AS UNSIGNED) DESC, r.created_at DESC";
$sos_results = $conn->query($sos_query);

// ── CLINIC TRANSFERS ──────────────────────────────────────────────────────────
$cli_where = "r.status = 'Approved' AND r.clinic_name IS NOT NULL AND r.clinic_name != '' AND r.assigned_rescuer IS NULL";
if ($f_cli_species  !== '') $cli_where .= likeC($conn, 'r.species',    $f_cli_species);
if ($f_cli_location !== '') $cli_where .= likeC($conn, 'r.clinic_name', $f_cli_location);
$clinic_query = "SELECT r.*, u.full_name, v.address AS clinic_address FROM rescues r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN vet_clinics v ON r.clinic_name = v.name WHERE $cli_where ORDER BY r.created_at DESC";
$clinic_results = $conn->query($clinic_query);

// ── ACTIVE CASES ──────────────────────────────────────────────────────────────
$act_where = "r.status = 'In Progress' AND r.assigned_rescuer = $rescuer_id";
if ($f_act_species  !== '') $act_where .= likeC($conn, 'r.species',  $f_act_species);
if ($f_act_location !== '') $act_where .= likeC($conn, 'r.location', $f_act_location);
$active_query = "SELECT r.*, u.full_name FROM rescues r LEFT JOIN users u ON r.user_id = u.id WHERE $act_where ORDER BY r.created_at DESC";
$active_results = $conn->query($active_query);

// ── COMPLETED COUNT (unfiltered for stat card) ────────────────────────────────
$total_done_query = "SELECT id FROM rescues WHERE assigned_rescuer = $rescuer_id AND status = 'Resolved'";
$total_done_count = $conn->query($total_done_query)->num_rows;

// ── MISSION HISTORY (filtered) ────────────────────────────────────────────────
$his_where = "r.assigned_rescuer = $rescuer_id AND r.status = 'Resolved'";
if ($f_his_species  !== '') $his_where .= likeC($conn, 'r.species',  $f_his_species);
if ($f_his_location !== '') $his_where .= likeC($conn, 'r.location', $f_his_location);
if ($f_his_from     !== '') $his_where .= " AND DATE(r.resolved_at) >= '" . resc($conn, $f_his_from) . "'";
if ($f_his_to       !== '') $his_where .= " AND DATE(r.resolved_at) <= '" . resc($conn, $f_his_to)   . "'";
$his_query   = "SELECT r.* FROM rescues r WHERE $his_where ORDER BY r.resolved_at DESC";
$his_results = $conn->query($his_query);

// ── TRIAGE THRESHOLDS ─────────────────────────────────────────────────────────
$low_t = (int) setting('triage_low_threshold', '2');
$mid_t = (int) setting('triage_medium_threshold', '3');

$modal_output = "";

function getSeverityColor($score, $low_t, $mid_t) {
    $score = (int)$score;
    if ($score > $mid_t) return "#ef4444";
    if ($score > $low_t) return "#f97316";
    return "#22c55e";
}

function getSeverityLabel($score, $low_t, $mid_t) {
    $score = (int)$score;
    if ($score > $mid_t) return "Critical";
    if ($score > $low_t) return "Moderate";
    return "Stable";
}

function renderMediaAndModal($row, &$modal_output, $passed_addr = null) {
    $path        = !empty($row['media_path']) ? $row['media_path'] : 'assets/no-image.jpg';
    $id          = $row['id'];
    $desc        = htmlspecialchars($row['description'] ?? '');
    $loc         = htmlspecialchars($row['location'] ?? '');
    $species     = htmlspecialchars($row['species'] ?? 'Unknown');
    $clinic_name = htmlspecialchars($row['clinic_name'] ?? '');
    $created     = !empty($row['created_at'])  ? date('d M Y, h:i A', strtotime($row['created_at']))  : '—';
    $resolved    = !empty($row['resolved_at']) ? date('d M Y, h:i A', strtotime($row['resolved_at'])) : null;
    $clinic_info = !empty($clinic_name) ? "<p class='mb-1' style='color:#3b82f6;'><strong>Clinic:</strong> {$clinic_name}</p>" : "";
    $addr_info   = !empty($passed_addr) ? "<p class='mb-1 text-muted'><strong>Clinic Address:</strong> " . htmlspecialchars($passed_addr) . "</p>" : "";
    $resolved_line = $resolved ? "<p class='mb-1 small'><i class='fas fa-check-circle me-1' style='color:#22c55e;'></i><strong>Resolved:</strong> {$resolved}</p>" : "";

    $modal_output .= "
    <div class='modal fade' id='infoModal{$id}' tabindex='-1' aria-hidden='true'>
        <div class='modal-dialog modal-dialog-centered'>
            <div class='modal-content rq-modal-content'>
                <div class='rq-modal-header'>
                    <span class='rq-modal-title'><i class='fas fa-paw me-2'></i>Rescue Info #<strong>{$id}</strong></span>
                    <button type='button' class='rq-modal-close' data-bs-dismiss='modal' aria-label='Close'>&times;</button>
                </div>
                <div class='rq-modal-body'>
                    <img src='{$path}' class='rq-modal-img' alt='rescue media'>
                    <div class='rq-modal-details'>
                        <p class='mb-2'><strong>Species:</strong> <span class='rq-species-badge'>{$species}</span></p>
                        <p class='mb-2 small'><i class='fas fa-map-marker-alt me-1' style='color:#ffc107;'></i><strong>Location:</strong> {$loc}</p>
                        {$clinic_info}
                        {$addr_info}
                        <p class='mb-1 small'><i class='fas fa-calendar-plus me-1' style='color:#94a3b8;'></i><strong>Reported:</strong> {$created}</p>
                        {$resolved_line}
                        <hr style='border-color:rgba(255,239,194,0.1); margin:12px 0;'>
                        <p class='small' style='color:rgba(255,239,194,0.6); text-align:justify;'><strong style='color:rgba(255,239,194,0.85);'>Notes:</strong><br>{$desc}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>";

    return "<img src='{$path}' class='rescue-thumb' data-bs-toggle='modal' data-bs-target='#infoModal{$id}' title='Click to view details'>";
}

// ── FILTER LABEL BUILDER (for print headers) ──────────────────────────────────
function buildFilterLabel(array $parts): string {
    return implode(' · ', array_filter($parts));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescuer HQ | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=1.1">

    <style>
        /* ===== BASE ===== */
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 110px; }
        body { background-color: #f0f2f7; font-family: 'Montserrat', sans-serif; min-height: 100vh; margin: 0; }

        /* ===== HERO ===== */
        .hq-header {
            margin-top: -1px;
            background: linear-gradient(135deg, rgba(6,10,22,0.95) 0%, rgba(10,19,41,0.90) 100%),
                        url('https://images.unsplash.com/photo-1541781774459-bb2af2f05b55?q=80&w=2060&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            padding: 90px 0 130px;
            color: white;
            text-align: center;
            border-bottom: 3px solid rgba(255,193,7,0.4);
            position: relative;
            overflow: hidden;
        }
        .hq-header::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 380px; height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,193,7,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .hq-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 60px;
            background: linear-gradient(to bottom, transparent, #f0f2f7);
            pointer-events: none;
        }
        .hq-header .container { position: relative; z-index: 2; }
        .hq-eyebrow {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,193,7,0.7);
            display: block;
            margin-bottom: 12px;
        }
        .hq-header h1 {
            font-family: 'Cinzel', serif !important;
            font-weight: 700 !important;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem) !important;
            color: #ffffff !important;
            letter-spacing: 3px !important;
            margin: 0 0 12px !important;
            line-height: 1.2 !important;
        }
        .hq-header p {
            font-size: 0.82rem;
            font-weight: 500;
            color: rgba(255,239,194,0.5);
            letter-spacing: 0.5px;
            margin: 0;
        }
        .hq-divider {
            width: 50px; height: 2px;
            background: linear-gradient(90deg, #ffc107, transparent);
            margin: 16px auto;
            border-radius: 2px;
        }

        /* ===== STAT CARDS (overlap) ===== */
        .stats-overlap {
            margin-top: -80px !important;
            position: relative;
            z-index: 5;
        }
        .nav-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 16px 20px;
            transition: transform 0.3s cubic-bezier(0.165,0.84,0.44,1), box-shadow 0.3s ease;
            box-shadow: 0 8px 28px rgba(10,19,41,0.1);
            text-decoration: none !important;
            display: block;
            border-bottom: 3px solid transparent;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .nav-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 18px 18px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .nav-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(10,19,41,0.14); }
        .nav-card:hover::before { opacity: 1; }
        .nav-card-sos::before, .nav-card-sos { border-bottom-color: #ef4444 !important; }
        .nav-card-clinic::before, .nav-card-clinic { border-bottom-color: #0646ae !important; }
        .nav-card-active::before, .nav-card-active { border-bottom-color: #0b8638 !important; }
        .nav-card-success::before, .nav-card-success { border-bottom-color: #027d92 !important; }
        .nav-card .nc-icon {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.1rem;
        }
        .nc-icon-sos     { background: rgba(239,68,68,0.1);  color: #ef4444; }
        .nc-icon-clinic  { background: rgba(59,130,246,0.1); color: #0646ae; }
        .nc-icon-active  { background: rgba(34,197,94,0.1);  color: #0b8638; }
        .nc-icon-success { background: rgba(6,182,212,0.1);  color: #027d92; }
        .nav-card .nc-label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #94a3b8;
            display: block;
            margin-bottom: 6px;
        }
        .nav-card .nc-count {
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin: 0;
        }
        .nc-count-sos    { color: #ef4444; }
        .nc-count-clinic { color: #0646ae; }
        .nc-count-active { color: #0b8638; }
        .nc-count-success{ color: #027d92; }

        /* ===== LOG OWN RESCUE BAR ===== */
        .log-own-rescue-bar {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(10,19,41,0.08);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-left: 4px solid #ffc107;
            margin-bottom: 8px;
        }
        .log-own-rescue-bar .lor-text {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .lor-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: rgba(255,193,7,0.12);
            color: #b8860b;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .lor-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0a1329;
            margin: 0 0 2px;
            letter-spacing: 0.3px;
        }
        .lor-sub {
            font-size: 0.68rem;
            color: #94a3b8;
            margin: 0;
            font-weight: 500;
        }
        .btn-log-rescue {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            letter-spacing: 1.5px !important;
            text-transform: uppercase !important;
            padding: 10px 22px !important;
            border-radius: 10px !important;
            border: none !important;
            cursor: pointer;
            background: #0a1329 !important;
            color: #ffc107 !important;
            box-shadow: 0 3px 12px rgba(10,19,41,0.2) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-log-rescue:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(10,19,41,0.3) !important;
        }

        /* ===== SECTION LABEL ===== */
        .section-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            margin-top: 48px;
        }
        .section-label-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        .sl-sos    { background: rgba(239,68,68,0.12);  color: #ef4444; }
        .sl-clinic { background: rgba(59,130,246,0.12); color: #0646ae; }
        .sl-active { background: rgba(34,197,94,0.12);  color: #0b8638; }
        .sl-success{ background: rgba(6,182,212,0.12);  color: #027d92; }
        .section-label .sl-text {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* ===== SECTION CARD ===== */
        .section-card {
            border: 1px solid rgba(255,255,255,0.9) !important;
            border-radius: 20px !important;
            box-shadow: 0 4px 20px rgba(10,19,41,0.07) !important;
            margin-bottom: 0;
            background: #ffffff;
            overflow: hidden;
        }
        .card-header-custom {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: #fff;
        }
        .header-sos    { border-top: 3px solid #ef4444; }
        .header-clinic { border-top: 3px solid #0646ae; }
        .header-active { border-top: 3px solid #0b8638; }
        .header-success{ border-top: 3px solid #027d92; }
        .card-header-custom h5 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.85rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            margin: 0 !important;
        }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: #fafbfd;
            border-bottom: 1px solid #f1f5f9;
            padding: 12px 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .fb-group { display: flex; flex-direction: column; gap: 4px; }
        .fb-label {
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .filter-bar input, .filter-bar select {
            padding: 7px 11px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 0.78rem;
            font-family: 'Montserrat', sans-serif;
            background: #fff;
            color: #0a1329;
            transition: border-color 0.2s;
            min-width: 130px;
        }
        .filter-bar input:focus, .filter-bar select:focus {
            outline: none;
            border-color: #0891b2;
            box-shadow: 0 0 0 3px rgba(8,145,178,0.08);
        }
        .result-count {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            align-self: center;
            margin-left: auto;
            white-space: nowrap;
        }
        .result-count span { color: #0a1329; }

        /* ===== BADGE ===== */
        .rq-badge {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .rq-badge-sos     { background: rgba(239,68,68,0.1);  color: #ef4444;  border: 1px solid rgba(239,68,68,0.2); }
        .rq-badge-clinic  { background: rgba(59,130,246,0.1); color: #0646ae;  border: 1px solid rgba(59,130,246,0.2); }
        .rq-badge-active  { background: rgba(34,197,94,0.1);  color: #0b8638;  border: 1px solid rgba(34,197,94,0.2); }
        .rq-badge-success { background: rgba(6,182,212,0.1);  color: #027d92;  border: 1px solid rgba(6,182,212,0.2); }
        .rq-badge-resolved{ background: rgba(34,197,94,0.08); color: #16a34a;  border: 1px solid rgba(34,197,94,0.2); }

        /* ===== TABLE ===== */
        .table { margin: 0 !important; }
        .table > tbody > tr {
            border-bottom: 1px solid #f1f5f9 !important;
            transition: background 0.2s ease;
        }
        .table > tbody > tr:last-child { border-bottom: none !important; }
        .table > tbody > tr:hover { background: #fafbfd; }
        .table td {
            padding: 16px 20px !important;
            vertical-align: middle !important;
            border: none !important;
        }

        /* ===== DATE BADGE ===== */
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.62rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 3px;
        }
        .date-badge i { font-size: 0.58rem; }

        /* ===== THUMBNAIL ===== */
        .rescue-thumb {
            width: 72px;
            height: 58px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            border: 2px solid #f1f5f9;
            transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), border-color 0.2s ease;
        }
        .rescue-thumb:hover {
            transform: scale(1.08);
            border-color: #ffc107;
        }

        /* ===== ROW TEXT ===== */
        .row-species     { font-size: 0.82rem; font-weight: 700; color: #0a1329; margin-bottom: 3px; }
        .row-location    { font-size: 0.72rem; font-weight: 500; color: #94a3b8; }
        .row-clinic-name { font-size: 0.72rem; font-weight: 600; color: #0646ae; }

        /* ===== ACTION BUTTONS ===== */
        .btn-deploy {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            letter-spacing: 1.5px !important;
            text-transform: uppercase !important;
            padding: 8px 18px !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
            white-space: nowrap;
        }
        .btn-deploy:hover  { transform: translateY(-2px); }
        .btn-deploy:active { transform: scale(0.97); }
        .btn-deploy-sos    { background: #0a1329 !important; color: #ffc107 !important; box-shadow: 0 3px 10px rgba(10,19,41,0.2) !important; }
        .btn-deploy-clinic { background: #0646ae !important; color: #fff !important;    box-shadow: 0 3px 10px rgba(59,130,246,0.25) !important; }
        .btn-deploy-active { background: #0b8638 !important; color: #fff !important;    box-shadow: 0 3px 10px rgba(34,197,94,0.25) !important; }
        .btn-deploy-sos:hover    { box-shadow: 0 6px 18px rgba(10,19,41,0.3) !important; }
        .btn-deploy-clinic:hover { box-shadow: 0 6px 18px rgba(59,130,246,0.35) !important; }
        .btn-deploy-active:hover { box-shadow: 0 6px 18px rgba(34,197,94,0.35) !important; }

        /* ===== PRINT BUTTON ===== */
        .btn-print-rq {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 7px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-print-rq:hover { background: #0a1329; color: #fff; border-color: #0a1329; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            padding: 50px 0 !important;
            text-align: center !important;
            color: #cbd5e1 !important;
        }
        .empty-state i    { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: 0.4; }
        .empty-state span { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.5px; }

        /* ===== MODAL ===== */
        .rq-modal-content {
            border: none !important;
            border-radius: 20px !important;
            overflow: hidden;
            background: #0d1b3e !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5) !important;
            border: 1px solid rgba(255,239,194,0.1) !important;
        }
        .rq-modal-header {
            background: #080f22;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,239,194,0.08);
        }
        .rq-modal-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #ffefc2;
            text-transform: uppercase;
        }
        .rq-modal-close {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(255,239,194,0.07);
            border: none;
            color: rgba(255,239,194,0.5);
            cursor: pointer;
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s ease, color 0.2s ease;
            line-height: 1;
        }
        .rq-modal-close:hover { background: rgba(255,100,100,0.15); color: #ff7070; }
        .rq-modal-body { padding: 20px; }
        .rq-modal-img {
            width: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid rgba(255,239,194,0.08);
        }
        .rq-modal-details { font-family: 'Montserrat', sans-serif; font-size: 0.8rem; color: rgba(255,239,194,0.75); }
        .rq-modal-details strong { color: rgba(255,239,194,0.9); }
        .rq-species-badge {
            background: rgba(255,193,7,0.15);
            color: #ffc107;
            border: 1px solid rgba(255,193,7,0.2);
            border-radius: 6px;
            padding: 2px 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* ===== OWN RESCUE FORM ===== */
        .own-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255,239,194,0.5);
            margin-bottom: 6px;
        }
        .own-input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,239,194,0.12);
            border-radius: 10px;
            padding: 10px 14px;
            color: rgba(255,239,194,0.9);
            font-family: 'Montserrat', sans-serif;
            font-size: 0.8rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .own-input::placeholder { color: rgba(255,239,194,0.25); }
        .own-input:focus { border-color: rgba(255,193,7,0.45); }
        .photo-upload-zone {
            border: 2px dashed rgba(255,239,194,0.18);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
            position: relative;
        }
        .photo-upload-zone:hover {
            border-color: rgba(255,193,7,0.4);
            background: rgba(255,193,7,0.04);
        }
        .photo-upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .photo-upload-zone .puz-icon  { font-size: 1.6rem; color: rgba(255,193,7,0.4); display: block; margin-bottom: 8px; }
        .photo-upload-zone .puz-text  { font-size: 0.72rem; font-weight: 600; color: rgba(255,239,194,0.4); letter-spacing: 0.5px; }
        .photo-upload-zone .puz-hint  { font-size: 0.62rem; color: rgba(255,239,194,0.25); margin-top: 4px; }
        #or_preview {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(255,239,194,0.1);
            display: none;
            margin-top: 10px;
        }

        /* ===== STAGGER ===== */
        .rq-section {
            opacity: 0;
            transform: translateY(20px);
            animation: rq-rise 0.45s ease forwards;
        }
        .rq-section:nth-child(1) { animation-delay: 0.05s; }
        .rq-section:nth-child(2) { animation-delay: 0.15s; }
        .rq-section:nth-child(3) { animation-delay: 0.25s; }
        .rq-section:nth-child(4) { animation-delay: 0.35s; }
        @keyframes rq-rise { to { opacity: 1; transform: translateY(0); } }
        .stat-col {
            opacity: 0;
            transform: translateY(24px);
            animation: rq-rise 0.4s ease forwards;
        }
        .stat-col:nth-child(1) { animation-delay: 0.05s; }
        .stat-col:nth-child(2) { animation-delay: 0.12s; }
        .stat-col:nth-child(3) { animation-delay: 0.19s; }
        .stat-col:nth-child(4) { animation-delay: 0.26s; }

        /* ===== PRINT ===== */
        .print-only { display: none; }
        .no-print   { }

        @media print {
            body { background: #fff !important; font-size: 11pt; margin: 0; }

            /* Hide chrome and UI elements */
            .hq-header, .stats-overlap, nav, .navbar, footer,
            .btn-deploy, .btn-log-rescue, .btn-print-rq,
            .filter-bar, .section-label, .log-own-rescue-bar,
            .no-print, [class*="chat"], .modal { display: none !important; }

            /* Hide the 3 NON-target sections - only hide others, never hide all */
            body.printing-sos    #clinic-anchor,
            body.printing-sos    #active-anchor,
            body.printing-sos    #success-anchor { display: none !important; }

            body.printing-clinic #sos-anchor,
            body.printing-clinic #active-anchor,
            body.printing-clinic #success-anchor { display: none !important; }

            body.printing-active #sos-anchor,
            body.printing-active #clinic-anchor,
            body.printing-active #success-anchor { display: none !important; }

            body.printing-history #sos-anchor,
            body.printing-history #clinic-anchor,
            body.printing-history #active-anchor { display: none !important; }

            /* Section card cleanup */
            .section-card { box-shadow: none !important; border: 1px solid #ccc !important; border-radius: 0 !important; }
            .table > tbody > tr:hover { background: transparent !important; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            .container { max-width: 100% !important; padding: 10px !important; }

            /* Fix animation - sections must be fully visible when printing */
            .rq-section { opacity: 1 !important; transform: none !important; animation: none !important; }

            /* Show matching print-only header */
            body.printing-sos     .phdr-sos,
            body.printing-clinic  .phdr-clinic,
            body.printing-active  .phdr-active,
            body.printing-history .phdr-history { display: block !important; }

            .print-only-header {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                padding-bottom: 12px;
                margin-bottom: 18px;
                border-bottom: 2px solid #0a1329;
            }
            .print-only-header h3 { font-size: 14pt; font-weight: 800; color: #0a1329; margin: 0; font-family: 'Montserrat', sans-serif; }
            .print-meta    { font-size: 9pt; color: #64748b; text-align: right; }
            .print-filters { font-size: 8pt; color: #94a3b8; margin-top: 3px; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ===== HERO ===== -->
<header class="hq-header">
    <div class="container">
        <span class="hq-eyebrow">Rescuer Portal</span>
        <h1>Rescue Command Center</h1>
        <div class="hq-divider"></div>
        <p>Heartbeat Heaven Animal Welfare Foundation</p>
    </div>
</header>

<!-- ===== STAT CARDS ===== -->
<div class="container stats-overlap">
    <div class="row g-4 mb-4 justify-content-center">
        <div class="col-md-3 col-6 stat-col">
            <a href="#sos-anchor" class="nav-card nav-card-sos">
                <div class="nc-icon nc-icon-sos"><i class="fas fa-broadcast-tower"></i></div>
                <span class="nc-label">Street SOS</span>
                <p class="nc-count nc-count-sos"><?= $sos_results->num_rows ?></p>
            </a>
        </div>
        <div class="col-md-3 col-6 stat-col">
            <a href="#clinic-anchor" class="nav-card nav-card-clinic">
                <div class="nc-icon nc-icon-clinic"><i class="fas fa-hospital"></i></div>
                <span class="nc-label">Clinics</span>
                <p class="nc-count nc-count-clinic"><?= $clinic_results->num_rows ?></p>
            </a>
        </div>
        <div class="col-md-3 col-6 stat-col">
            <a href="#active-anchor" class="nav-card nav-card-active">
                <div class="nc-icon nc-icon-active"><i class="fas fa-user-shield"></i></div>
                <span class="nc-label">My Active</span>
                <p class="nc-count nc-count-active"><?= $active_results->num_rows ?></p>
            </a>
        </div>
        <div class="col-md-3 col-6 stat-col">
            <a href="#success-anchor" class="nav-card nav-card-success">
                <div class="nc-icon nc-icon-success"><i class="fas fa-medal"></i></div>
                <span class="nc-label">My Success</span>
                <p class="nc-count nc-count-success"><?= $total_done_count ?></p>
            </a>
        </div>
    </div>

    <!-- ===== LOG OWN RESCUE BAR ===== -->
    <div class="log-own-rescue-bar mb-5">
        <div class="lor-text">
            <div class="lor-icon"><i class="fas fa-paw"></i></div>
            <div>
                <p class="lor-title">Log Your Own Rescue</p>
                <p class="lor-sub">Report an independent rescue not triggered by any SOS or clinic request</p>
            </div>
        </div>
        <button class="btn-log-rescue" data-bs-toggle="modal" data-bs-target="#ownRescueModal">
            <i class="fas fa-plus me-1"></i> Log Rescue
        </button>
    </div>

    <!-- ===== STREET SOS ===== -->
    <div id="sos-anchor" class="rq-section">

        <!-- Print-only header -->
        <div class="print-only phdr-sos">
            <div class="print-only-header">
                <h3>Street SOS — <?= htmlspecialchars($rescuer_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Rescue Command</div>
                    <?php $lbl = buildFilterLabel([$f_sos_species ? 'Species: '.$f_sos_species : '', $f_sos_location ? 'Location: '.$f_sos_location : '']); if ($lbl): ?>
                    <div class="print-filters">Filters: <?= htmlspecialchars($lbl) ?></div>
                    <?php endif; ?>
                    <div class="pds" data-section="sos"></div>
                </div>
            </div>
        </div>

        <div class="section-label">
            <div class="section-label-icon sl-sos"><i class="fas fa-broadcast-tower"></i></div>
            <span class="sl-text">Approved Street SOS</span>
        </div>
        <div class="section-card">
            <div class="card-header-custom header-sos">
                <h5 class="text-dark"><i class="fas fa-broadcast-tower me-2" style="color:#ef4444;"></i>Approved Street SOS</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge rq-badge-sos"><?= $sos_results->num_rows ?> Verified</span>
                    <button class="btn-print-rq" onclick="printSection('sos')"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="#sos-anchor" class="filter-bar">
                <?php foreach (['f_cli_species','f_cli_location','f_act_species','f_act_location','f_his_species','f_his_location','f_his_from','f_his_to'] as $k): ?>
                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k] ?? '') ?>">
                <?php endforeach; ?>
                <div class="fb-group">
                    <span class="fb-label">Species</span>
                    <input type="text" name="f_sos_species" placeholder="e.g. Dog, Cat" value="<?= htmlspecialchars($f_sos_species) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Location</span>
                    <input type="text" name="f_sos_location" placeholder="Area or landmark" value="<?= htmlspecialchars($f_sos_location) ?>">
                </div>
                <div class="d-flex gap-2 align-self-end">
                    <button type="submit" class="btn-deploy btn-deploy-sos" style="padding:8px 16px !important;"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>#sos-anchor" class="btn-deploy btn-deploy-sos" style="text-decoration:none;padding:8px 12px !important;"><i class="fas fa-times"></i></a>
                </div>
                <div class="result-count">
                    <span><?= $sos_results->num_rows ?></span> result<?= $sos_results->num_rows != 1 ? 's' : '' ?>
                    <?= ($f_sos_species || $f_sos_location) ? ' &nbsp;<span style="color:#ef4444;">— filtered</span>' : '' ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php if ($sos_results->num_rows > 0): while ($row = $sos_results->fetch_assoc()):
                            $s_score = (int)($row['severity_score'] ?? 0);
                            $s_color = getSeverityColor($s_score, $low_t, $mid_t);
                            $s_label = getSeverityLabel($s_score, $low_t, $mid_t);
                        ?>
                        <tr>
                            <td style="width:90px;"><?= renderMediaAndModal($row, $modal_output) ?></td>
                            <td>
                                <div class="d-flex align-items-center mb-1">
                                    <div class="row-species me-2"><?= htmlspecialchars($row['species']) ?></div>
                                    <span style="height:9px;width:9px;border-radius:50%;display:inline-block;background-color:<?= $s_color ?>;box-shadow:0 0 6px <?= $s_color ?>;" title="Severity: <?= $s_label ?> (<?= $s_score ?>)"></span>
                                    <span style="font-size:0.6rem;font-weight:700;color:<?= $s_color ?>;margin-left:5px;"><?= $s_label ?></span>
                                </div>
                                <div class="row-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['location']) ?></div>
                                <span class="date-badge"><i class="fas fa-calendar-plus"></i><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td class="text-end no-print">
                                <button class="btn-deploy btn-deploy-sos" onclick="updateStatus(<?= $row['id'] ?>, 'In Progress', this)">
                                    <i class="fas fa-rocket me-1"></i>Deploy
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="empty-state"><i class="fas fa-street-view"></i><span><?= ($f_sos_species || $f_sos_location) ? 'No results match your filter.' : 'No approved reports right now.' ?></span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== CLINIC TRANSFERS ===== -->
    <div id="clinic-anchor" class="rq-section">

        <div class="print-only phdr-clinic">
            <div class="print-only-header">
                <h3>Clinic Transfers — <?= htmlspecialchars($rescuer_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Rescue Command</div>
                    <?php $lbl = buildFilterLabel([$f_cli_species ? 'Species: '.$f_cli_species : '', $f_cli_location ? 'Clinic: '.$f_cli_location : '']); if ($lbl): ?>
                    <div class="print-filters">Filters: <?= htmlspecialchars($lbl) ?></div>
                    <?php endif; ?>
                    <div class="pds" data-section="clinic"></div>
                </div>
            </div>
        </div>

        <div class="section-label">
            <div class="section-label-icon sl-clinic"><i class="fas fa-hospital"></i></div>
            <span class="sl-text">Clinic Transfers</span>
        </div>
        <div class="section-card">
            <div class="card-header-custom header-clinic">
                <h5 style="color:#3b82f6;"><i class="fas fa-hospital me-2"></i>Clinic Transfers</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge rq-badge-clinic"><?= $clinic_results->num_rows ?> Available</span>
                    <button class="btn-print-rq" onclick="printSection('clinic')"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>

            <form method="GET" action="#clinic-anchor" class="filter-bar">
                <?php foreach (['f_sos_species','f_sos_location','f_act_species','f_act_location','f_his_species','f_his_location','f_his_from','f_his_to'] as $k): ?>
                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k] ?? '') ?>">
                <?php endforeach; ?>
                <div class="fb-group">
                    <span class="fb-label">Species</span>
                    <input type="text" name="f_cli_species" placeholder="e.g. Dog, Cat" value="<?= htmlspecialchars($f_cli_species) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Clinic Name</span>
                    <input type="text" name="f_cli_location" placeholder="Clinic name" value="<?= htmlspecialchars($f_cli_location) ?>">
                </div>
                <div class="d-flex gap-2 align-self-end">
                    <button type="submit" class="btn-deploy btn-deploy-clinic" style="padding:8px 16px !important;"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>#clinic-anchor" class="btn-deploy btn-deploy-clinic" style="text-decoration:none;padding:8px 12px !important;"><i class="fas fa-times"></i></a>
                </div>
                <div class="result-count">
                    <span><?= $clinic_results->num_rows ?></span> result<?= $clinic_results->num_rows != 1 ? 's' : '' ?>
                    <?= ($f_cli_species || $f_cli_location) ? ' &nbsp;<span style="color:#0646ae;">— filtered</span>' : '' ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php if ($clinic_results->num_rows > 0): while ($row = $clinic_results->fetch_assoc()): ?>
                        <tr>
                            <td style="width:90px;"><?= renderMediaAndModal($row, $modal_output, $row['clinic_address']) ?></td>
                            <td>
                                <div class="row-species"><?= htmlspecialchars($row['species']) ?></div>
                                <div class="row-clinic-name"><i class="fas fa-hospital-alt me-1"></i><?= htmlspecialchars($row['clinic_name']) ?></div>
                                <?php if (!empty($row['clinic_address'])): ?>
                                <div class="row-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['clinic_address']) ?></div>
                                <?php endif; ?>
                                <span class="date-badge"><i class="fas fa-calendar-plus"></i><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td class="text-end no-print">
                                <button class="btn-deploy btn-deploy-clinic" onclick="updateStatus(<?= $row['id'] ?>, 'In Progress', this)">
                                    <i class="fas fa-truck-medical me-1"></i>Pickup
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="empty-state"><i class="fas fa-notes-medical"></i><span><?= ($f_cli_species || $f_cli_location) ? 'No results match your filter.' : 'No clinic transfers pending.' ?></span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== ACTIVE OPERATIONS ===== -->
    <div id="active-anchor" class="rq-section">

        <div class="print-only phdr-active">
            <div class="print-only-header">
                <h3>Active Operations — <?= htmlspecialchars($rescuer_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Rescue Command</div>
                    <?php $lbl = buildFilterLabel([$f_act_species ? 'Species: '.$f_act_species : '', $f_act_location ? 'Location: '.$f_act_location : '']); if ($lbl): ?>
                    <div class="print-filters">Filters: <?= htmlspecialchars($lbl) ?></div>
                    <?php endif; ?>
                    <div class="pds" data-section="active"></div>
                </div>
            </div>
        </div>

        <div class="section-label">
            <div class="section-label-icon sl-active"><i class="fas fa-user-shield"></i></div>
            <span class="sl-text">Active Operations</span>
        </div>
        <div class="section-card">
            <div class="card-header-custom header-active">
                <h5 style="color:#22c55e;"><i class="fas fa-user-shield me-2"></i>Active Operations</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge rq-badge-active"><?= $active_results->num_rows ?> Active</span>
                    <button class="btn-print-rq" onclick="printSection('active')"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>

            <form method="GET" action="#active-anchor" class="filter-bar">
                <?php foreach (['f_sos_species','f_sos_location','f_cli_species','f_cli_location','f_his_species','f_his_location','f_his_from','f_his_to'] as $k): ?>
                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k] ?? '') ?>">
                <?php endforeach; ?>
                <div class="fb-group">
                    <span class="fb-label">Species</span>
                    <input type="text" name="f_act_species" placeholder="e.g. Dog, Cat" value="<?= htmlspecialchars($f_act_species) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Location</span>
                    <input type="text" name="f_act_location" placeholder="Area or landmark" value="<?= htmlspecialchars($f_act_location) ?>">
                </div>
                <div class="d-flex gap-2 align-self-end">
                    <button type="submit" class="btn-deploy btn-deploy-active" style="padding:8px 16px !important;"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>#active-anchor" class="btn-deploy btn-deploy-active" style="text-decoration:none;padding:8px 12px !important;"><i class="fas fa-times"></i></a>
                </div>
                <div class="result-count">
                    <span><?= $active_results->num_rows ?></span> result<?= $active_results->num_rows != 1 ? 's' : '' ?>
                    <?= ($f_act_species || $f_act_location) ? ' &nbsp;<span style="color:#0b8638;">— filtered</span>' : '' ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php if ($active_results->num_rows > 0): while ($row = $active_results->fetch_assoc()): ?>
                        <tr>
                            <td style="width:90px;"><?= renderMediaAndModal($row, $modal_output) ?></td>
                            <td>
                                <div class="row-species" style="color:#22c55e;"><?= htmlspecialchars($row['species']) ?></div>
                                <div class="row-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['location']) ?></div>
                                <span class="date-badge"><i class="fas fa-calendar-plus"></i>Deployed: <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td class="text-end no-print">
                                <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                                    <input type="number" id="fc_<?= $row['id'] ?>" placeholder="Field cost ৳"
                                           style="width:130px;padding:5px 8px;border-radius:7px;border:1px solid #e2e8f0;font-size:0.75rem;">
                                    <button class="btn-deploy btn-deploy-active" onclick="completeRescue(<?= $row['id'] ?>, this)">
                                        <i class="fas fa-flag-checkered me-1"></i>Complete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="empty-state"><i class="fas fa-shield-alt"></i><span><?= ($f_act_species || $f_act_location) ? 'No results match your filter.' : 'No active missions.' ?></span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== MISSION HISTORY ===== -->
    <div id="success-anchor" class="rq-section" style="padding-bottom: 60px;">

        <div class="print-only phdr-history">
            <div class="print-only-header">
                <h3>Mission History — <?= htmlspecialchars($rescuer_name) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Heartbeat Heaven · Rescue Command</div>
                    <?php
                    $lbl = buildFilterLabel([
                        $f_his_species  ? 'Species: '.$f_his_species   : '',
                        $f_his_location ? 'Location: '.$f_his_location : '',
                        $f_his_from     ? 'From: '.date('d M Y', strtotime($f_his_from)) : '',
                        $f_his_to       ? 'To: '.date('d M Y', strtotime($f_his_to))     : '',
                    ]);
                    if ($lbl): ?>
                    <div class="print-filters">Filters: <?= htmlspecialchars($lbl) ?></div>
                    <?php endif; ?>
                    <div class="pds" data-section="history"></div>
                </div>
            </div>
        </div>

        <div class="section-label">
            <div class="section-label-icon sl-success"><i class="fas fa-medal"></i></div>
            <span class="sl-text">Mission History</span>
        </div>
        <div class="section-card">
            <div class="card-header-custom header-success">
                <h5 style="color:#06b6d4;"><i class="fas fa-medal me-2"></i>Mission History</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="rq-badge rq-badge-success"><?= $his_results->num_rows ?> Shown / <?= $total_done_count ?> Total</span>
                    <button class="btn-print-rq" onclick="printSection('history')"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>

            <form method="GET" action="#success-anchor" class="filter-bar">
                <?php foreach (['f_sos_species','f_sos_location','f_cli_species','f_cli_location','f_act_species','f_act_location'] as $k): ?>
                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k] ?? '') ?>">
                <?php endforeach; ?>
                <div class="fb-group">
                    <span class="fb-label">Species</span>
                    <input type="text" name="f_his_species" placeholder="e.g. Dog, Cat" value="<?= htmlspecialchars($f_his_species) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Location</span>
                    <input type="text" name="f_his_location" placeholder="Area or landmark" value="<?= htmlspecialchars($f_his_location) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Resolved From</span>
                    <input type="date" name="f_his_from" value="<?= htmlspecialchars($f_his_from) ?>">
                </div>
                <div class="fb-group">
                    <span class="fb-label">Resolved To</span>
                    <input type="date" name="f_his_to" value="<?= htmlspecialchars($f_his_to) ?>">
                </div>
                <div class="d-flex gap-2 align-self-end">
                    <button type="submit" class="btn-deploy" style="background:#027d92 !important;color:#fff !important;padding:8px 16px !important;"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>#success-anchor" class="btn-deploy" style="background:#027d92 !important;color:#fff !important;text-decoration:none;padding:8px 12px !important;"><i class="fas fa-times"></i></a>
                </div>
                <div class="result-count">
                    <span><?= $his_results->num_rows ?></span> result<?= $his_results->num_rows != 1 ? 's' : '' ?>
                    <?= ($f_his_species || $f_his_location || $f_his_from || $f_his_to) ? ' &nbsp;<span style="color:#027d92;">— filtered</span>' : '' ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php
                        $his_results->data_seek(0);
                        if ($his_results->num_rows > 0):
                            while ($row = $his_results->fetch_assoc()):
                        ?>
                        <tr>
                            <td style="width:90px;"><?= renderMediaAndModal($row, $modal_output) ?></td>
                            <td>
                                <div class="row-species" style="color:#06b6d4;"><?= htmlspecialchars($row['species']) ?></div>
                                <div class="row-location"><i class="fas fa-check-circle me-1" style="color:#22c55e;"></i>Mission Accomplished</div>
                                <div class="row-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['location'] ?? '—') ?></div>
                                <span class="date-badge" style="margin-right:4px;">
                                    <i class="fas fa-calendar-plus"></i>
                                    Reported: <?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—' ?>
                                </span>
                                <span class="date-badge" style="background:rgba(22,163,74,0.08);color:#16a34a;border:1px solid rgba(22,163,74,0.2);">
                                    <i class="fas fa-check-circle"></i>
                                    Resolved: <?= !empty($row['resolved_at']) ? date('d M Y, h:i A', strtotime($row['resolved_at'])) : '—' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="rq-badge rq-badge-resolved"><i class="fas fa-check-circle me-1"></i>Resolved</span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="empty-state"><i class="fas fa-award"></i><span><?= ($f_his_species || $f_his_location || $f_his_from || $f_his_to) ? 'No results match your filter.' : 'No completed missions yet.' ?></span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php echo $modal_output; ?>

<!-- ===== LOG OWN RESCUE MODAL ===== -->
<div class="modal fade" id="ownRescueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rq-modal-content">
            <div class="rq-modal-header">
                <span class="rq-modal-title"><i class="fas fa-paw me-2"></i>Log Own Rescue</span>
                <button type="button" class="rq-modal-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="rq-modal-body">
                <p style="font-size:0.75rem; color:rgba(255,239,194,0.5); margin-bottom:18px;">
                    Report a rescue you initiated independently — not from a public SOS or clinic request.
                </p>
                <div class="mb-3">
                    <label class="own-label">Species</label>
                    <input type="text" id="or_species" placeholder="e.g. Dog, Cat, Bird…" class="own-input" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="own-label">Location</label>
                    <input type="text" id="or_location" placeholder="Street, landmark, area…" class="own-input" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="own-label">Description / Notes</label>
                    <textarea id="or_description" rows="3" placeholder="Condition of animal, circumstances, any injuries…"
                              class="own-input" style="resize:vertical;"></textarea>
                </div>
                <div class="mb-4">
                    <label class="own-label">Photo <span style="color:rgba(255,239,194,0.25); font-weight:500; text-transform:none; letter-spacing:0;">(optional)</span></label>
                    <div class="photo-upload-zone" id="or_upload_zone">
                        <input type="file" id="or_photo" accept="image/*" onchange="previewPhoto(this)">
                        <i class="fas fa-camera puz-icon"></i>
                        <div class="puz-text">Click to upload a photo</div>
                        <div class="puz-hint">JPG, PNG or WEBP — max 5MB</div>
                    </div>
                    <img id="or_preview" src="" alt="Preview">
                </div>
                <button class="btn-deploy btn-deploy-active w-100" id="or_submit_btn"
                        onclick="submitOwnRescue()" style="padding:12px !important; font-size:0.7rem !important;">
                    <i class="fas fa-flag me-2"></i>Submit for Review
                </button>
                <div id="or_feedback" style="margin-top:12px; font-size:0.75rem; text-align:center; display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(id, newStatus, btn) {
    if (!confirm("Are you sure you want to proceed?")) return;
    btn.disabled = true;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', newStatus);
    fetch('update_status.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(data => {
        if (data.trim() === "success") { location.reload(); }
        else { alert("Error: " + data); btn.disabled = false; }
    });
}

function completeRescue(id, btn) {
    const cost = document.getElementById('fc_' + id)?.value || 0;
    if (!confirm("Mark mission complete?")) return;
    btn.disabled = true;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', 'Under Review');
    formData.append('field_cost', cost);
    formData.append('cost_note', 'Field rescue operation costs');
    formData.append('set_resolved_at', '1');
    fetch('update_status.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(data => {
        if (data.trim() === "success") { location.reload(); }
        else { alert("Error: " + data); btn.disabled = false; }
    });
}

function printSection(section) {
    // Stamp date/time
    var now     = new Date();
    var opts    = { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
    var dateStr = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    document.querySelectorAll('.pds').forEach(function(el) {
        if (el.getAttribute('data-section') === section) el.textContent = dateStr;
    });

    // Map section name to anchor id
    var anchorMap = { sos: 'sos', clinic: 'clinic', active: 'active', history: 'success' };

    // Remove any previous printing class
    document.body.className = document.body.className.replace(/\bprinting-\S+/g, '').trim();
    document.body.classList.add('printing-' + section);

    setTimeout(function() {
        window.print();
        window.addEventListener('afterprint', function cleanup() {
            document.body.classList.remove('printing-' + section);
            window.removeEventListener('afterprint', cleanup);
        });
    }, 300);
}

// Stamp all on load (for Ctrl+P fallback)
(function() {
    var now     = new Date();
    var opts    = { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
    var dateStr = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    document.querySelectorAll('.pds').forEach(function(el) { el.textContent = dateStr; });
})();

function previewPhoto(input) {
    const preview = document.getElementById('or_preview');
    const zone    = document.getElementById('or_upload_zone');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            zone.style.borderColor = 'rgba(255,193,7,0.5)';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitOwnRescue() {
    const species     = document.getElementById('or_species').value.trim();
    const location    = document.getElementById('or_location').value.trim();
    const description = document.getElementById('or_description').value.trim();
    const photoFile   = document.getElementById('or_photo').files[0];
    const feedback    = document.getElementById('or_feedback');
    const btn         = document.getElementById('or_submit_btn');

    if (!species || !location || !description) {
        feedback.style.display = 'block';
        feedback.style.color   = '#f87171';
        feedback.textContent   = 'Please fill in all required fields.';
        return;
    }
    if (photoFile && photoFile.size > 5 * 1024 * 1024) {
        feedback.style.display = 'block';
        feedback.style.color   = '#f87171';
        feedback.textContent   = 'Photo must be under 5MB.';
        return;
    }
    btn.disabled = true;
    feedback.style.display = 'none';
    const formData = new FormData();
    formData.append('species',     species);
    formData.append('location',    location);
    formData.append('description', description);
    formData.append('rescuer_id',  <?= $rescuer_id ?>);
    if (photoFile) formData.append('photo', photoFile);
    fetch('log_own_rescue.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(data => {
        if (data.trim() === 'success') {
            feedback.style.color   = '#4ade80';
            feedback.textContent   = '✓ Rescue logged successfully. Pending admin review.';
            feedback.style.display = 'block';
            document.getElementById('or_species').value     = '';
            document.getElementById('or_location').value    = '';
            document.getElementById('or_description').value = '';
            document.getElementById('or_photo').value       = '';
            document.getElementById('or_preview').style.display = 'none';
            document.getElementById('or_upload_zone').style.borderColor = '';
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('ownRescueModal')).hide();
                location.reload();
            }, 1800);
        } else {
            feedback.style.color   = '#f87171';
            feedback.textContent   = 'Error: ' + data;
            feedback.style.display = 'block';
            btn.disabled = false;
        }
    });
}
</script>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>