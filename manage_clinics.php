<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// --- LOGIC: ADD NEW CLINIC ---
if (isset($_POST['add_clinic'])) {
    $name        = mysqli_real_escape_string($conn, $_POST['name']);
    $address     = mysqli_real_escape_string($conn, $_POST['address']);
    $phone       = mysqli_real_escape_string($conn, $_POST['phone']);
    $specialty   = mysqli_real_escape_string($conn, $_POST['specialty']);
    $license     = mysqli_real_escape_string($conn, $_POST['license']);
    $lat         = mysqli_real_escape_string($conn, $_POST['latitude']);
    $lng         = mysqli_real_escape_string($conn, $_POST['longitude']);
    $is_24_7     = isset($_POST['is_24_7'])     ? 1 : 0;
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;

$sql = "INSERT INTO vet_clinics (
            name,
            address,
            phone,
            specialty,
            license,
            latitude,
            longitude,
            is_24_7,
            is_verified
        ) VALUES (
            '$name',
            '$address',
            '$phone',
            '$specialty',
            '$license',
            '$lat',
            '$lng',
            $is_24_7,
            $is_verified
        )";

    header("Location: manage_clinics.php?msg=" . ($conn->query($sql) ? 'added' : 'error'));
    exit();
}

// --- LOGIC: DELETE CLINIC ---
if (isset($_GET['delete_id'])) {
    $id          = intval($_GET['delete_id']);
    $return_view = in_array($_GET['view'] ?? '', ['verified','emergency','standard','all'])
                   ? $_GET['view'] : 'verified';
    $conn->query("DELETE FROM vet_clinics WHERE id = $id");
    header("Location: manage_clinics.php?msg=deleted&view=" . $return_view);
    exit();
}

// ── VIEW & FILTER PARAMETERS ──────────────────────────────────────────────────
$view             = $_GET['view']              ?? 'verified';
$search           = trim($_GET['search']       ?? '');
$filter_specialty = trim($_GET['filter_specialty'] ?? '');
$filter_status    = $_GET['filter_status']     ?? ''; // 'verified' | 'pending'
$filter_avail     = $_GET['filter_avail']      ?? ''; // '247' | 'standard'

// ── HELPER ────────────────────────────────────────────────────────────────────
function esc($conn, $v) { return $conn->real_escape_string($v); }

// ── WHERE BUILDER ─────────────────────────────────────────────────────────────
function build_where($conn, $view, $search, $filter_specialty, $filter_status, $filter_avail) {
    $where = "WHERE 1=1";

    // View-level scope
    if ($view === 'verified')  $where .= " AND is_verified = 1";
    if ($view === 'emergency') $where .= " AND is_24_7 = 1";
    if ($view === 'standard')  $where .= " AND is_24_7 = 0";

    // Keyword search
    if ($search) {
        $s = esc($conn, $search);
        $where .= " AND (name LIKE '%$s%' OR address LIKE '%$s%' OR phone LIKE '%$s%' OR specialty LIKE '%$s%')";
    }

    // Specialty filter
    if ($filter_specialty) {
        $sp = esc($conn, $filter_specialty);
        $where .= " AND specialty LIKE '%$sp%'";
    }

    // Verification status filter
    if ($filter_status === 'verified') $where .= " AND is_verified = 1";
    if ($filter_status === 'pending')  $where .= " AND is_verified = 0";

    // Availability filter
    if ($filter_avail === '247')      $where .= " AND is_24_7 = 1";
    if ($filter_avail === 'standard') $where .= " AND is_24_7 = 0";

    return $where;
}

$where        = build_where($conn, $view, $search, $filter_specialty, $filter_status, $filter_avail);
$active_set   = $conn->query("SELECT * FROM vet_clinics $where ORDER BY id DESC");
$result_count = $active_set->num_rows;

// ── SPECIALTY LIST for dropdown (unfiltered, distinct) ────────────────────────
$spec_res    = $conn->query("SELECT DISTINCT specialty FROM vet_clinics WHERE specialty != '' ORDER BY specialty ASC");
$specialties = [];
while ($sr = $spec_res->fetch_assoc()) $specialties[] = $sr['specialty'];

