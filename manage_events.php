<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Logic: Add Event with Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title    = $conn->real_escape_string($_POST['title']);
    $date     = $_POST['event_date'];
    $location = $conn->real_escape_string($_POST['location']);
    $desc     = $conn->real_escape_string($_POST['description']);

    $image_path = "";
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "uploads/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_ext    = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        $clean_title = preg_replace("/[^a-zA-Z0-9]/", "", $title);
        $file_name   = time() . "_" . $clean_title . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    $sql = "INSERT INTO events (title, event_date, location, description, image_path)
            VALUES ('$title', '$date', '$location', '$desc', '$image_path')";
    if ($conn->query($sql)) {
        header("Location: manage_events.php?view=events&msg=added"); exit();
    }
}

// Logic: Delete Event
if (isset($_GET['delete_id'])) {
    $id  = intval($_GET['delete_id']);
    $res = $conn->query("SELECT image_path FROM events WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['image_path']) && file_exists($row['image_path'])) unlink($row['image_path']);
    }
    $conn->query("DELETE FROM event_interests WHERE event_id = $id");
    $conn->query("DELETE FROM events WHERE id = $id");
    header("Location: manage_events.php?view=events&msg=deleted"); exit();
}

// ── VIEW & FILTER PARAMETERS ──────────────────────────────────────────────────
$view            = $_GET['view']                  ?? 'events';
$search          = trim($_GET['search']           ?? '');
$filter_timing   = $_GET['filter_timing']         ?? '';
$filter_location = trim($_GET['filter_location']  ?? '');

// ── HELPER ────────────────────────────────────────────────────────────────────
function esc($conn, $v) { return $conn->real_escape_string($v); }

// ── WHERE BUILDER ─────────────────────────────────────────────────────────────
function build_where($conn, $view, $search, $filter_timing, $filter_location) {
    $where = "WHERE 1=1";

    // View-level date scope
    if ($view === 'upcoming') {
        $where .= " AND event_date >= CURDATE()";
    } elseif ($view === 'past') {
        $where .= " AND event_date < CURDATE()";
    }

    // Timing dropdown (only applies on 'events' / All view)
    if ($view === 'events' && $filter_timing === 'upcoming') {
        $where .= " AND event_date >= CURDATE()";
    } elseif ($view === 'events' && $filter_timing === 'past') {
        $where .= " AND event_date < CURDATE()";
    }

    // Keyword search
    if ($search) {
        $s = esc($conn, $search);
        $where .= " AND (title LIKE '%$s%' OR location LIKE '%$s%' OR description LIKE '%$s%')";
    }

    // Location filter
    if ($filter_location) {
        $l = esc($conn, $filter_location);
        $where .= " AND location LIKE '%$l%'";
    }

    return $where;
}

$where        = build_where($conn, $view, $search, $filter_timing, $filter_location);
$order        = ($view === 'upcoming') ? "ORDER BY event_date ASC" : "ORDER BY event_date DESC";
$events       = $conn->query("SELECT * FROM events $where $order");
$result_count = $events->num_rows;

// ── LOCATION LIST for dropdown (unfiltered, distinct) ─────────────────────────
$loc_res   = $conn->query("SELECT DISTINCT location FROM events ORDER BY location ASC");
$locations = [];
while ($lr = $loc_res->fetch_assoc()) $locations[] = $lr['location'];

// ── SIDEBAR TOTALS (unfiltered) ───────────────────────────────────────────────
$cnt_events = $conn->query("SELECT COUNT(*) t FROM events")->fetch_assoc()['t'];
$upcoming   = $conn->query("SELECT COUNT(*) t FROM events WHERE event_date >= CURDATE()")->fetch_assoc()['t'];
$past       = $conn->query("SELECT COUNT(*) t FROM events WHERE event_date < CURDATE()")->fetch_assoc()['t'];
$total_int  = $conn->query("SELECT COUNT(*) t FROM event_interests")->fetch_assoc()['t'];

