<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$query = "SELECT d.*, u.full_name, u.email 
          FROM donations d 
          LEFT JOIN users u ON d.user_id = u.id 
          ORDER BY d.created_at DESC";
$donations = $conn->query($query);

$total_funds  = $conn->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;
$cnt_total    = $conn->query("SELECT COUNT(*) as t FROM donations")->fetch_assoc()['t'];
$cnt_today    = $conn->query("SELECT COUNT(*) as t FROM donations WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['t'];
$today_amount = $conn->query("SELECT SUM(amount) as t FROM donations WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['t'] ?? 0;

// Method breakdown for sidebar
$methods = $conn->query("SELECT method, COUNT(*) as cnt FROM donations GROUP BY method ORDER BY cnt DESC");

$view = $_GET['view'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donation Logs | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy:  #0a1329;
            --gold:  #B8860B;
            --green: #198754;
            --blue:  #0d6efd;
        }

        body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }

        /* ── LAYOUT ── */
        .dn-wrapper { display:flex; min-height:calc(100vh - 70px); }
        .dn-sidebar {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .dn-content { flex:1; padding:32px 28px 60px; min-width:0; }

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

        .sidebar-divider {
            margin:12px 20px;
            border-top:1px solid rgba(255,255,255,0.08);
            font-size:0.55rem; font-weight:800; letter-spacing:2px;
            color:rgba(255,255,255,0.25); text-transform:uppercase;
            padding-top:12px;
        }

        /* ── STAT CARDS ── */
        .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .stat-card {
            background:#fff; border-radius:16px;
            border:1px solid #e2e8f0; padding:20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.03);
        }
        .stat-card .stat-icon {
            width:40px; height:40px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; margin-bottom:12px;
        }
        .stat-card .stat-val { font-size:1.3rem; font-weight:800; color:var(--navy); }
        .stat-card .stat-lbl { font-size:0.65rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; }

        /* ── PAGE HEADER ── */
        .dn-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; }
        .dn-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .dn-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .dn-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background:#fff; border-radius:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid #e2e8f0; overflow:hidden;
        }
        .glass-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; gap:10px;
        }
        .glass-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px;
        }

        /* ── TABLE ── */
        .donor-avatar {
            width:40px; height:40px; border-radius:12px;
            background:var(--navy); color:var(--gold);
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:0.95rem; flex-shrink:0;
        }
        .method-pill {
            font-size:0.6rem; font-weight:800; text-transform:uppercase;
            padding:4px 10px; border-radius:20px; letter-spacing:0.5px;
            background:#f1f5f9; color:#475569;
        }
        .trx-id {
            font-size:0.65rem; font-weight:800; color:var(--gold);
            text-transform:uppercase; letter-spacing:0.5px;
        }
        .amount-text {
            font-weight:800; font-size:0.92rem; color:var(--green);
        }
        .empty-msg { padding:40px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }
    </style>
</head>
<body>
<?php include 'chat_widget.php'; ?>
<?php include 'navbar.php'; ?>

<div class="dn-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="dn-sidebar">
        <div class="sidebar-head">
            <span>Financial Ledger</span>
            <p>Donation Logs</p>
        </div>

        <a href="?view=all" class="sidebar-item <?= $view=='all' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-vault"></i></div>
            All Donations
            <?php if($cnt_total > 0): ?>
                <span class="sidebar-badge" style="background:var(--gold);"><?= $cnt_total ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=today" class="sidebar-item <?= $view=='today' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-calendar-day"></i></div>
            Today
            <?php if($cnt_today > 0): ?>
                <span class="sidebar-badge" style="background:var(--green);"><?= $cnt_today ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar-divider">Payment Methods</div>

        <?php $methods->data_seek(0); while($m = $methods->fetch_assoc()): ?>
        <a href="?view=method&method=<?= urlencode($m['method']) ?>"
           class="sidebar-item <?= ($view=='method' && ($_GET['method']??'')==$m['method']) ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-credit-card"></i></div>
            <?= htmlspecialchars($m['method']) ?>
            <span class="sidebar-badge" style="background:#334155;"><?= $m['cnt'] ?></span>
        </a>
        <?php endwhile; ?>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="dn-content">

        <!-- Page Header -->
        <div class="dn-page-header">
            <div class="dn-accent-bar" style="background:var(--gold);"></div>
            <h5><i class="fas fa-vault me-2" style="color:var(--gold);"></i>
                <?php
                if ($view === 'today')     echo 'Today\'s Donations';
                elseif ($view === 'anonymous') echo 'Anonymous Donors';
                elseif ($view === 'method')    echo htmlspecialchars($_GET['method'] ?? '') . ' Donations';
                else                          echo 'All Donations';
                ?>
            </h5>
            <p>Financial Ledger &rsaquo; Donation Logs</p>
        </div>

        <!-- Stat Cards -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i class="fas fa-bangladeshi-taka-sign" style="color:var(--gold);"></i>
                </div>
                <div class="stat-val">৳ <?= number_format($total_funds, 0) ?></div>
                <div class="stat-lbl">Total Collected</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;">
                    <i class="fas fa-hand-holding-heart" style="color:var(--green);"></i>
                </div>
                <div class="stat-val"><?= $cnt_total ?></div>
                <div class="stat-lbl">Total Donations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="fas fa-calendar-day" style="color:var(--blue);"></i>
                </div>
                <div class="stat-val">৳ <?= number_format($today_amount, 0) ?></div>
                <div class="stat-lbl">Today's Amount</div>
            </div>
        </div>

        <!-- Table Card -->
        <?php
        if ($view === 'today') {
            $donations = $conn->query("SELECT d.*, u.full_name, u.email FROM donations d LEFT JOIN users u ON d.user_id = u.id WHERE DATE(d.created_at) = CURDATE() ORDER BY d.created_at DESC");
        } elseif ($view === 'method' && !empty($_GET['method'])) {
            $m_safe = $conn->real_escape_string($_GET['method']);
            $donations = $conn->query("SELECT d.*, u.full_name, u.email FROM donations d LEFT JOIN users u ON d.user_id = u.id WHERE d.method = '$m_safe' ORDER BY d.created_at DESC");
        } else {
            $donations->data_seek(0);
        }
        ?>

        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid var(--gold);">
                <i class="fas fa-list" style="color:var(--gold);"></i>
                <h6>Transaction Records</h6>
            </div>

            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <thead style="background:#f8fafc; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; font-weight:800;">
                        <tr>
                            <th class="ps-4 py-3">Contributor</th>
                            <th>Transaction</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($donations && $donations->num_rows > 0):
                        while($row = $donations->fetch_assoc()):
                            $name    = $row['full_name'] ?? 'Anonymous';
                            $email   = $row['email']     ?? 'Guest';
                            $initial = strtoupper(substr($name, 0, 1));
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="donor-avatar"><?= $initial ?></div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:0.85rem;">
                                        <?= htmlspecialchars($name) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.7rem;">
                                        <?= htmlspecialchars($email) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="trx-id mb-1">
                                <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($row['trx_id']) ?>
                            </div>
                            <span class="method-pill"><?= htmlspecialchars($row['method']) ?></span>
                        </td>
                        <td style="font-size:0.75rem; color:#94a3b8;">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('M d, Y', strtotime($row['created_at'])) ?>
                        </td>
                        <td class="text-end pe-4">
                            <span class="amount-text">৳ <?= number_format($row['amount'], 2) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4">
                        <div class="empty-msg">
                            <i class="fas fa-receipt d-block mb-2" style="font-size:2rem; color:#cbd5e1;"></i>
                            No donation records found.
                        </div>
                    </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>