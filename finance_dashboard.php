<?php
session_start();
include 'db_config.php';
require_once 'finance_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=unauthorized'); exit();
}

$summary     = getFinancialSummary($conn);
$sponsors    = getActiveSponsors($conn);
$net_pos     = $summary['net_balance'] >= 0;

$funding_labels = json_encode(array_keys($summary['funding_by_source']));
$funding_values = json_encode(array_values($summary['funding_by_source']));
$expense_labels = json_encode(array_keys($summary['expense_by_category']));
$expense_values = json_encode(array_values($summary['expense_by_category']));
$trend_months   = json_encode(array_column($summary['monthly_trend'], 'month'));
$trend_income   = json_encode(array_column($summary['monthly_trend'], 'income'));
$trend_expense  = json_encode(array_column($summary['monthly_trend'], 'expense'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Overview | <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root { --navy:#0a1329; --gold:#B8860B; --gold-lt:#ffc107; --sos:#ff4d4d; --orange:#f97316; }
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
.ma-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; background:var(--gold); }
.glass-card { background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden; }
.glass-card-header { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; }
.glass-card-header h6 { font-weight:800; font-size:0.78rem; text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px; }
.btn-cmd { border-radius:9px; font-weight:800; font-size:0.62rem; text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }

/* KPI CARDS */
.kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.kpi-card { background:#fff; border-radius:18px; padding:22px 20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; border-top:4px solid transparent; position:relative; overflow:hidden; }
.kpi-card.green { border-top-color:#16a34a; }
.kpi-card.red   { border-top-color:#dc2626; }
.kpi-card.blue  { border-top-color:var(--navy); }
.kpi-card.gold  { border-top-color:var(--gold-lt); }
.kpi-label { font-size:0.65rem; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:#94a3b8; margin-bottom:10px; }
.kpi-value { font-size:1.8rem; font-weight:800; color:var(--navy); line-height:1; margin-bottom:6px; }
.kpi-value.green { color:#16a34a; }
.kpi-value.red   { color:#dc2626; }
.kpi-value.gold  { color:var(--gold-lt); }
.kpi-sub { font-size:0.72rem; color:#94a3b8; }
.kpi-icon { position:absolute; top:18px; right:18px; font-size:1.6rem; opacity:0.08; }

/* QUICK ACTIONS */
.qa-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
.qa-card { background:#fff; border:1.5px solid #e2e8f0; border-radius:14px; padding:16px 12px; text-align:center; cursor:pointer; transition:all 0.2s; text-decoration:none; color:var(--navy); }
.qa-card:hover { border-color:var(--gold-lt); transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.07); color:var(--navy); }
.qa-icon { font-size:1.5rem; margin-bottom:8px; display:block; }
.qa-label { font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; }

/* CHARTS */
.chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
.chart-card { background:#fff; border-radius:18px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; }
.chart-title { font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--navy); margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.chart-full { grid-column:1 / -1; }
canvas { max-height:260px; }

/* RECENT TX */
.tx-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
.tx-item { display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
.tx-item:last-child { border-bottom:none; }
.tx-item:hover { background:#f8fafc; }
.tx-dot { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }
.tx-dot.income  { background:#dcfce7; }
.tx-dot.expense { background:#fee2e2; }
.tx-info { flex:1; }
.tx-name { font-size:0.82rem; font-weight:700; color:var(--navy); }
.tx-meta { font-size:0.7rem; color:#94a3b8; margin-top:2px; }
.tx-amount { font-weight:800; font-size:0.88rem; white-space:nowrap; }
.tx-amount.income  { color:#16a34a; }
.tx-amount.expense { color:#dc2626; }
.tx-header { padding:14px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
.tx-header h6 { font-weight:800; font-size:0.75rem; text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px; }

/* BADGES */
.badge-pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:0.65rem; font-weight:700; }
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

/* SPONSOR */
.sponsor-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; }
.sponsor-card { background:#fff; border-radius:14px; padding:16px; border:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.03); }
.sponsor-avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid var(--gold-lt); flex-shrink:0; }
.sponsor-avatar-ph { width:44px; height:44px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:1.1rem; border:2px solid var(--gold-lt); flex-shrink:0; }
.sponsor-info h6 { font-size:0.85rem; font-weight:700; color:var(--navy); margin:0 0 2px; }
.sponsor-info p  { font-size:0.72rem; color:#94a3b8; margin:0; }
.sponsor-amount  { font-size:0.8rem; font-weight:800; color:#16a34a; margin-top:4px; }

/* NET STATUS */
.net-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700; }
.net-pill.surplus { background:#dcfce7; color:#15803d; }
.net-pill.deficit { background:#fee2e2; color:#991b1b; }

/* EMPTY */
.empty-state { text-align:center; padding:40px 20px; color:#94a3b8; font-size:0.82rem; }
.empty-state i { font-size:2rem; display:block; margin-bottom:10px; opacity:0.3; }

@media(max-width:900px) {
    .kpi-grid, .chart-grid, .tx-grid { grid-template-columns:1fr; }
    .qa-grid { grid-template-columns:repeat(2,1fr); }
    .chart-full { grid-column:auto; }
}
.btn-print {
    border-radius:9px; font-weight:800; font-size:0.62rem;
    text-transform:uppercase; padding:8px 14px;
    border:1px solid #e2e8f0; background:#f8fafc;
    color:#475569; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px; transition:all 0.15s;
}
.btn-print:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--navy); }

.print-only { display:none; }
@media print {
    .ma-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print,
    .qa-grid, [class*="chat"] { display:none !important; }
    body { background:#fff !important; font-size:11pt; }
    .ma-wrapper { display:block; }
    .ma-content  { padding:0 !important; }
    .ma-page-header { display:block !important; }

    /* KPI cards — force 3 columns on print */
    .kpi-grid { display:grid !important; grid-template-columns:repeat(3,1fr) !important; gap:12px !important; }
    .kpi-card { border:1px solid #ccc !important; border-radius:8px !important; box-shadow:none !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .kpi-card.green { border-top:4px solid #16a34a !important; }
    .kpi-card.red   { border-top:4px solid #dc2626 !important; }
    .kpi-card.blue  { border-top:4px solid #0a1329 !important; }

    /* Charts — stack and limit height */
    .chart-grid { display:block !important; }
    .chart-card { margin-bottom:16px; page-break-inside:avoid; border:1px solid #ccc !important; border-radius:8px !important; box-shadow:none !important; }
    canvas { max-height:200px !important; }

    /* Transactions — stack */
    .tx-grid { display:block !important; }
    .glass-card { box-shadow:none !important; border:1px solid #ccc !important; border-radius:8px !important; margin-bottom:16px; }
    .tx-item { page-break-inside:avoid; }

    /* Sponsors */
    .sponsor-grid { display:grid !important; grid-template-columns:repeat(2,1fr) !important; }
    .sponsor-card { border:1px solid #ccc !important; border-radius:8px !important; box-shadow:none !important; page-break-inside:avoid; }
    .glass-card-header { border-radius:8px 8px 0 0 !important; }

    .net-pill, .badge-pill { -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .print-only { display:block !important; }
    .print-only-header {
        display:flex !important; align-items:center;
        justify-content:space-between; padding-bottom:12px;
        margin-bottom:18px; border-bottom:2px solid #0a1329;
    }
    .print-only-header h3   { font-size:14pt; font-weight:800; color:#0a1329; margin:0; }
    .print-only-header .print-meta { font-size:9pt; color:#64748b; text-align:right; }
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
        <a href="finance_dashboard.php" class="sidebar-item active">
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

        <div class="print-only">
    <div class="print-only-header">
        <h3>Heartbeat Heaven &mdash; Financial Overview</h3>
        <div class="print-meta">
            <div style="font-weight:700;">Sanctuary Funding &rsaquo; Income &amp; Expenditure</div>
            <div id="printDateStamp"></div>
        </div>
    </div>
</div>

<div class="ma-page-header">
    <div>
        <div class="ma-accent-bar"></div>
        <h5>Financial Overview</h5>
        <p>Sanctuary Funding &rsaquo; Income &amp; Expenditure</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <button class="btn-print" onclick="printPage()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="finance_log_income.php" class="btn-cmd text-white" style="background:#16a34a;">
            <i class="fas fa-plus"></i> Log Funding
        </a>
        <a href="finance_log_expense.php" class="btn-cmd text-white" style="background:#dc2626;">
            <i class="fas fa-plus"></i> Log Expense
        </a>
        <a href="finance_ledger.php" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
            <i class="fas fa-table"></i> Ledger
        </a>
    </div>
</div>

        <!-- KPI CARDS -->
        <div class="kpi-grid">
            <div class="kpi-card green">
                <div class="kpi-icon"><i class="fas fa-arrow-down" style="color:#16a34a;"></i></div>
                <div class="kpi-label">Total Funding Received</div>
                <div class="kpi-value green"><?= formatCurrency($summary['total_funding']) ?></div>
                <div class="kpi-sub"><?= count($summary['funding_by_source']) ?> active income streams</div>
            </div>
            <div class="kpi-card red">
                <div class="kpi-icon"><i class="fas fa-arrow-up" style="color:#dc2626;"></i></div>
                <div class="kpi-label">Total Expenditure</div>
                <div class="kpi-value red"><?= formatCurrency($summary['total_expenditure']) ?></div>
                <div class="kpi-sub"><?= count($summary['expense_by_category']) ?> expense categories</div>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-icon"><i class="fas fa-scale-balanced" style="color:var(--navy);"></i></div>
                <div class="kpi-label">Net Sanctuary Balance</div>
                <div class="kpi-value"><?= formatCurrency(abs($summary['net_balance'])) ?></div>
                <div class="kpi-sub">
                    <span class="net-pill <?= $net_pos ? 'surplus' : 'deficit' ?>">
                        <i class="fas fa-<?= $net_pos ? 'check' : 'exclamation' ?>-circle"></i>
                        <?= $net_pos ? 'Surplus' : 'Deficit' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="qa-grid">
            <a href="finance_log_income.php?type=General+Donation" class="qa-card">
                <span class="qa-icon">💝</span>
                <div class="qa-label">Log Donation</div>
            </a>
            <a href="finance_log_income.php?type=Grant" class="qa-card">
                <span class="qa-icon">🏛️</span>
                <div class="qa-label">Log Grant</div>
            </a>
            <a href="finance_log_income.php?type=SOS+Campaign" class="qa-card">
                <span class="qa-icon">🚨</span>
                <div class="qa-label">SOS Campaign</div>
            </a>
            <a href="finance_log_income.php?type=Owner+Capital" class="qa-card">
                <span class="qa-icon">💼</span>
                <div class="qa-label">Owner Capital</div>
            </a>
            <a href="finance_log_expense.php?cat=Medical" class="qa-card">
                <span class="qa-icon">🏥</span>
                <div class="qa-label">Medical Bill</div>
            </a>
            <a href="finance_log_expense.php?cat=Staff+Salaries" class="qa-card">
                <span class="qa-icon">👤</span>
                <div class="qa-label">Pay Salary</div>
            </a>
            <a href="finance_log_expense.php?cat=Event+Management" class="qa-card">
                <span class="qa-icon">🎪</span>
                <div class="qa-label">Event Cost</div>
            </a>
            <a href="finance_sponsorships.php" class="qa-card">
                <span class="qa-icon">🌟</span>
                <div class="qa-label">Sponsorships</div>
            </a>
        </div>

        <!-- CHARTS -->
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-pie" style="color:var(--gold-lt);"></i> Funding Streams</div>
                <canvas id="fundingPie"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-bar" style="color:#dc2626;"></i> Expenditure by Category</div>
                <canvas id="expenseBar"></canvas>
            </div>
            <div class="chart-card chart-full">
                <div class="chart-title"><i class="fas fa-chart-line" style="color:var(--navy);"></i> 6-Month Income vs Expenditure Trend</div>
                <canvas id="trendLine"></canvas>
            </div>
        </div>

        <!-- RECENT TRANSACTIONS -->
        <div class="tx-grid">
            <div class="glass-card">
                <div class="tx-header">
                    <h6>💚 Recent Funding</h6>
                    <a href="finance_ledger.php?tab=income" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);font-size:0.6rem;padding:5px 10px;">View All</a>
                </div>
                <?php if(empty($summary['recent_income'])): ?>
                    <div class="empty-state"><i class="fas fa-hand-holding-heart"></i>No funding records yet.</div>
                <?php else: foreach($summary['recent_income'] as $tx): ?>
                <div class="tx-item">
                    <div class="tx-dot income"><i class="fas fa-arrow-down" style="color:#16a34a;font-size:0.75rem;"></i></div>
                    <div class="tx-info">
                        <div class="tx-name"><?= htmlspecialchars($tx['source_name']) ?></div>
                        <div class="tx-meta">
                            <span class="badge-pill <?= getSourceBadgeClass($tx['source_type']) ?>"><?= $tx['source_type'] ?></span>
                            &nbsp;<?= date('d M Y', strtotime($tx['date_received'])) ?>
                        </div>
                    </div>
                    <div class="tx-amount income"><?= formatCurrency($tx['amount']) ?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="glass-card">
                <div class="tx-header">
                    <h6>🔴 Recent Expenses</h6>
                    <a href="finance_ledger.php?tab=expense" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);font-size:0.6rem;padding:5px 10px;">View All</a>
                </div>
                <?php if(empty($summary['recent_expenses'])): ?>
                    <div class="empty-state"><i class="fas fa-receipt"></i>No expense records yet.</div>
                <?php else: foreach($summary['recent_expenses'] as $tx): ?>
                <div class="tx-item">
                    <div class="tx-dot expense"><i class="fas fa-arrow-up" style="color:#dc2626;font-size:0.75rem;"></i></div>
                    <div class="tx-info">
                        <div class="tx-name"><?= htmlspecialchars($tx['vendor_name_or_payee']) ?></div>
                        <div class="tx-meta">
                            <span class="badge-pill <?= getCategoryBadgeClass($tx['category']) ?>"><?= $tx['category'] ?></span>
                            &nbsp;<?= date('d M Y', strtotime($tx['date_paid'])) ?>
                        </div>
                    </div>
                    <div class="tx-amount expense"><?= formatCurrency($tx['amount']) ?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- ACTIVE SPONSORSHIPS -->
        <div class="glass-card-header" style="background:#fff;border-radius:18px 18px 0 0;border:1px solid #e2e8f0;border-bottom:none;margin-top:8px;">
            <i class="fas fa-star" style="color:var(--gold-lt);"></i>
            <h6 style="font-weight:800;font-size:0.75rem;text-transform:uppercase;color:var(--navy);margin:0;letter-spacing:0.5px;">Active Resident Sponsorships</h6>
            <a href="finance_sponsorships.php" class="btn-cmd ms-auto" style="background:var(--gold-lt);color:var(--navy);font-size:0.6rem;padding:5px 12px;">Manage All</a>
        </div>
        <?php if(empty($sponsors)): ?>
        <div class="glass-card" style="border-radius:0 0 18px 18px;">
            <div class="empty-state"><i class="fas fa-star"></i>No active sponsorships yet. <a href="finance_sponsorships.php">Set one up →</a></div>
        </div>
        <?php else: ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 18px 18px;padding:16px;">
            <div class="sponsor-grid">
            <?php foreach($sponsors as $sp): ?>
            <div class="sponsor-card">
                <?php if(!empty($sp['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($sp['profile_image']) ?>" class="sponsor-avatar" alt="">
                <?php else: ?>
                    <div class="sponsor-avatar-ph">🧑</div>
                <?php endif; ?>
                <div class="sponsor-info">
                    <h6><?= htmlspecialchars($sp['full_name']) ?></h6>
                    <p>Sponsoring <strong><?= htmlspecialchars($sp['animal_name']) ?></strong></p>
                    <div class="sponsor-amount"><?= formatCurrency($sp['monthly_amount']) ?>/month</div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const navyPalette = ['#0a1329','#1e3a5f','#2563eb','#3b82f6','#60a5fa','#93c5fd','#bfdbfe'];
const redPalette  = ['#991b1b','#dc2626','#ef4444','#f87171','#fca5a5','#fecaca','#fee2e2'];

new Chart(document.getElementById('fundingPie'), {
    type: 'doughnut',
    data: {
        labels: <?= $funding_labels ?>,
        datasets: [{ data: <?= $funding_values ?>, backgroundColor: navyPalette, borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive:true, plugins: { legend:{ position:'bottom', labels:{ font:{ size:11 } } }, tooltip:{ callbacks:{ label: ctx => ' ৳'+ctx.raw.toLocaleString('en-BD',{minimumFractionDigits:2}) } } } }
});

new Chart(document.getElementById('expenseBar'), {
    type: 'bar',
    data: {
        labels: <?= $expense_labels ?>,
        datasets: [{ label:'Expenditure (৳)', data: <?= $expense_values ?>, backgroundColor: redPalette, borderRadius:8, borderSkipped:false }]
    },
    options: { responsive:true, plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ' ৳'+ctx.raw.toLocaleString('en-BD',{minimumFractionDigits:2}) } } }, scales:{ x:{grid:{display:false}}, y:{ grid:{color:'#f1f5f9'}, ticks:{ callback: v => '৳'+v.toLocaleString() } } } }
});

new Chart(document.getElementById('trendLine'), {
    type: 'line',
    data: {
        labels: <?= $trend_months ?>,
        datasets: [
            { label:'Funding', data: <?= $trend_income ?>, borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,0.07)', tension:0.4, fill:true, pointBackgroundColor:'#16a34a', pointRadius:5 },
            { label:'Expenditure', data: <?= $trend_expense ?>, borderColor:'#dc2626', backgroundColor:'rgba(220,38,38,0.05)', tension:0.4, fill:true, pointBackgroundColor:'#dc2626', pointRadius:5 }
        ]
    },
    options: { responsive:true, plugins:{ legend:{ position:'top', labels:{ font:{size:12} } }, tooltip:{ callbacks:{ label: ctx => ' ৳'+ctx.raw.toLocaleString('en-BD',{minimumFractionDigits:2}) } } }, scales:{ x:{grid:{display:false}}, y:{ grid:{color:'#f1f5f9'}, ticks:{ callback: v => '৳'+v.toLocaleString() } } } }
});
</script>

<?php include 'chat_widget.php'; ?>
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