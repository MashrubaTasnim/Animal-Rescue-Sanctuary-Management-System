<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// ── ACTIONS ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM vacancies WHERE id = $id");
    header("Location: manage_vacancies.php?view=vacancies");
    exit();
}

if (isset($_GET['action']) && isset($_GET['app_id'])) {
    $app_id = intval($_GET['app_id']);

    if ($_GET['action'] == 'approve') {
        $stmt = $conn->prepare("SELECT ja.user_id, v.category FROM job_applications ja JOIN vacancies v ON ja.job_id = v.id WHERE ja.id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app_data = $stmt->get_result()->fetch_assoc();

        if ($app_data) {
            $u_id     = $app_data['user_id'];
            $cat      = trim($app_data['category']);
            $new_role = ($cat == 'Medical') ? 'vet' : (($cat == 'Rescue') ? 'rescuer' : 'user');

            $conn->query("UPDATE users SET role = '$new_role' WHERE id = $u_id");
            $conn->query("UPDATE job_applications SET status = 'Accepted' WHERE id = $app_id");

            require_once 'notifications.php';
            notify_job_application($app_id, 'Accepted');

            header("Location: manage_vacancies.php?view=history&success=hired");
            exit();
        }

    } elseif ($_GET['action'] == 'reject') {
        $conn->query("UPDATE job_applications SET status = 'Rejected' WHERE id = $app_id");
        require_once 'notifications.php';
        notify_job_application($app_id, 'Rejected');
        header("Location: manage_vacancies.php?view=rejected");
        exit();
    }
}

if (isset($_GET['action']) && isset($_GET['res_id'])) {
    $res_id = intval($_GET['res_id']);
    if ($_GET['action'] == 'approve_resign') {
        $conn->query("UPDATE users SET role = 'user', resignation_status = 'approved', status_note = 'Resigned on " . date('Y-m-d') . "' WHERE id = $res_id");
        header("Location: manage_vacancies.php?view=resignations&sub=list&success=resign_approved");
        exit();
    } elseif ($_GET['action'] == 'reject_resign') {
        $conn->query("UPDATE users SET resignation_status = 'none', status_note = NULL WHERE id = $res_id");
        header("Location: manage_vacancies.php?view=resignations&success=resign_rejected");
        exit();
    }
}

// ── POST: add vacancy ─────────────────────────────────────────────────────────
if (isset($_POST['add_vacancy'])) {
    $title       = $conn->real_escape_string($_POST['title']);
    $category    = $conn->real_escape_string($_POST['category']);
    $job_type    = $conn->real_escape_string($_POST['job_type']);
    $location    = $conn->real_escape_string($_POST['location']);
    $salary      = $conn->real_escape_string($_POST['salary']);
    $description = $conn->real_escape_string($_POST['description']);
    $conn->query("INSERT INTO vacancies (title, category, job_type, location, salary, description) VALUES ('$title','$category','$job_type','$location','$salary','$description')");
    header("Location: manage_vacancies.php?view=vacancies");
    exit();
}

// ── VIEW & FILTER PARAMETERS ──────────────────────────────────────────────────
$view            = $_GET['view']            ?? 'vacancies';
$sub             = $_GET['sub']             ?? 'pending';
$search          = trim($_GET['search']     ?? '');
$filter_category = $_GET['filter_category'] ?? '';  // vacancies + applications
$filter_job_type = $_GET['filter_job_type'] ?? '';  // vacancies
$filter_role     = $_GET['filter_role']     ?? '';  // resignations

// ── helper ────────────────────────────────────────────────────────────────────
function esc($conn, $v) { return $conn->real_escape_string($v); }

// ── FILTERED DATA QUERIES ─────────────────────────────────────────────────────

// VACANCIES
$vac_where = "WHERE 1=1";
if ($search)          $vac_where .= " AND (title LIKE '%" . esc($conn,$search) . "%' OR location LIKE '%" . esc($conn,$search) . "%')";
if ($filter_category) $vac_where .= " AND category = '" . esc($conn,$filter_category) . "'";
if ($filter_job_type) $vac_where .= " AND job_type = '" . esc($conn,$filter_job_type) . "'";
$res_vacancies = $conn->query("SELECT * FROM vacancies $vac_where ORDER BY id DESC");

// APPLICATIONS (pending)
$app_where = "WHERE ja.status = 'Pending'";
if ($search)          $app_where .= " AND (ja.full_name LIKE '%" . esc($conn,$search) . "%' OR v.title LIKE '%" . esc($conn,$search) . "%' OR ja.phone LIKE '%" . esc($conn,$search) . "%')";
if ($filter_category) $app_where .= " AND v.category = '" . esc($conn,$filter_category) . "'";
$res_applications = $conn->query("SELECT ja.*, v.title, v.category FROM job_applications ja JOIN vacancies v ON ja.job_id = v.id $app_where ORDER BY ja.id DESC");

// HISTORY (hired)
$hist_where = "WHERE ja.status = 'Accepted'";
if ($search)          $hist_where .= " AND (ja.full_name LIKE '%" . esc($conn,$search) . "%' OR v.title LIKE '%" . esc($conn,$search) . "%')";
if ($filter_category) $hist_where .= " AND v.category = '" . esc($conn,$filter_category) . "'";
$res_history = $conn->query("SELECT ja.*, v.title, v.category FROM job_applications ja JOIN vacancies v ON ja.job_id = v.id $hist_where ORDER BY ja.id DESC");

// REJECTED
$rej_where = "WHERE ja.status = 'Rejected'";
if ($search)          $rej_where .= " AND (ja.full_name LIKE '%" . esc($conn,$search) . "%' OR v.title LIKE '%" . esc($conn,$search) . "%')";
if ($filter_category) $rej_where .= " AND v.category = '" . esc($conn,$filter_category) . "'";
$res_rejected = $conn->query("SELECT ja.*, v.title, v.category FROM job_applications ja JOIN vacancies v ON ja.job_id = v.id $rej_where ORDER BY ja.id DESC");

// RESIGNATIONS — pending
$resg_p_where = "WHERE resignation_status = 'pending'";
if ($search)      $resg_p_where .= " AND full_name LIKE '%" . esc($conn,$search) . "%'";
if ($filter_role) $resg_p_where .= " AND role = '" . esc($conn,$filter_role) . "'";
$res_resign_pending = $conn->query("SELECT id, full_name, role, status_note FROM users $resg_p_where ORDER BY id DESC");

// RESIGNATIONS — approved/list
$resg_l_where = "WHERE resignation_status = 'approved'";
if ($search)      $resg_l_where .= " AND full_name LIKE '%" . esc($conn,$search) . "%'";
if ($filter_role) $resg_l_where .= " AND role = '" . esc($conn,$filter_role) . "'";
$res_resign_list = $conn->query("SELECT id, full_name, role, status_note FROM users $resg_l_where ORDER BY id DESC");

// ── result count for active view ──────────────────────────────────────────────
$result_count = 0;
if ($view === 'vacancies')    $result_count = $res_vacancies->num_rows;
elseif ($view === 'applications') $result_count = $res_applications->num_rows;
elseif ($view === 'history')  $result_count = $res_history->num_rows;
elseif ($view === 'rejected') $result_count = $res_rejected->num_rows;
elseif ($view === 'resignations') $result_count = ($sub === 'list') ? $res_resign_list->num_rows : $res_resign_pending->num_rows;

// ── sidebar totals (unfiltered) ───────────────────────────────────────────────
$stats = [
    'v'   => $conn->query("SELECT COUNT(*) t FROM vacancies")->fetch_assoc()['t'],
    'p'   => $conn->query("SELECT COUNT(*) t FROM job_applications WHERE status='Pending'")->fetch_assoc()['t'],
    'a'   => $conn->query("SELECT COUNT(*) t FROM job_applications WHERE status='Accepted'")->fetch_assoc()['t'],
    'r'   => $conn->query("SELECT COUNT(*) t FROM job_applications WHERE status='Rejected'")->fetch_assoc()['t'],
    'res' => $conn->query("SELECT COUNT(*) t FROM users WHERE resignation_status='pending'")->fetch_assoc()['t'],
];

// ── build filter label for print ─────────────────────────────────────────────
$active_filters = [];
if ($search)          $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_category) $active_filters[] = 'Category: ' . htmlspecialchars($filter_category);
if ($filter_job_type) $active_filters[] = 'Type: '     . htmlspecialchars($filter_job_type);
if ($filter_role)     $active_filters[] = 'Role: '     . ucfirst(htmlspecialchars($filter_role));
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Operations | Heartbeat Heaven</title>
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

        .hr-wrapper { display:flex; min-height:calc(100vh - 70px); }
        .hr-sidebar {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .hr-content { flex:1; padding:32px 28px 60px; min-width:0; }

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
        .hr-page-header {
            margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
        }
        .hr-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .hr-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .hr-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background:#fff; border-radius:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid #e2e8f0; overflow:hidden;
        }
        .glass-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; justify-content:space-between;
            gap:10px; flex-wrap:wrap;
        }
        .glass-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px;
        }

        /* ── POST CARD ── */
        .post-card {
            background:#fff; border-radius:20px;
            border:1px solid #e2e8f0;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            overflow:hidden; margin-bottom:24px;
        }
        .post-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; gap:10px;
            border-left:4px solid var(--gold);
        }
        .post-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0;
        }
        .post-card-body { padding:20px; }

        .op-input {
            background:#f8fafc; border:1px solid #eef2f6;
            border-radius:12px; padding:10px 14px;
            font-size:0.82rem; width:100%; margin-bottom:12px;
            transition:border-color 0.2s;
        }
        .op-input:focus { outline:none; border-color:var(--gold); background:#fff; }

        .op-label {
            font-size:0.62rem; font-weight:800; color:#64748b;
            text-transform:uppercase; letter-spacing:1px;
            display:block; margin-bottom:5px;
        }

        .job-avatar {
            width:42px; height:42px; background:var(--navy); color:var(--gold);
            border-radius:12px; display:flex; align-items:center;
            justify-content:center; font-weight:800; font-size:1rem;
            flex-shrink:0;
        }
        .btn-cmd {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px;
            border:none; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;
        }

        .empty-msg { padding:40px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }

        .sub-tab {
            font-size:0.6rem; font-weight:800; text-transform:uppercase;
            padding:6px 14px; border-radius:8px; text-decoration:none;
            border:1px solid transparent; transition:all 0.2s;
        }
        .sub-tab.active   { background:var(--navy); color:#fff; }
        .sub-tab.inactive { background:transparent; color:var(--navy); border-color:#e2e8f0; }
        .sub-tab.inactive:hover { background:#f1f5f9; }

        /* ── FILTER BAR ── */
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

        /* ── PRINT BUTTON ── */
        .btn-print {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px;
            border:1px solid #e2e8f0; background:#f8fafc;
            color:#475569; cursor:pointer;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.15s;
        }
        .btn-print:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--navy); }

        .toast-success {
            position:fixed; top:20px; right:20px; z-index:9999;
            background:#198754; color:#fff; padding:12px 24px;
            border-radius:12px; font-weight:700; font-size:0.82rem;
            box-shadow:0 4px 20px rgba(0,0,0,0.15);
            animation: fadeInOut 3s forwards;
        }
        @keyframes fadeInOut {
            0%   { opacity:0; transform:translateY(-10px); }
            10%  { opacity:1; transform:translateY(0); }
            80%  { opacity:1; }
            100% { opacity:0; }
        }

        /* ── PRINT ── */
        .print-only { display:none; }
        @media print {
            .hr-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print,
            .sub-tab, .toast-success, .filter-bar,
            [class*="chat"], a.btn-cmd { display:none !important; }
            body { background:#fff !important; font-size:11pt; }
            .hr-wrapper { display:block; }
            .hr-content { padding:0 !important; }
            .glass-card { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
            .post-card  { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
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

<?php if (isset($_GET['success'])): ?>
<div class="toast-success">
    <?php
    $s = $_GET['success'];
    if ($s == 'hired')               echo '✓ Applicant hired successfully!';
    elseif ($s == 'resign_approved') echo '✓ Resignation approved.';
    elseif ($s == 'resign_rejected') echo '✓ Resignation rejected.';
    ?>
</div>
<?php endif; ?>

<div class="hr-wrapper">

    <!-- SIDEBAR -->
    <aside class="hr-sidebar">
        <div class="sidebar-head">
            <span>HR Operations</span>
            <p>Talent Management</p>
        </div>

        <a href="?view=vacancies" class="sidebar-item <?= $view=='vacancies' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-briefcase"></i></div>
            Vacancies
            <?php if($stats['v'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--gold);"><?= $stats['v'] ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=applications" class="sidebar-item <?= $view=='applications' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-file-contract"></i></div>
            Applications
            <?php if($stats['p'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--blue);"><?= $stats['p'] ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=history" class="sidebar-item <?= $view=='history' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-check-circle"></i></div>
            Hired
            <?php if($stats['a'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--green);"><?= $stats['a'] ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=resignations" class="sidebar-item <?= $view=='resignations' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-user-minus"></i></div>
            Resignations
            <?php if($stats['res'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--purple);"><?= $stats['res'] ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=rejected" class="sidebar-item <?= $view=='rejected' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-times-circle"></i></div>
            Rejected
            <?php if($stats['r'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--red);"><?= $stats['r'] ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=post" class="sidebar-item <?= $view=='post' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-plus-circle"></i></div>
            Post Vacancy
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="hr-content">

        <?php
        $view_meta = [
            'vacancies'    => ['color'=>'#B8860B', 'icon'=>'fa-briefcase',     'title'=>'Active Vacancies',       'sub'=>'All published job positions'],
            'applications' => ['color'=>'#0d6efd', 'icon'=>'fa-file-contract', 'title'=>'Pending Applications',   'sub'=>'Applications awaiting review'],
            'history'      => ['color'=>'#198754', 'icon'=>'fa-check-circle',  'title'=>'Placement Records',      'sub'=>'Successfully hired applicants'],
            'resignations' => ['color'=>'#6f42c1', 'icon'=>'fa-user-minus',    'title'=>'Resignation Management', 'sub'=>'Staff resignation requests'],
            'rejected'     => ['color'=>'#dc3545', 'icon'=>'fa-times-circle',  'title'=>'Rejected Archives',      'sub'=>'Declined applications'],
            'post'         => ['color'=>'#B8860B', 'icon'=>'fa-plus-circle',   'title'=>'Post New Vacancy',       'sub'=>'Publish a new job opening'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['vacancies'];
        ?>

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; <?= $meta['title'] ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">HR Operations &rsaquo; <?= $meta['title'] ?></div>
                    <?php if($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="hr-page-header">
            <div>
                <div class="hr-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
                <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
                <p>HR Operations &rsaquo; <?= $meta['title'] ?></p>
            </div>
            <?php if ($view !== 'post'): ?>
                <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
            <?php endif; ?>
        </div>

        <?php if ($view === 'post'): ?>
        <!-- ══ POST VACANCY FORM ══ -->
        <div class="post-card" style="max-width:600px;">
            <div class="post-card-header">
                <i class="fas fa-plus-circle" style="color:var(--gold);"></i>
                <h6>Post New Vacancy</h6>
            </div>
            <div class="post-card-body">
                <form action="" method="POST">
                    <label class="op-label">Position Title</label>
                    <input type="text" name="title" class="op-input" required placeholder="e.g. Senior Vet Surgeon">

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="op-label">Category</label>
                            <select name="category" class="op-input">
                                <option value="Medical">Medical</option>
                                <option value="Rescue">Rescue</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="op-label">Job Type</label>
                            <select name="job_type" class="op-input">
                                <option value="Full Time">Full Time</option>
                                <option value="Part Time">Part Time</option>
                            </select>
                        </div>
                    </div>

                    <label class="op-label">Location</label>
                    <input type="text" name="location" class="op-input" placeholder="Uttara, Dhaka" required>

                    <label class="op-label">Salary Detail</label>
                    <input type="text" name="salary" class="op-input" placeholder="e.g. 30k BDT">

                    <label class="op-label">Job Description</label>
                    <textarea name="description" class="op-input" rows="5" required></textarea>

                    <button type="submit" name="add_vacancy"
                        class="btn w-100 py-3 fw-bold text-white"
                        style="background:var(--navy); border-radius:12px; font-size:0.78rem; letter-spacing:1px;">
                        <i class="fas fa-paper-plane me-2"></i>PUBLISH NOW
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- FILTER BAR                                             -->
        <!-- ══════════════════════════════════════════════════════ -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?= $view ?>">
            <?php if ($view === 'resignations'): ?>
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
            <?php endif; ?>

            <!-- Search input — placeholder adapts per view -->
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="<?php
                    if ($view === 'vacancies')      echo 'Search title, location...';
                    elseif ($view === 'resignations') echo 'Search staff name...';
                    else                            echo 'Search applicant name, position...';
                ?>">

            <?php if ($view === 'vacancies'): ?>
                <!-- Category + Job Type for vacancies -->
                <select name="filter_category">
                    <option value="">All Categories</option>
                    <option value="Medical" <?= $filter_category==='Medical' ? 'selected':'' ?>>Medical</option>
                    <option value="Rescue"  <?= $filter_category==='Rescue'  ? 'selected':'' ?>>Rescue</option>
                </select>
                <select name="filter_job_type">
                    <option value="">All Types</option>
                    <option value="Full Time" <?= $filter_job_type==='Full Time' ? 'selected':'' ?>>Full Time</option>
                    <option value="Part Time" <?= $filter_job_type==='Part Time' ? 'selected':'' ?>>Part Time</option>
                </select>

            <?php elseif (in_array($view, ['applications','history','rejected'])): ?>
                <!-- Category only for application views -->
                <select name="filter_category">
                    <option value="">All Categories</option>
                    <option value="Medical" <?= $filter_category==='Medical' ? 'selected':'' ?>>Medical</option>
                    <option value="Rescue"  <?= $filter_category==='Rescue'  ? 'selected':'' ?>>Rescue</option>
                </select>

            <?php elseif ($view === 'resignations'): ?>
                <!-- Role filter for resignations -->
                <select name="filter_role">
                    <option value="">All Roles</option>
                    <option value="vet"     <?= $filter_role==='vet'     ? 'selected':'' ?>>Vet</option>
                    <option value="rescuer" <?= $filter_role==='rescuer' ? 'selected':'' ?>>Rescuer</option>
                    <option value="user"    <?= $filter_role==='user'    ? 'selected':'' ?>>User</option>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-cmd text-white" style="background:var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="?view=<?= $view ?><?= $view==='resignations' ? '&sub='.$sub : '' ?>"
               class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
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
                <div class="d-flex align-items-center gap-2">
                    <i class="fas <?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                    <h6><?= $meta['title'] ?> &mdash; <?= $result_count ?> Record<?= $result_count != 1 ? 's' : '' ?></h6>
                </div>
                <?php if ($view === 'resignations'): ?>
                <div class="d-flex gap-2">
                    <a href="?view=resignations&sub=pending<?= $search ? '&search='.urlencode($search) : '' ?><?= $filter_role ? '&filter_role='.urlencode($filter_role) : '' ?>"
                       class="sub-tab <?= $sub=='pending'?'active':'inactive' ?>">Requests</a>
                    <a href="?view=resignations&sub=list<?= $search ? '&search='.urlencode($search) : '' ?><?= $filter_role ? '&filter_role='.urlencode($filter_role) : '' ?>"
                       class="sub-tab <?= $sub=='list'?'active':'inactive' ?>">Resigned</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <tbody>
                    <?php
                    // pick the right result set
                    if ($view === 'vacancies')         $res = $res_vacancies;
                    elseif ($view === 'applications')  $res = $res_applications;
                    elseif ($view === 'history')       $res = $res_history;
                    elseif ($view === 'rejected')      $res = $res_rejected;
                    elseif ($view === 'resignations')  $res = ($sub === 'list') ? $res_resign_list : $res_resign_pending;
                    else                               $res = $res_vacancies;

                    if ($res && $res->num_rows > 0):
                        while ($row = $res->fetch_assoc()):
                            $label_char = strtoupper(substr(
                                ($view === 'vacancies') ? $row['title'] : $row['full_name'], 0, 1
                            ));
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="job-avatar"><?= $label_char ?></div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:0.85rem;">
                                        <?php
                                        if ($view === 'vacancies') echo htmlspecialchars($row['title']);
                                        else                       echo htmlspecialchars($row['full_name']);
                                        ?>
                                    </div>
                                    <small class="text-muted" style="font-size:0.72rem;">
                                        <?php if ($view === 'vacancies'): ?>
                                            <span class="badge rounded-pill text-white" style="background:var(--navy); font-size:0.6rem;"><?= htmlspecialchars($row['category']) ?></span>
                                            &nbsp;<?= htmlspecialchars($row['job_type']) ?> &bull; <?= htmlspecialchars($row['location']) ?>
                                        <?php elseif ($view === 'resignations'): ?>
                                            <strong>Role: <?= ucfirst(htmlspecialchars($row['role'])) ?></strong> &bull;
                                            <?= htmlspecialchars($row['status_note'] ?? 'No reason provided') ?>
                                        <?php else: ?>
                                            <span class="badge rounded-pill text-white" style="background:var(--navy); font-size:0.6rem;"><?= htmlspecialchars($row['category'] ?? '') ?></span>
                                            &nbsp;<strong>Position: <?= htmlspecialchars($row['title']) ?></strong> &bull;
                                            Ph: <?= htmlspecialchars($row['phone'] ?? 'N/A') ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($view === 'vacancies'): ?>
                                <a href="?delete=<?= $row['id'] ?>"
                                   class="btn-cmd bg-danger text-white"
                                   onclick="return confirm('Delete this vacancy?')">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </a>
                            <?php elseif ($view === 'applications'): ?>
                                <a href="<?= htmlspecialchars($row['cv_link']) ?>" target="_blank"
                                   class="btn-cmd bg-dark text-white me-1">
                                    <i class="fas fa-file-pdf me-1"></i>CV
                                </a>
                                <a href="?action=approve&app_id=<?= $row['id'] ?>"
                                   class="btn-cmd bg-success text-white me-1"
                                   onclick="return confirm('Hire this applicant?')">
                                    <i class="fas fa-check me-1"></i>Hire
                                </a>
                                <a href="?action=reject&app_id=<?= $row['id'] ?>"
                                   class="btn-cmd bg-danger text-white"
                                   onclick="return confirm('Reject this application?')">
                                    <i class="fas fa-times me-1"></i>Reject
                                </a>
                            <?php elseif ($view === 'resignations' && $sub === 'pending'): ?>
                                <a href="?action=approve_resign&res_id=<?= $row['id'] ?>"
                                   class="btn-cmd bg-success text-white me-1"
                                   onclick="return confirm('Approve resignation?')">
                                    <i class="fas fa-check me-1"></i>Approve
                                </a>
                                <a href="?action=reject_resign&res_id=<?= $row['id'] ?>"
                                   class="btn-cmd bg-warning text-dark"
                                   onclick="return confirm('Reject request?')">
                                    <i class="fas fa-undo me-1"></i>Reject
                                </a>
                            <?php elseif ($view === 'resignations' && $sub === 'list'): ?>
                                <span class="badge bg-secondary text-uppercase" style="font-size:0.6rem; padding:6px 12px; border-radius:8px;">Archived</span>
                            <?php elseif ($view === 'history'): ?>
                                <span class="badge bg-success text-uppercase" style="font-size:0.6rem; padding:6px 12px; border-radius:8px;">
                                    <i class="fas fa-check me-1"></i>Hired
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger text-uppercase" style="font-size:0.6rem; padding:6px 12px; border-radius:8px;">
                                    <i class="fas fa-times me-1"></i>Rejected
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="2">
                            <div class="empty-msg">
                                <i class="fas fa-folder-open d-block mb-2" style="font-size:2rem; color:#cbd5e1;"></i>
                                <?= ($filter_label) ? 'No records match your filter.' : 'No records found.' ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
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