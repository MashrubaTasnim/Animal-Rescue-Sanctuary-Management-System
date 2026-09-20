<?php
session_start();
include 'db_config.php';
require_once 'finance_helpers.php';
require_once 'notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=unauthorized'); exit();
}

$success = $error = '';
$tab     = $_GET['tab'] ?? 'active';

// ── APPROVE SPONSORSHIP ───────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
    $id = intval($_POST['sp_id']);

    // Get sponsorship details
    $sp = $conn->query("SELECT rs.*, u.full_name, a.name AS animal_name
                        FROM resident_sponsorships rs
                        JOIN users u ON rs.user_id = u.id
                        JOIN animals a ON rs.animal_id = a.id
                        WHERE rs.id = $id")->fetch_assoc();

    if ($sp) {
        // Activate sponsorship
        $conn->query("UPDATE resident_sponsorships
                      SET status='Active', start_date=CURDATE()
                      WHERE id=$id");
notify_sponsorship($id, 'Active');

        // Auto-log to funding_income
        $name   = $conn->real_escape_string($sp['full_name']);
        $amount = floatval($sp['monthly_amount']);
        $aid    = intval($sp['animal_id']);
        $by     = intval($_SESSION['user_id']);
        $conn->query("INSERT INTO funding_income
                        (source_type, amount, date_received, source_name, animal_id, notes, created_by)
                      VALUES
                        ('Sponsorship', $amount, CURDATE(), '$name', $aid,
                         'Monthly sponsorship approved and activated.', $by)");

        $success = "Sponsorship approved and activated! First payment logged to funding income.";
        $tab     = 'active';
    }
}
// ── REJECT SPONSORSHIP ────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $id = intval($_POST['sp_id']);
    $conn->query("UPDATE resident_sponsorships SET status='Rejected' WHERE id=$id AND status='Pending'");
    notify_sponsorship($id, 'Rejected');
    $success = "Sponsorship request rejected.";
    $tab     = 'pending';
}

// ── END SPONSORSHIP ───────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'end') {
    $id = intval($_POST['sp_id']);
    $conn->query("UPDATE resident_sponsorships SET status='Ended', end_date=CURDATE() WHERE id=$id");
    $success = "Sponsorship ended.";
    $tab     = 'active';
}

