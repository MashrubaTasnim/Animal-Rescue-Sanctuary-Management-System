<?php
session_start();
include 'db_config.php';
require_once 'finance_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=unauthorized'); exit();
}

$tab    = $_GET['tab']    ?? 'income';
$page   = max(1, intval($_GET['page']   ?? 1));
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

if ($tab === 'income') {
    $result  = getAllFundingPaginated($conn, $page, 20, $filter, $search);
    $sources = ['General Donation','Sponsorship','Grant','SOS Campaign','Partner Clinic','Surrender Contribution','Owner Capital'];
} else {
    $result  = getAllExpensesPaginated($conn, $page, 20, $filter, $search);
    $sources = ['Medical','Food & Supplies','Facility Maintenance','Rescue Ops','Staff Salaries','Event Management','Admin'];
}

$data        = $result['data'];
$total_rows  = $result['total'];
$total_pages = $result['pages'];

// Grand totals for strip
if ($tab === 'income') {
    $grand_q    = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM funding_income");
    $filter_sql = "SELECT COALESCE(SUM(amount),0) AS t FROM funding_income WHERE 1=1"
                . ($filter ? " AND source_type='".$conn->real_escape_string($filter)."'" : "")
                . ($search ? " AND source_name LIKE '%".$conn->real_escape_string($search)."%'" : "");
} else {
    $grand_q    = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM sanctuary_expenses");
    $filter_sql = "SELECT COALESCE(SUM(amount),0) AS t FROM sanctuary_expenses WHERE 1=1"
                . ($filter ? " AND category='".$conn->real_escape_string($filter)."'" : "")
                . ($search ? " AND vendor_name_or_payee LIKE '%".$conn->real_escape_string($search)."%'" : "");
}
$grand_total    = (float)$grand_q->fetch_assoc()['t'];
$filtered_total = (float)$conn->query($filter_sql)->fetch_assoc()['t'];

function buildUrl($overrides) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
if (isset($_GET['print'])) {
    // Build print-friendly page — no sidebar, no pagination, full data
    $all = ($tab === 'income')
        ? getAllFundingPaginated($conn, 1, 9999, $filter, $search)['data']
        : getAllExpensesPaginated($conn, 1, 9999, $filter, $search)['data'];
    include 'finance_ledger_print.php'; // see below
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Ledger | <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?></title>
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
.ma-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.ma-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
.ma-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
.ma-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; background:var(--gold-lt); }
.glass-card { background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden; }
.glass-card-header { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; }
.glass-card-header h6 { font-weight:800; font-size:0.78rem; text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px; }
.btn-cmd { border-radius:9px; font-weight:800; font-size:0.62rem; text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }

/* TABS */
.ledger-tabs { display:flex; gap:0; margin-bottom:20px; background:#fff; border-radius:14px; border:1.5px solid #e2e8f0; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.04); }
.ledger-tab { flex:1; padding:13px; text-align:center; font-size:0.78rem; font-weight:800; cursor:pointer; border:none; background:transparent; transition:all 0.2s; color:#94a3b8; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; }
.ledger-tab:hover { background:#f8fafc; color:var(--navy); }
.ledger-tab.active-income  { background:#16a34a; color:#fff; }
.ledger-tab.active-expense { background:#dc2626; color:#fff; }

/* FILTER BAR */
.filter-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-bar input, .filter-bar select { padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:10px; font-family:'Inter',sans-serif; font-size:0.82rem; background:#fff; color:var(--navy); transition:border-color 0.2s; }
.filter-bar input  { flex:1; min-width:180px; }
.filter-bar input:focus, .filter-bar select:focus { outline:none; border-color:var(--gold-lt); }

/* SUMMARY STRIP */
.summary-strip { background:#fff; border-radius:12px; padding:14px 20px; display:flex; gap:28px; flex-wrap:wrap; align-items:center; margin-bottom:16px; border:1px solid #e2e8f0; box-shadow:0 2px 10px rgba(0,0,0,0.03); }
.ss-item { display:flex; flex-direction:column; gap:2px; }
.ss-label { font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; }
.ss-value { font-size:1rem; font-weight:800; color:var(--navy); }
.ss-value.green { color:#16a34a; }
.ss-value.red   { color:#dc2626; }

/* TABLE */
.table { margin:0 !important; }
.table > thead > tr > th { font-size:0.65rem !important; font-weight:800 !important; letter-spacing:1.5px !important; text-transform:uppercase !important; color:#64748b !important; padding:13px 16px !important; border:none !important; background:#f8fafc !important; border-bottom:1px solid #f1f5f9 !important; white-space:nowrap; }
.table > tbody > tr { border-bottom:1px solid #f1f5f9 !important; transition:background 0.15s; }
.table > tbody > tr:last-child { border-bottom:none !important; }
.table > tbody > tr:hover { background:#f8fafc; }
.table td { padding:12px 16px !important; vertical-align:middle !important; border:none !important; font-size:0.82rem; }
.amount-income  { color:#16a34a; font-weight:800; }
.amount-expense { color:#dc2626; font-weight:800; }

/* BADGES */
.badge-pill { display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.65rem; font-weight:700; white-space:nowrap; }
.badge-donation  { background:#dcfce7; color:#15803d; }
.badge-sponsor   { background:#fef9c3; color:#854d0e; }
.badge-grant     { background:#dbeafe; color:#1e40af; }
.badge-sos       { background:#fee2e2; color:#991b1b; }
.badge-clinic    { background:#ede9fe; color:#5b21b6; }
.badge-surrender { background:#ffedd5; color:#9a3412; }
.badge-owner     { background:#f0fdf4; color:#166534; }
.badge-medical   { background:#fee2e2; color:#991b1b; }
.badge-food      { background:#dcfce7; color:#15803d; }
.badge-facility  { background:#dbeafe; color:#1e40af; }
.badge-rescue    { background:#fef9c3; color:#854d0e; }
.badge-salary    { background:#dbeafe; color:#1e40af; }
.badge-event     { background:#ffedd5; color:#9a3412; }
.badge-admin     { background:#f1f5f9; color:#475569; }
.badge-default   { background:#f1f5f9; color:#475569; }

/* PAGINATION */
.pagination-bar { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; background:#fff; border-top:1.5px solid #f1f5f9; flex-wrap:wrap; gap:10px; }
.page-info { font-size:0.75rem; color:#94a3b8; font-weight:600; }
.page-btns { display:flex; gap:5px; }
.page-btn { padding:6px 12px; border-radius:8px; border:1.5px solid #e2e8f0; background:#fff; cursor:pointer; font-size:0.75rem; font-weight:700; text-decoration:none; color:var(--navy); transition:all 0.2s; }
.page-btn:hover { border-color:var(--gold-lt); color:var(--gold-lt); }
.page-btn.current { background:var(--navy); color:#fff; border-color:var(--navy); }
.page-btn.disabled { opacity:0.35; pointer-events:none; }

/* EMPTY */
.empty-state { text-align:center; padding:50px 20px; color:#94a3b8; }
.empty-state i { font-size:2rem; display:block; margin-bottom:10px; opacity:0.3; }
.empty-state p { font-size:0.82rem; font-weight:600; margin:0; }
@media print {
    body { background: #fff !important; font-size: 11pt; }
    .ma-sidebar, .ma-page-header .d-flex,
    .ledger-tabs, .filter-bar, .pagination-bar,
    nav, footer, .chat-widget { display: none !important; }
    .ma-wrapper { display: block; }
    .ma-content { padding: 0; }
    .glass-card { box-shadow: none; border: 1px solid #ccc; border-radius: 0; }
    .table > thead > tr > th,
    .table > tbody > tr > td { padding: 8px 10px !important; font-size: 9pt !important; }
    .summary-strip { border: 1px solid #ccc; border-radius: 0; }
    .page-break-after { page-break-after: always; }
}
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
        <a href="finance_ledger.php" class="sidebar-item active">
            <div class="si-icon"><i class="fas fa-table"></i></div>
            Full Ledger
        </a>
        <a href="finance_sponsorships.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-star"></i></div>
            Sponsorships
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
                <h5>Financial Ledger</h5>
                <p>Financial Control &rsaquo; All Transactions</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="finance_log_income.php" class="btn-cmd text-white" style="background:#16a34a;">
                    <i class="fas fa-plus"></i> Log Funding
                </a>
                <a href="finance_log_expense.php" class="btn-cmd text-white" style="background:#dc2626;">
                    <i class="fas fa-plus"></i> Log Expense
                </a>
            </div>
            <a href="<?= buildUrl(['tab'=>$tab,'print'=>'1']) ?>" target="_blank"
   class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
    <i class="fas fa-print"></i> Print
</a>
        </div>

        <!-- TABS -->
        <div class="ledger-tabs">
            <a href="<?= buildUrl(['tab'=>'income','page'=>1,'filter'=>'','search'=>'']) ?>"
               class="ledger-tab <?= $tab==='income' ? 'active-income' : '' ?>">
                <i class="fas fa-arrow-down"></i> Funding Income
                <?php if($tab==='income'): ?>
                    <span style="background:rgba(255,255,255,0.25);padding:1px 7px;border-radius:20px;font-size:0.65rem;"><?= $total_rows ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= buildUrl(['tab'=>'expense','page'=>1,'filter'=>'','search'=>'']) ?>"
               class="ledger-tab <?= $tab==='expense' ? 'active-expense' : '' ?>">
                <i class="fas fa-arrow-up"></i> Sanctuary Expenses
                <?php if($tab==='expense'): ?>
                    <span style="background:rgba(255,255,255,0.25);padding:1px 7px;border-radius:20px;font-size:0.65rem;"><?= $total_rows ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search name, vendor, notes...">
            <select name="filter">
                <option value="">All <?= $tab==='income' ? 'Sources' : 'Categories' ?></option>
                <?php foreach($sources as $s): ?>
                    <option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-cmd text-white" style="background:var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="<?= buildUrl(['tab'=>$tab,'page'=>1,'filter'=>'','search'=>'']) ?>"
               class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>

        <!-- SUMMARY STRIP -->
        <div class="summary-strip">
            <div class="ss-item">
                <span class="ss-label">Showing</span>
                <span class="ss-value"><?= $total_rows ?> records</span>
            </div>
            <div class="ss-item">
                <span class="ss-label">Filtered Total</span>
                <span class="ss-value <?= $tab==='income'?'green':'red' ?>"><?= formatCurrency($filtered_total) ?></span>
            </div>
            <div class="ss-item">
                <span class="ss-label">Grand Total (All Time)</span>
                <span class="ss-value"><?= formatCurrency($grand_total) ?></span>
            </div>
            <div class="ss-item">
                <span class="ss-label">Page</span>
                <span class="ss-value"><?= $page ?> / <?= max(1,$total_pages) ?></span>
            </div>
        </div>

        <!-- TABLE -->
        <div class="glass-card">
            <?php if(empty($data)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No records found.</p>
                </div>

            <?php elseif($tab === 'income'): ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Source Type</th>
                            <th>Source Name</th>
                            <th>Amount</th>
                            <th>Date Received</th>
                            <th>Linked Animal</th>
                            <th>Logged By</th>
                            <th class="pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($data as $row): ?>
                    <tr>
                        <td class="ps-4" style="color:#94a3b8;font-size:0.72rem;">#<?= $row['id'] ?></td>
                        <td><span class="badge-pill <?= getSourceBadgeClass($row['source_type']) ?>"><?= $row['source_type'] ?></span></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($row['source_name']) ?></td>
                        <td class="amount-income"><?= formatCurrency($row['amount']) ?></td>
                        <td><?= date('d M Y', strtotime($row['date_received'])) ?></td>
                        <td><?= $row['animal_name'] ? htmlspecialchars($row['animal_name']) : '<span style="color:#94a3b8;">—</span>' ?></td>
                        <td><?= $row['logged_by']   ? htmlspecialchars($row['logged_by'])   : '<span style="color:#94a3b8;">System</span>' ?></td>
                        <td class="pe-4" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;"
                            title="<?= htmlspecialchars($row['notes']) ?>"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Category</th>
                            <th>Vendor / Payee</th>
                            <th>Amount</th>
                            <th>Date Paid</th>
                            <th>Linked Animal</th>
                            <th>Linked Event</th>
                            <th class="pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($data as $row): ?>
                    <tr>
                        <td class="ps-4" style="color:#94a3b8;font-size:0.72rem;">#<?= $row['id'] ?></td>
                        <td><span class="badge-pill <?= getCategoryBadgeClass($row['category']) ?>"><?= $row['category'] ?></span></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($row['vendor_name_or_payee']) ?></td>
                        <td class="amount-expense"><?= formatCurrency($row['amount']) ?></td>
                        <td><?= date('d M Y', strtotime($row['date_paid'])) ?></td>
                        <td><?= $row['animal_name'] ? htmlspecialchars($row['animal_name']) : '<span style="color:#94a3b8;">—</span>' ?></td>
                        <td><?= $row['event_name']  ? htmlspecialchars($row['event_name'])  : '<span style="color:#94a3b8;">—</span>' ?></td>
                        <td class="pe-4" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;"
                            title="<?= htmlspecialchars($row['notes']) ?>"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-bar">
                <div class="page-info">Showing <?= count($data) ?> of <?= $total_rows ?> records</div>
                <div class="page-btns">
                    <a href="<?= buildUrl(['page'=>max(1,$page-1)]) ?>"
                       class="page-btn <?= $page<=1?'disabled':'' ?>">‹ Prev</a>
                    <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                        <a href="<?= buildUrl(['page'=>$i]) ?>"
                           class="page-btn <?= $i===$page?'current':'' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="<?= buildUrl(['page'=>min($total_pages,$page+1)]) ?>"
                       class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next ›</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>