// ── SIDEBAR TOTALS (unfiltered) ───────────────────────────────────────────────
$cnt_all       = $conn->query("SELECT COUNT(*) t FROM vet_clinics")->fetch_assoc()['t'];
$cnt_verified  = $conn->query("SELECT COUNT(*) t FROM vet_clinics WHERE is_verified = 1")->fetch_assoc()['t'];
$cnt_emergency = $conn->query("SELECT COUNT(*) t FROM vet_clinics WHERE is_24_7 = 1")->fetch_assoc()['t'];
$cnt_standard  = $conn->query("SELECT COUNT(*) t FROM vet_clinics WHERE is_24_7 = 0")->fetch_assoc()['t'];

// ── FILTER LABEL FOR PRINT ────────────────────────────────────────────────────
$active_filters = [];
if ($search)           $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_specialty) $active_filters[] = 'Specialty: ' . htmlspecialchars($filter_specialty);
if ($filter_status)    $active_filters[] = 'Status: ' . ucfirst($filter_status);
if ($filter_avail)     $active_filters[] = 'Availability: ' . ($filter_avail === '247' ? '24/7 Emergency' : 'Standard Hours');
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vet Partners | Heartbeat Heaven Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --navy:   #0a1329;
            --gold:   #B8860B;
            --gold-lt:#ffc107;
            --green:  #198754;
            --red:    #dc3545;
            --blue:   #0d6efd;
        }

        body { background: #f4f7fa; font-family: 'Montserrat', sans-serif; margin: 0; }

        /* ── LAYOUT ── */
        .mc-wrapper { display: flex; min-height: calc(100vh - 70px); }

        /* ── SIDEBAR ── */
        .mc-sidebar {
            width: 240px; flex-shrink: 0; background: var(--navy);
            padding: 24px 0; position: sticky; top: 70px;
            height: calc(100vh - 70px); overflow-y: auto;
        }
        .sidebar-head {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 12px;
        }
        .sidebar-head span {
            font-size: 0.58rem; font-weight: 800; letter-spacing: 2.5px;
            text-transform: uppercase; color: rgba(255,193,7,0.6);
            display: block; margin-bottom: 4px;
        }
        .sidebar-head p { color: #fff; font-weight: 700; font-size: 0.88rem; margin: 0; }

        .sidebar-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 20px; text-decoration: none !important;
            color: rgba(255,255,255,0.55); font-size: 0.78rem; font-weight: 600;
            transition: all 0.2s ease; border-left: 3px solid transparent;
        }
        .sidebar-item:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .sidebar-item.active { color: #fff; background: rgba(255,255,255,0.07); border-left-color: var(--gold); }

        .si-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; flex-shrink: 0; background: rgba(255,255,255,0.06);
        }
        .sidebar-item.active .si-icon { background: rgba(184,134,11,0.2); color: var(--gold); }

        .sidebar-badge {
            margin-left: auto; color: #fff;
            font-size: 0.6rem; font-weight: 800; padding: 2px 7px;
            border-radius: 20px; line-height: 1.6; flex-shrink: 0;
        }
        .badge-green { background: var(--green); }
        .badge-red   { background: var(--red); }
        .badge-blue  { background: var(--blue); }
        .badge-grey  { background: #6c757d; }
        .badge-gold  { background: var(--gold); }

        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 10px 20px; }

        .sidebar-add-btn {
            margin: 16px 16px 0; display: block; text-align: center;
            background: rgba(184,134,11,0.15);
            border: 1px solid rgba(184,134,11,0.3);
            border-radius: 12px; padding: 10px;
            color: var(--gold-lt); font-size: 0.72rem; font-weight: 700;
            letter-spacing: 0.5px; text-transform: uppercase;
            text-decoration: none !important; transition: all 0.2s ease;
        }
        .sidebar-add-btn:hover { background: rgba(184,134,11,0.28); color: #fff; }

        /* ── MAIN CONTENT ── */
        .mc-content { flex: 1; padding: 32px 28px 60px; min-width: 0; }

        /* ── PAGE HEADER ── */
        .mc-page-header {
            margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: flex-start; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
        }
        .mc-page-header h5 { font-weight: 800; font-size: 1rem; color: var(--navy); margin: 0 0 4px; }
        .mc-page-header p  { font-size: 0.75rem; color: #94a3b8; margin: 0; }
        .mc-accent-bar { width: 40px; height: 3px; border-radius: 4px; margin-bottom: 10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0; overflow: hidden;
        }
        .glass-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .glass-card-header-left { display: flex; align-items: center; gap: 10px; }
        .glass-card-header h6 {
            font-weight: 800; font-size: 0.78rem;
            text-transform: uppercase; color: var(--navy); margin: 0; letter-spacing: 0.5px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            background: #fff; border-radius: 14px; padding: 14px 18px;
            border: 1px solid #e2e8f0; display: flex; gap: 10px;
            flex-wrap: wrap; align-items: center; margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .filter-bar input, .filter-bar select {
            padding: 9px 13px; border: 1.5px solid #e2e8f0;
            border-radius: 10px; font-size: 0.82rem;
            font-family: 'Montserrat', sans-serif; background: #fff;
            color: var(--navy); transition: border-color 0.2s;
        }
        .filter-bar input  { flex: 1; min-width: 200px; }
        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: var(--gold); }
        .result-count {
            font-size: 0.72rem; font-weight: 700; color: #94a3b8;
            margin-left: auto; white-space: nowrap;
        }
        .result-count span { color: var(--navy); }

        /* ── PRINT BUTTON ── */
        .btn-print {
            border-radius: 9px; font-weight: 800; font-size: 0.62rem;
            text-transform: uppercase; padding: 8px 14px;
            border: 1px solid #e2e8f0; background: #f8fafc;
            color: #475569; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s;
        }
        .btn-print:hover { background: #f1f5f9; border-color: #cbd5e1; color: var(--navy); }

        /* ── TABLE ── */
        .table thead th {
            background: #f8fafc; padding: 14px 20px;
            font-size: 0.68rem; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px;
            border: none; font-weight: 700;
        }
        .table tbody td { padding: 16px 20px; vertical-align: middle; border-color: #f1f5f9; }
        .table tbody tr { transition: background 0.15s ease; }
        .table tbody tr:hover { background: #fafbfd; }

        .clinic-avatar {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #fff4e0, #ffecc4);
            border: 2px solid #fde68a; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: var(--gold); flex-shrink: 0;
        }

        .verified-pill {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.62rem; font-weight: 700;
            padding: 3px 9px; border-radius: 20px;
        }
        .pill-verified   { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .pill-unverified { background: #f9fafb; color: #9ca3af; border: 1px solid #e5e7eb; }
        .pill-emergency  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .pill-standard   { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

        .specialty-tag {
            font-size: 0.62rem; font-weight: 700; padding: 4px 10px;
            border-radius: 8px; background: #f1f5f9; color: #475569;
            letter-spacing: 0.5px; text-transform: uppercase;
            border: 1px solid #e2e8f0;
        }

        .btn-cmd {
            border-radius: 9px; font-weight: 800; font-size: 0.62rem;
            text-transform: uppercase; padding: 7px 14px;
            border: none; cursor: pointer; letter-spacing: 0.3px;
            transition: all 0.2s ease; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-cmd:hover { transform: translateY(-1px); }

        .empty-msg {
            padding: 60px; text-align: center;
            color: #94a3b8; font-weight: 600; font-style: italic;
        }
        .empty-msg i { font-size: 2rem; display: block; margin-bottom: 12px; opacity: 0.3; }

        /* ── TOAST ── */
        .toast-success {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            color: #fff; padding: 12px 24px; border-radius: 12px;
            font-weight: 700; font-size: 0.82rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            animation: fadeInOut 3s forwards;
        }
        .toast-success.success { background: #198754; }
        .toast-success.danger  { background: #dc3545; }
        @keyframes fadeInOut {
            0%   { opacity: 0; transform: translateY(-10px); }
            10%  { opacity: 1; transform: translateY(0); }
            80%  { opacity: 1; }
            100% { opacity: 0; }
        }

        /* ── MODAL ── */
        .modal-content  { border-radius: 24px !important; border: none; overflow: hidden; }
        .modal-title    { font-weight: 800; font-size: 0.95rem; color: var(--navy); }
        .form-label-sm  { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; }
        .form-control, .form-select {
            border-radius: 11px !important; font-size: 0.82rem;
            border-color: #e2e8f0; padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(184,134,11,0.12);
        }
        .modal-section-label {
            font-size: 0.6rem; font-weight: 800; letter-spacing: 2px;
            text-transform: uppercase; color: var(--gold);
            display: block; margin-bottom: 12px; padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .form-check-input:checked { background-color: var(--gold); border-color: var(--gold); }
        .btn-submit {
            background: var(--navy); color: #fff; border: none;
            border-radius: 12px; font-weight: 800; font-size: 0.8rem;
            padding: 12px 24px; letter-spacing: 0.5px; width: 100%; transition: all 0.2s;
        }
        .btn-submit:hover { background: #0f1e40; transform: translateY(-2px); }

        /* ── PRINT ── */
        .print-only { display: none; }
        @media print {
            .mc-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print,
            .filter-bar, .toast-success, .modal, [class*="chat"] { display: none !important; }
            body { background: #fff !important; font-size: 11pt; }
            .mc-wrapper { display: block; }
            .mc-content  { padding: 0 !important; }
            .glass-card  { box-shadow: none !important; border: 1px solid #ccc !important; border-radius: 0 !important; }
            thead { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { page-break-inside: avoid; }
            .print-only { display: block !important; }
            .print-only-header {
                display: flex !important; align-items: center;
                justify-content: space-between; padding-bottom: 12px;
                margin-bottom: 18px; border-bottom: 2px solid #0a1329;
            }
            .print-only-header h3   { font-size: 14pt; font-weight: 800; color: #0a1329; margin: 0; }
            .print-only-header .print-meta { font-size: 9pt; color: #64748b; text-align: right; }
            .print-only-header .print-filters { font-size: 8pt; color: #94a3b8; margin-top: 3px; }
            .result-count { display: none !important; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<?php if (isset($_GET['msg'])): ?>
<div class="toast-success <?= $_GET['msg'] === 'error' ? 'danger' : 'success' ?>">
    <i class="fas <?= $_GET['msg'] === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> me-2"></i>
    <?php
        if ($_GET['msg'] === 'added')   echo "New clinic partner successfully registered!";
        if ($_GET['msg'] === 'deleted') echo "Clinic removed from the network.";
        if ($_GET['msg'] === 'error')   echo "Something went wrong. Please try again.";
    ?>
</div>
<?php endif; ?>

<div class="mc-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="mc-sidebar">
        <div class="sidebar-head">
            <span>Medical Network</span>
            <p>Vet Clinic Partners</p>
        </div>

        <a href="?view=verified" class="sidebar-item <?= $view === 'verified' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-check-circle"></i></div>
            Verified Partners
            <?php if ($cnt_verified > 0): ?>
                <span class="sidebar-badge badge-green"><?= $cnt_verified ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar-divider"></div>

        <a href="?view=emergency" class="sidebar-item <?= $view === 'emergency' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-bolt"></i></div>
            24/7 Emergency
            <?php if ($cnt_emergency > 0): ?>
                <span class="sidebar-badge badge-red"><?= $cnt_emergency ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=standard" class="sidebar-item <?= $view === 'standard' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-calendar-check"></i></div>
            Standard Hours
            <?php if ($cnt_standard > 0): ?>
                <span class="sidebar-badge badge-blue"><?= $cnt_standard ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar-divider"></div>

        <a href="?view=all" class="sidebar-item <?= $view === 'all' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-list"></i></div>
            All Clinics
            <?php if ($cnt_all > 0): ?>
                <span class="sidebar-badge badge-grey"><?= $cnt_all ?></span>
            <?php endif; ?>
        </a>

        <a href="#" class="sidebar-add-btn" data-bs-toggle="modal" data-bs-target="#addClinicModal">
            <i class="fas fa-plus me-2"></i>Register Clinic
        </a>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="mc-content">

        <?php
        $view_meta = [
            'verified'  => ['color' => '#198754', 'icon' => 'fa-check-circle',  'title' => 'Verified Partners',      'sub' => 'Clinics confirmed and active in the network'],
            'emergency' => ['color' => '#dc3545', 'icon' => 'fa-bolt',           'title' => '24/7 Emergency Clinics', 'sub' => 'Round-the-clock emergency care providers'],
            'standard'  => ['color' => '#0d6efd', 'icon' => 'fa-calendar-check', 'title' => 'Standard Hours Clinics', 'sub' => 'Clinics operating on regular schedules'],
            'all'       => ['color' => '#B8860B', 'icon' => 'fa-hospital-user',  'title' => 'All Registered Clinics', 'sub' => 'Complete list of all clinic partners'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['verified'];
        ?>

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; <?= $meta['title'] ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Medical Network &rsaquo; <?= $meta['title'] ?></div>
                    <?php if ($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="mc-page-header">
            <div>
                <div class="mc-accent-bar" style="background: <?= $meta['color'] ?>;"></div>
                <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color: <?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
                <p>Medical Network &rsaquo; <?= $meta['title'] ?></p>
            </div>
            <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- FILTER BAR                                             -->
        <!-- ══════════════════════════════════════════════════════ -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?= $view ?>">

            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search name, address, phone, specialty...">

            <!-- Specialty dropdown — dynamic from DB -->
            <select name="filter_specialty">
                <option value="">All Specialties</option>
                <?php foreach ($specialties as $spec): ?>
                    <option value="<?= htmlspecialchars($spec) ?>"
                        <?= $filter_specialty === $spec ? 'selected' : '' ?>>
                        <?= htmlspecialchars($spec) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($view === 'all'): ?>
                <!-- Availability filter — only on All view since other views are already scoped -->
                <select name="filter_avail">
                    <option value="">All Availability</option>
                    <option value="247"      <?= $filter_avail === '247'      ? 'selected' : '' ?>>24/7 Emergency</option>
                    <option value="standard" <?= $filter_avail === 'standard' ? 'selected' : '' ?>>Standard Hours</option>
                </select>

                <!-- Verification status filter — only on All view -->
                <select name="filter_status">
                    <option value="">All Statuses</option>
                    <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending"  <?= $filter_status === 'pending'  ? 'selected' : '' ?>>Pending</option>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-cmd text-white" style="background: var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="?view=<?= $view ?>" class="btn-cmd" style="background: #f1f5f9; color: var(--navy);">
                <i class="fas fa-times"></i> Clear
            </a>

            <div class="result-count">
                <span><?= $result_count ?></span> clinic<?= $result_count != 1 ? 's' : '' ?>
                <?= $filter_label ? ' &nbsp;<span style="color:#B8860B;">— filtered</span>' : '' ?>
            </div>
        </form>

        <!-- GLASS CARD -->
        <div class="glass-card">
            <div class="glass-card-header" style="border-left: 4px solid <?= $meta['color'] ?>;">
                <div class="glass-card-header-left">
                    <i class="fas <?= $meta['icon'] ?>" style="color: <?= $meta['color'] ?>;"></i>
                    <h6><?= $meta['title'] ?> &mdash; <?= $result_count ?> Clinic<?= $result_count != 1 ? 's' : '' ?></h6>
                </div>
                <button class="btn-cmd text-white" style="background: var(--navy);"
                        data-bs-toggle="modal" data-bs-target="#addClinicModal">
                    <i class="fas fa-plus me-1"></i> Register Clinic
                </button>
            </div>

            <?php if ($result_count > 0): ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
    <tr>
        <th class="ps-4">Clinic</th>
        <th>Contact & Location</th>
        <th>Specialization</th>
        <th>License</th>
        <th>Availability</th>
        <th>Status</th>
        <th class="text-end pe-4">Actions</th>
    </tr>
</thead>
                    <tbody>
                    <?php while ($row = $active_set->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="clinic-avatar">
                                        <i class="fas fa-hospital-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </div>
                                        <div style="font-size: 0.68rem; color: #94a3b8; margin-top: 2px;">
                                            ID #<?= $row['id'] ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.78rem; font-weight: 700; color: #334155;">
                                    <i class="fas fa-phone-alt me-1 text-muted" style="font-size:0.65rem;"></i>
                                    <?= htmlspecialchars($row['phone']) ?>
                                </div>
                                <div style="font-size: 0.68rem; color: #94a3b8; max-width: 200px; margin-top: 3px; line-height: 1.4;">
                                    <i class="fas fa-location-dot me-1" style="font-size:0.62rem;"></i>
                                    <?= htmlspecialchars($row['address']) ?>
                                </div>
                                <?php if ($row['latitude'] && $row['longitude']): ?>
                                <div style="font-size: 0.62rem; color: #cbd5e1; margin-top: 2px;">
                                    <?= $row['latitude'] ?>, <?= $row['longitude'] ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="specialty-tag">
                                    <?= htmlspecialchars(strtoupper($row['specialty'] ?: 'General')) ?>
                                </span>
                            </td>
                            <td>
    <span class="specialty-tag">
        <?= htmlspecialchars($row['license']) ?>
    </span>
</td>
                            <td>
                                <?php if ($row['is_24_7']): ?>
                                    <span class="verified-pill pill-emergency">
                                        <i class="fas fa-bolt" style="font-size:0.55rem;"></i> 24/7 Emergency
                                    </span>
                                <?php else: ?>
                                    <span class="verified-pill pill-standard">
                                        <i class="fas fa-clock" style="font-size:0.55rem;"></i> Standard Hours
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['is_verified']): ?>
                                    <span class="verified-pill pill-verified">
                                        <i class="fas fa-check-circle" style="font-size:0.55rem;"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verified-pill pill-unverified">
                                        <i class="fas fa-clock" style="font-size:0.55rem;"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="?delete_id=<?= $row['id'] ?>&view=<?= $view ?>"
                                   class="btn-cmd text-white"
                                   style="background: #dc3545;"
                                   onclick="return confirm('Remove <?= htmlspecialchars(addslashes($row['name'])) ?> from the network?')">
                                    <i class="fas fa-trash-alt me-1"></i> Remove
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-msg">
                    <i class="fas <?= $meta['icon'] ?>"></i>
                    <?= $filter_label ? 'No clinics match your filter.' : 'No clinics found in this category.' ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>


<!-- ══════ ADD CLINIC MODAL ══════ -->
<div class="modal fade" id="addClinicModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="" method="POST" class="modal-content shadow-xl">

            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <span style="font-size:0.6rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--gold);">Medical Network</span>
                    <h5 class="modal-title mt-1">Register New Clinic Partner</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4 pb-0 pt-3">

                <span class="modal-section-label">Basic Information</span>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-sm">Clinic Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Dhaka Animal Hospital" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Contact Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+880 1X XX XXX XXX" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label-sm">Full Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Street, Area, City"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label-sm">Specialization</label>
                        <input type="text" name="specialty" class="form-control" placeholder="e.g. Surgery, Vaccination, Emergency Care">
                    </div>
                                    <div class="col-12">
    <label class="form-label-sm">License Number</label>
    <input type="text"
           name="license"
           class="form-control"
           placeholder="Enter clinic license number"
           required>
</div>

                </div>

                <span class="modal-section-label">GPS Coordinates</span>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-sm">Latitude</label>
                        <input type="text" name="latitude" class="form-control" placeholder="e.g. 23.8103">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Longitude</label>
                        <input type="text" name="longitude" class="form-control" placeholder="e.g. 90.4125">
                    </div>
                </div>

                <span class="modal-section-label">Partnership Flags</span>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_24_7" id="switch247">
                            <label class="form-check-label" for="switch247" style="font-size:0.8rem;font-weight:600;">24/7 Emergency Support</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_verified" id="switchVerified" checked>
                            <label class="form-check-label" for="switchVerified" style="font-size:0.8rem;font-weight:600;">Mark as Verified Partner</label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="submit" name="add_clinic" class="btn-submit">
                    <i class="fas fa-plus-circle me-2"></i>Save Partnership
                </button>
            </div>

        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</body>
</html>