// ── ADMIN ADD SPONSORSHIP ─────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $user_id  = intval($_POST['user_id']);
    $animal_id= intval($_POST['animal_id']);
    $amount   = floatval($_POST['monthly_amount']);
    $start    = $conn->real_escape_string($_POST['start_date']);
    $notes    = $conn->real_escape_string($_POST['notes'] ?? '');

    if ($amount > 0 && $user_id && $animal_id) {
        $conn->query("INSERT INTO resident_sponsorships
                        (user_id, animal_id, monthly_amount, start_date, status, notes)
                      VALUES ($user_id, $animal_id, $amount, '$start', 'Active', '$notes')");

        // Auto-log to funding_income
        $u    = $conn->query("SELECT full_name FROM users WHERE id=$user_id")->fetch_assoc();
        $name = $conn->real_escape_string($u['full_name'] ?? 'Anonymous');
        $by   = intval($_SESSION['user_id']);
        $conn->query("INSERT INTO funding_income
                        (source_type, amount, date_received, source_name, animal_id, notes, created_by)
                      VALUES
                        ('Sponsorship', $amount, '$start', '$name', $animal_id,
                         'Monthly sponsorship manually added by admin.', $by)");

        $success = "Sponsorship added and funding entry created!";
        $tab     = 'active';
    } else {
        $error = "Please fill all required fields.";
    }
}

// ── DATA ──────────────────────────────────────────────────────────────────────
$pending_r = $conn->query("
    SELECT rs.*, u.full_name, u.email, u.profile_image,
           a.name AS animal_name, a.species, a.image_path AS animal_image
    FROM resident_sponsorships rs
    JOIN users u ON rs.user_id = u.id
    JOIN animals a ON rs.animal_id = a.id
    WHERE rs.status = 'Pending'
    ORDER BY rs.created_at DESC");
$pending = [];
while ($row = $pending_r->fetch_assoc()) $pending[] = $row;

$active_sponsors = getActiveSponsors($conn);

$ended_r = $conn->query("
    SELECT rs.*, u.full_name, u.email, u.profile_image,
           a.name AS animal_name, a.species, a.image_path AS animal_image
    FROM resident_sponsorships rs
    JOIN users u ON rs.user_id = u.id
    JOIN animals a ON rs.animal_id = a.id
    WHERE rs.status IN ('Ended','Rejected')
    ORDER BY rs.created_at DESC
    LIMIT 20");
$ended = [];
while ($row = $ended_r->fetch_assoc()) $ended[] = $row;

$all_animals = getSponsorableAnimals($conn);
$all_users_r = $conn->query("SELECT id, full_name, email FROM users WHERE role='user' AND status='active' ORDER BY full_name");
$all_users   = [];
while ($u = $all_users_r->fetch_assoc()) $all_users[] = $u;

// Stats
$stats = $conn->query("
    SELECT
        COUNT(CASE WHEN status='Active'  THEN 1 END) AS total_active,
        COUNT(CASE WHEN status='Pending' THEN 1 END) AS total_pending,
        COALESCE(SUM(CASE WHEN status='Active' THEN monthly_amount END),0) AS monthly_total
    FROM resident_sponsorships
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sponsorships | <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
:root { --navy:#0a1329; --gold:#B8860B; --gold-lt:#ffc107; }
body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }
.ma-wrapper  { display:flex; min-height:calc(100vh - 70px); }
.ma-sidebar  { width:240px; flex-shrink:0; background:var(--navy); padding:24px 0; position:sticky; top:70px; height:calc(100vh - 70px); overflow-y:auto; }
.ma-content  { flex:1; padding:32px 28px 60px; min-width:0; }
.sidebar-head { padding:0 20px 20px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:12px; }
.sidebar-head span { font-size:0.58rem; font-weight:800; letter-spacing:2.5px; text-transform:uppercase; color:rgba(255,193,7,0.6); display:block; margin-bottom:4px; }
.sidebar-head p { color:#fff; font-weight:700; font-size:0.88rem; margin:0; }
.sidebar-item { display:flex; align-items:center; gap:12px; padding:11px 20px; text-decoration:none !important; color:rgba(255,255,255,0.55); font-size:0.78rem; font-weight:600; transition:all 0.2s; border-left:3px solid transparent; }
.sidebar-item:hover { color:#fff; background:rgba(255,255,255,0.05); }
.sidebar-item.active { color:#fff; background:rgba(255,255,255,0.07); border-left-color:var(--gold); }
.sidebar-item .si-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; flex-shrink:0; background:rgba(255,255,255,0.06); }
.sidebar-item.active .si-icon { background:rgba(184,134,11,0.2); color:var(--gold); }
.sidebar-badge { margin-left:auto; background:#dc2626; color:#fff; font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:20px; line-height:1.6; }
.ma-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; }
.ma-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
.ma-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
.ma-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; background:var(--gold-lt); }
.glass-card { background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden; }
.glass-card-header { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; }
.glass-card-header h6 { font-weight:800; font-size:0.78rem; text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px; }
.btn-cmd { border-radius:9px; font-weight:800; font-size:0.62rem; text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
.ma-alert { border-radius:12px; font-size:0.8rem; font-weight:600; }

/* TABS */
.sp-tabs { display:flex; gap:0; margin-bottom:20px; background:#fff; border-radius:14px; border:1.5px solid #e2e8f0; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.04); }
.sp-tab { flex:1; padding:13px; text-align:center; font-size:0.78rem; font-weight:800; cursor:pointer; border:none; background:transparent; transition:all 0.2s; color:#94a3b8; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; }
.sp-tab:hover { background:#f8fafc; color:var(--navy); }
.sp-tab.active-pending { background:#d97706; color:#fff; }
.sp-tab.active-active  { background:#16a34a; color:#fff; }
.sp-tab.active-add     { background:var(--navy); color:#fff; }
.sp-tab.active-history { background:#475569; color:#fff; }
.sp-tab-badge { background:rgba(255,255,255,0.3); padding:1px 7px; border-radius:20px; font-size:0.65rem; }

/* KPI */
.kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.kpi-card { background:#fff; border-radius:16px; padding:20px; border:1px solid #e2e8f0; box-shadow:0 2px 10px rgba(0,0,0,0.03); border-top:4px solid var(--gold-lt); }
.kpi-card.orange { border-top-color:#f59e0b; }
.kpi-card.green  { border-top-color:#16a34a; }
.kpi-label { font-size:0.65rem; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:#94a3b8; margin-bottom:8px; }
.kpi-value { font-size:1.7rem; font-weight:800; color:var(--navy); }
.kpi-value.orange { color:#d97706; }
.kpi-value.green  { color:#16a34a; }

/* SPONSOR CARDS */
.sponsor-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.sp-card { background:#fff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.03); transition:transform 0.2s, box-shadow 0.2s; }
.sp-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,0.08); }
.sp-top-bar { height:5px; }
.sp-top-bar.active  { background:linear-gradient(90deg,#16a34a,#4ade80); }
.sp-top-bar.pending { background:linear-gradient(90deg,#d97706,#fbbf24); }
.sp-top-bar.ended   { background:#e2e8f0; }
.sp-body { padding:16px; }
.sp-user-row { display:flex; gap:12px; align-items:center; margin-bottom:12px; }
.sp-avatar { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid var(--gold-lt); flex-shrink:0; }
.sp-avatar-ph { width:42px; height:42px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:1.1rem; border:2px solid var(--gold-lt); flex-shrink:0; }
.sp-name  { font-size:0.88rem; font-weight:700; color:var(--navy); }
.sp-email { font-size:0.72rem; color:#94a3b8; }
.sp-animal-row { display:flex; gap:10px; align-items:center; background:#f8fafc; border-radius:10px; padding:10px; margin-bottom:12px; }
.sp-animal-img { width:34px; height:34px; border-radius:8px; object-fit:cover; flex-shrink:0; }
.sp-animal-ph  { width:34px; height:34px; border-radius:8px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }
.sp-animal-name    { font-size:0.82rem; font-weight:700; color:var(--navy); }
.sp-animal-species { font-size:0.7rem; color:#94a3b8; }
.sp-footer { display:flex; justify-content:space-between; align-items:center; }
.sp-amount { font-size:0.92rem; font-weight:800; color:#16a34a; }
.sp-amount.pending { color:#d97706; }
.sp-date { font-size:0.7rem; color:#94a3b8; }
.sp-trx  { font-size:0.65rem; color:#94a3b8; background:#f8fafc; border-radius:6px; padding:2px 7px; font-family:monospace; margin-top:4px; display:inline-block; }

/* ACTION BUTTONS on pending */
.btn-approve { background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; border-radius:9px; font-size:0.65rem; font-weight:800; text-transform:uppercase; padding:7px 12px; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:5px; }
.btn-approve:hover { background:#16a34a; color:#fff; }
.btn-reject  { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:9px; font-size:0.65rem; font-weight:800; text-transform:uppercase; padding:7px 12px; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:5px; }
.btn-reject:hover  { background:#dc2626; color:#fff; }
.btn-end { background:#f1f5f9; color:#64748b; border:none; border-radius:8px; font-size:0.65rem; font-weight:800; text-transform:uppercase; padding:6px 12px; cursor:pointer; transition:all 0.2s; }
.btn-end:hover { background:#dc2626; color:#fff; }

/* ADD FORM */
.add-card { background:#fff; border-radius:20px; border:1px solid #e2e8f0; box-shadow:0 4px 20px rgba(0,0,0,0.04); overflow:hidden; }
.add-header { background:linear-gradient(135deg,var(--navy) 0%,#1e3a5f 100%); padding:18px 24px; }
.add-header h6 { color:var(--gold-lt); font-weight:800; font-size:0.85rem; margin:0 0 3px; }
.add-header p  { color:rgba(255,255,255,0.5); font-size:0.72rem; margin:0; }
.add-body { padding:24px; }
.field-label { font-size:0.68rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:#475569; display:block; margin-bottom:7px; }
.field-input { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-family:'Inter',sans-serif; font-size:0.88rem; background:#fff; color:var(--navy); transition:border-color 0.2s; }
.field-input:focus { outline:none; border-color:var(--gold-lt); box-shadow:0 0 0 3px rgba(255,193,7,0.1); }
.form-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:16px; }
.btn-submit { background:var(--navy); color:#fff; padding:11px 24px; border-radius:10px; font-weight:800; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; border:none; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:8px; }
.btn-submit:hover { background:#1e3a5f; transform:translateY(-1px); }

/* SECTION LABEL */
.section-label { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.section-label::after { content:''; flex:1; height:1px; background:#e2e8f0; }
.section-label h6 { font-size:0.68rem; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:#94a3b8; margin:0; white-space:nowrap; }

/* EMPTY */
.empty-state { text-align:center; padding:40px 20px; color:#94a3b8; }
.empty-state i { font-size:2rem; display:block; margin-bottom:10px; opacity:0.3; }
.empty-state p { font-size:0.82rem; font-weight:600; margin:0; }

/* STATUS PILL */
.status-pill-sp { font-size:0.6rem; font-weight:800; padding:3px 9px; border-radius:20px; text-transform:uppercase; }
.pill-ended   { background:#f1f5f9; color:#64748b; }
.pill-rejected{ background:#fee2e2; color:#991b1b; }

@media(max-width:900px) { .kpi-row { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) { .kpi-row, .form-grid-3 { grid-template-columns:1fr; } }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="ma-wrapper">

    <!-- SIDEBAR -->
    <aside class="ma-sidebar">
        <div class="sidebar-head">
            <span>Finance</span>
            <p>Financial Control</p>
        </div>
        <a href="finance_dashboard.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-chart-pie"></i></div>
            Overview
        </a>
        <a href="finance_log_income.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-arrow-down"></i></div>
            Log Funding
        </a>
        <a href="finance_log_expense.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-arrow-up"></i></div>
            Log Expense
        </a>
        <a href="finance_ledger.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-table"></i></div>
            Full Ledger
        </a>
        <a href="finance_sponsorships.php" class="sidebar-item active">
            <div class="si-icon"><i class="fas fa-star"></i></div>
            Sponsorships
            <?php if($stats['total_pending'] > 0): ?>
                <span class="sidebar-badge"><?= $stats['total_pending'] ?></span>
            <?php endif; ?>
        </a>
        <div style="margin:16px 20px;border-top:1px solid rgba(255,255,255,0.08);"></div>
        <a href="admin.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-shield-alt"></i></div>
            Admin Panel
        </a>
    </aside>

    <!-- MAIN -->
    <main class="ma-content">

        <div class="ma-page-header">
            <div>
                <div class="ma-accent-bar"></div>
                <h5>Resident Sponsorships</h5>
                <p>Financial Control &rsaquo; Manage Animal Sponsorships</p>
            </div>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success ma-alert alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="alert alert-danger ma-alert alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- KPI CARDS -->
        <div class="kpi-row">
            <div class="kpi-card orange">
                <div class="kpi-label"><i class="fas fa-clock me-1" style="color:#f59e0b;"></i>Pending Approval</div>
                <div class="kpi-value orange"><?= $stats['total_pending'] ?></div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-label"><i class="fas fa-star me-1" style="color:#16a34a;"></i>Active Sponsorships</div>
                <div class="kpi-value green"><?= $stats['total_active'] ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><i class="fas fa-hand-holding-heart me-1" style="color:var(--gold-lt);"></i>Monthly Income</div>
                <div class="kpi-value" style="color:var(--gold-lt);"><?= formatCurrency($stats['monthly_total']) ?></div>
            </div>
        </div>

        <!-- TABS -->
        <div class="sp-tabs">
            <a href="?tab=pending" class="sp-tab <?= $tab==='pending' ? 'active-pending' : '' ?>">
                <i class="fas fa-clock"></i> Pending
                <?php if($stats['total_pending'] > 0): ?>
                    <span class="sp-tab-badge"><?= $stats['total_pending'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=active" class="sp-tab <?= $tab==='active' ? 'active-active' : '' ?>">
                <i class="fas fa-star"></i> Active
                <span class="sp-tab-badge"><?= $stats['total_active'] ?></span>
            </a>
            <a href="?tab=add" class="sp-tab <?= $tab==='add' ? 'active-add' : '' ?>">
                <i class="fas fa-plus"></i> Add Manually
            </a>
            <a href="?tab=history" class="sp-tab <?= $tab==='history' ? 'active-history' : '' ?>">
                <i class="fas fa-history"></i> History
            </a>
        </div>

        <!-- ── PENDING TAB ── -->
        <?php if($tab === 'pending'): ?>
        <?php if(empty($pending)): ?>
        <div class="glass-card">
            <div class="empty-state">
                <i class="fas fa-check-circle" style="opacity:0.3;color:#16a34a;"></i>
                <p>No pending sponsorships. All caught up!</p>
            </div>
        </div>
        <?php else: ?>
        <div class="sponsor-grid">
            <?php foreach($pending as $sp): ?>
            <div class="sp-card">
                <div class="sp-top-bar pending"></div>
                <div class="sp-body">
                    <div class="sp-user-row">
                        <?php if(!empty($sp['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['profile_image']) ?>" class="sp-avatar" alt="">
                        <?php else: ?>
                            <div class="sp-avatar-ph">🧑</div>
                        <?php endif; ?>
                        <div>
                            <div class="sp-name"><?= htmlspecialchars($sp['full_name']) ?></div>
                            <div class="sp-email"><?= htmlspecialchars($sp['email']) ?></div>
                        </div>
                    </div>
                    <div class="sp-animal-row">
                        <?php if(!empty($sp['animal_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['animal_image']) ?>" class="sp-animal-img" alt="">
                        <?php else: ?>
                            <div class="sp-animal-ph">🐱</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size:0.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Wants to sponsor</div>
                            <div class="sp-animal-name"><?= htmlspecialchars($sp['animal_name']) ?></div>
                            <div class="sp-animal-species"><?= htmlspecialchars($sp['species']) ?></div>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div class="sp-amount pending"><?= formatCurrency($sp['monthly_amount']) ?>/month</div>
                        <?php if(!empty($sp['payment_trx_id'])): ?>
                            <div class="sp-trx">TRX: <?= htmlspecialchars($sp['payment_trx_id']) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($sp['payment_method'])): ?>
                            <div class="sp-date">Paid via: <?= htmlspecialchars($sp['payment_method']) ?></div>
                        <?php endif; ?>
                        <div class="sp-date">Submitted: <?= date('d M Y', strtotime($sp['created_at'])) ?></div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" style="flex:1;">
                            <input type="hidden" name="action"  value="approve">
                            <input type="hidden" name="sp_id"   value="<?= $sp['id'] ?>">
                            <button type="submit" class="btn-approve w-100"
                                    onclick="return confirm('Approve this sponsorship and log to funding income?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="sp_id"  value="<?= $sp['id'] ?>">
                            <button type="submit" class="btn-reject w-100"
                                    onclick="return confirm('Reject this sponsorship request?')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── ACTIVE TAB ── -->
        <?php elseif($tab === 'active'): ?>
        <?php if(empty($active_sponsors)): ?>
        <div class="glass-card">
            <div class="empty-state">
                <i class="fas fa-star"></i>
                <p>No active sponsorships yet.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="sponsor-grid">
            <?php foreach($active_sponsors as $sp): ?>
            <div class="sp-card">
                <div class="sp-top-bar active"></div>
                <div class="sp-body">
                    <div class="sp-user-row">
                        <?php if(!empty($sp['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['profile_image']) ?>" class="sp-avatar" alt="">
                        <?php else: ?>
                            <div class="sp-avatar-ph">🧑</div>
                        <?php endif; ?>
                        <div>
                            <div class="sp-name"><?= htmlspecialchars($sp['full_name']) ?></div>
                            <div class="sp-email"><?= htmlspecialchars($sp['email']) ?></div>
                        </div>
                    </div>
                    <div class="sp-animal-row">
                        <?php if(!empty($sp['animal_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['animal_image']) ?>" class="sp-animal-img" alt="">
                        <?php else: ?>
                            <div class="sp-animal-ph">🐱</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size:0.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Sponsoring</div>
                            <div class="sp-animal-name"><?= htmlspecialchars($sp['animal_name']) ?></div>
                            <div class="sp-animal-species"><?= htmlspecialchars($sp['species']) ?></div>
                        </div>
                    </div>
                    <div class="sp-footer">
                        <div>
                            <div class="sp-amount"><?= formatCurrency($sp['monthly_amount']) ?>/month</div>
                            <div class="sp-date">Since <?= date('d M Y', strtotime($sp['start_date'])) ?></div>
                        </div>
                        <form method="POST" onsubmit="return confirm('End this sponsorship?')">
                            <input type="hidden" name="action" value="end">
                            <input type="hidden" name="sp_id"  value="<?= $sp['id'] ?>">
                            <button type="submit" class="btn-end">End</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── ADD MANUALLY TAB ── -->
        <?php elseif($tab === 'add'): ?>
        <div class="add-card">
            <div class="add-header">
                <h6><i class="fas fa-plus me-2"></i>Add Sponsorship Manually</h6>
                <p>For cash payments, bank transfers, or in-person sponsors. Instantly set to Active.</p>
            </div>
            <div class="add-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid-3">
                        <div>
                            <label class="field-label">Sponsor (User) *</label>
                            <select name="user_id" class="field-input" required>
                                <option value="">— Select Donor —</option>
                                <?php foreach($all_users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> — <?= htmlspecialchars($u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Animal (Resident) *</label>
                            <select name="animal_id" class="field-input" required>
                                <option value="">— Select Animal —</option>
                                <?php foreach($all_animals as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= $a['species'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Monthly Amount (৳) *</label>
                            <input type="number" name="monthly_amount" class="field-input" min="1" step="0.01" placeholder="e.g. 1500.00" required>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div>
                            <label class="field-label">Start Date *</label>
                            <input type="date" name="start_date" class="field-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div style="grid-column:span 2;">
                            <label class="field-label">Notes <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                            <input type="text" name="notes" class="field-input" placeholder="e.g. Cash payment received at office">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-star"></i> Add Sponsorship &amp; Log Funding
                    </button>
                </form>
            </div>
        </div>

        <!-- ── HISTORY TAB ── -->
        <?php elseif($tab === 'history'): ?>
        <?php if(empty($ended)): ?>
        <div class="glass-card">
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No ended or rejected sponsorships yet.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="sponsor-grid">
            <?php foreach($ended as $sp): ?>
            <div class="sp-card" style="opacity:0.7;">
                <div class="sp-top-bar ended"></div>
                <div class="sp-body">
                    <div class="sp-user-row">
                        <?php if(!empty($sp['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['profile_image']) ?>" class="sp-avatar" alt="" style="border-color:#e2e8f0;">
                        <?php else: ?>
                            <div class="sp-avatar-ph" style="border-color:#e2e8f0;">🧑</div>
                        <?php endif; ?>
                        <div>
                            <div class="sp-name"><?= htmlspecialchars($sp['full_name']) ?></div>
                            <div class="sp-email"><?= htmlspecialchars($sp['email']) ?></div>
                        </div>
                    </div>
                    <div class="sp-animal-row">
                        <?php if(!empty($sp['animal_image'])): ?>
                            <img src="<?= htmlspecialchars($sp['animal_image']) ?>" class="sp-animal-img" alt="">
                        <?php else: ?>
                            <div class="sp-animal-ph">🐱</div>
                        <?php endif; ?>
                        <div>
                            <div class="sp-animal-name"><?= htmlspecialchars($sp['animal_name']) ?></div>
                            <div class="sp-animal-species"><?= htmlspecialchars($sp['species']) ?></div>
                        </div>
                    </div>
                    <div class="sp-footer">
                        <div>
                            <div class="sp-amount" style="color:#94a3b8;"><?= formatCurrency($sp['monthly_amount']) ?>/month</div>
                            <div class="sp-date">
                                <?= $sp['status'] === 'Ended'
                                    ? 'Ended: '.(!empty($sp['end_date']) ? date('d M Y', strtotime($sp['end_date'])) : '—')
                                    : 'Rejected: '.date('d M Y', strtotime($sp['created_at'])) ?>
                            </div>
                        </div>
                        <span class="status-pill-sp <?= $sp['status']==='Ended' ? 'pill-ended' : 'pill-rejected' ?>">
                            <?= $sp['status'] ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>