// ── FILTER LABEL FOR PRINT ────────────────────────────────────────────────────
$active_filters = [];
if ($search)          $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_timing)   $active_filters[] = 'Timing: '   . ucfirst($filter_timing);
if ($filter_location) $active_filters[] = 'Location: ' . htmlspecialchars($filter_location);
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Management | Praner Tan</title>
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
        .ev-wrapper { display:flex; min-height:calc(100vh - 70px); }
        .ev-sidebar {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .ev-content { flex:1; padding:32px 28px 60px; min-width:0; }

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
        .ev-page-header {
            margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
        }
        .ev-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .ev-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .ev-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background:#fff; border-radius:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid #e2e8f0; overflow:hidden;
        }
        .glass-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; justify-content:space-between; gap:10px;
        }
        .glass-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px;
        }

        /* ── POST FORM CARD ── */
        .post-card {
            background:#fff; border-radius:20px;
            border:1px solid #e2e8f0;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            overflow:hidden; max-width:640px;
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
        .post-card-body { padding:24px; }

        .op-label {
            font-size:0.62rem; font-weight:800; color:#64748b;
            text-transform:uppercase; letter-spacing:1px;
            display:block; margin-bottom:5px;
        }
        .op-input {
            background:#f8fafc; border:1px solid #eef2f6;
            border-radius:12px; padding:10px 14px;
            font-size:0.82rem; width:100%; margin-bottom:14px;
            transition:border-color 0.2s;
        }
        .op-input:focus { outline:none; border-color:var(--gold); background:#fff; }

        /* ── TABLE ── */
        .event-thumb {
            width:70px; height:48px; border-radius:10px;
            object-fit:cover; background:#f1f5f9; flex-shrink:0;
        }
        .event-thumb-placeholder {
            width:70px; height:48px; border-radius:10px;
            background:#f1f5f9; display:flex; align-items:center;
            justify-content:center; color:#cbd5e1; flex-shrink:0;
        }

        .btn-cmd {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px;
            border:none; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;
        }
        .btn-cmd:hover { opacity:0.85; }

        .empty-msg { padding:40px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }

        .int-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:#eff6ff; color:#2563eb;
            font-size:0.65rem; font-weight:800;
            padding:3px 8px; border-radius:20px;
        }

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

        /* ── TOAST ── */
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
            .ev-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print,
            .filter-bar, .alert, [class*="chat"] { display:none !important; }
            body { background:#fff !important; font-size:11pt; }
            .ev-wrapper { display:block; }
            .ev-content  { padding:0 !important; }
            .glass-card  { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
            .event-thumb { width:48px !important; height:36px !important; }
            thead { background:#f1f5f9 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
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

<?php if (isset($_GET['msg'])): ?>
<div class="toast-success">
    <i class="fas fa-check-circle me-2"></i>
    <?= $_GET['msg'] === 'added' ? 'Event published successfully!' : 'Event deleted successfully.' ?>
</div>
<?php endif; ?>

<div class="ev-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="ev-sidebar">
        <div class="sidebar-head">
            <span>Mission Control</span>
            <p>Event Management</p>
        </div>

        <a href="?view=events" class="sidebar-item <?= $view=='events' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-calendar-alt"></i></div>
            All Events
            <?php if($cnt_events > 0): ?>
                <span class="sidebar-badge" style="background:var(--gold);"><?= $cnt_events ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=upcoming" class="sidebar-item <?= $view=='upcoming' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-calendar-check"></i></div>
            Upcoming
            <?php if($upcoming > 0): ?>
                <span class="sidebar-badge" style="background:var(--green);"><?= $upcoming ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=past" class="sidebar-item <?= $view=='past' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-calendar-xmark"></i></div>
            Past Events
            <?php if($past > 0): ?>
                <span class="sidebar-badge" style="background:#6c757d;"><?= $past ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=create" class="sidebar-item <?= $view=='create' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-plus-circle"></i></div>
            Create Event
        </a>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="ev-content">

        <?php
        $view_meta = [
            'events'   => ['color'=>'#B8860B', 'icon'=>'fa-calendar-alt',   'title'=>'All Events',     'sub'=>'Every event in the system'],
            'upcoming' => ['color'=>'#198754', 'icon'=>'fa-calendar-check',  'title'=>'Upcoming Events','sub'=>'Events scheduled for the future'],
            'past'     => ['color'=>'#6c757d', 'icon'=>'fa-calendar-xmark',  'title'=>'Past Events',    'sub'=>'Events that have already occurred'],
            'create'   => ['color'=>'#B8860B', 'icon'=>'fa-plus-circle',     'title'=>'Create Event',   'sub'=>'Publish a new event campaign'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['events'];
        ?>

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Praner Tan &mdash; <?= $meta['title'] ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Mission Control &rsaquo; <?= $meta['title'] ?></div>
                    <?php if($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="ev-page-header">
            <div>
                <div class="ev-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
                <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
                <p>Mission Control &rsaquo; <?= $meta['title'] ?></p>
            </div>
            <?php if ($view !== 'create'): ?>
                <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
            <?php endif; ?>
        </div>

        <?php if ($view === 'create'): ?>
        <!-- ═══ CREATE EVENT FORM ═══ -->
        <div class="post-card">
            <div class="post-card-header">
                <i class="fas fa-plus-circle" style="color:var(--gold);"></i>
                <h6>New Event Campaign</h6>
            </div>
            <div class="post-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <label class="op-label">Event Title</label>
                    <input type="text" name="title" class="op-input" required placeholder="e.g. Adoption Drive 2025">

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="op-label">Event Date</label>
                            <input type="date" name="event_date" class="op-input" required>
                        </div>
                        <div class="col-6">
                            <label class="op-label">Location</label>
                            <input type="text" name="location" class="op-input" required placeholder="e.g. Uttara, Dhaka">
                        </div>
                    </div>

                    <label class="op-label">Banner Image</label>
                    <input type="file" name="event_image" class="op-input" accept="image/*">

                    <label class="op-label">Description</label>
                    <textarea name="description" class="op-input" rows="5" required></textarea>

                    <button type="submit" name="add_event"
                        class="btn w-100 py-3 fw-bold text-white"
                        style="background:var(--navy); border-radius:12px; font-size:0.78rem; letter-spacing:1px;">
                        <i class="fas fa-paper-plane me-2"></i>PUBLISH EVENT
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

            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search title, location, description...">

            <!-- Location dropdown — dynamic from DB -->
            <select name="filter_location">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>"
                        <?= $filter_location === $loc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($view === 'events'): ?>
                <select name="filter_timing">
                    <option value="">All Timings</option>
                    <option value="upcoming" <?= $filter_timing==='upcoming' ? 'selected':'' ?>>Upcoming</option>
                    <option value="past"     <?= $filter_timing==='past'     ? 'selected':'' ?>>Past</option>
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
                <div class="d-flex align-items-center gap-2">
                    <i class="fas <?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                    <h6><?= $meta['title'] ?> &mdash; <?= $result_count ?> Record<?= $result_count != 1 ? 's' : '' ?></h6>
                </div>
                <a href="?view=create" class="btn-cmd text-white" style="background:var(--navy);">
                    <i class="fas fa-plus me-1"></i>New Event
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <thead style="background:#f8fafc; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; font-weight:800;">
                        <tr>
                            <th class="ps-4 py-3">Event</th>
                            <th>Location & Date</th>
                            <th>Interest</th>
                            <th>Created</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($events->num_rows === 0): ?>
                        <tr><td colspan="5">
                            <div class="empty-msg">
                                <i class="fas fa-calendar-times d-block mb-2" style="font-size:2rem; color:#cbd5e1;"></i>
                                <?= $filter_label ? 'No events match your filter.' : 'No events found.' ?>
                            </div>
                        </td></tr>
                    <?php else: while($row = $events->fetch_assoc()):
                        $e_id    = $row['id'];
                        $p_count = $conn->query("SELECT COUNT(*) as total FROM event_interests WHERE event_id = $e_id")->fetch_assoc()['total'];
                        $is_past = (strtotime($row['event_date']) < time());
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <?php if(!empty($row['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($row['image_path']) ?>" class="event-thumb">
                                <?php else: ?>
                                    <div class="event-thumb-placeholder"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:0.85rem;">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.7rem; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?= htmlspecialchars($row['description']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold" style="font-size:0.78rem;">
                                <i class="fas fa-map-marker-alt me-1 text-warning"></i>
                                <?= htmlspecialchars($row['location']) ?>
                            </div>
                            <div style="font-size:0.72rem; color:<?= $is_past ? '#94a3b8' : 'var(--green)' ?>; font-weight:700;">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($row['event_date'])) ?>
                                <?php if($is_past): ?>
                                    <span class="ms-1 badge" style="background:#f1f5f9; color:#94a3b8; font-size:0.55rem; border-radius:6px;">Past</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="int-badge">
                                <i class="fas fa-users"></i> <?= $p_count ?>
                            </span>
                        </td>
                        <td style="font-size:0.72rem; color:#94a3b8;">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('M d, Y', strtotime($row['created_at'])) ?>
                        </td>
                        <td class="text-end pe-4">
                            <a href="edit_event.php?id=<?= $row['id'] ?>"
                               class="btn-cmd bg-dark text-white me-1">
                                <i class="fas fa-pen me-1"></i>Edit
                            </a>
                            <a href="event_participants.php?id=<?= $row['id'] ?>"
                               class="btn-cmd bg-success text-white me-1">
                                <i class="fas fa-users me-1"></i>People
                            </a>
                            <a href="?delete_id=<?= $row['id'] ?>"
                               class="btn-cmd bg-danger text-white"
                               onclick="return confirm('Delete this event permanently?')">
                                <i class="fas fa-trash-alt me-1"></i>Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
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