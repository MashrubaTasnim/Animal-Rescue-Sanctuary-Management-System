<?php
session_start();
include 'db_config.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$uid = intval($_GET['uid'] ?? 0);
if (!$uid) {
    header("Location: manage_users.php");
    exit();
}

// ── Fetch user ────────────────────────────────────────────────────────────────
$u = $conn->prepare("SELECT id, full_name, email, phone, role, status, profile_image, created_at FROM users WHERE id = ?");
$u->bind_param("i", $uid);
$u->execute();
$user = $u->get_result()->fetch_assoc();
if (!$user) { header("Location: manage_users.php"); exit(); }

$role = $user['role'];

// ── Role-based stats ──────────────────────────────────────────────────────────

if ($role === 'user') {

    // SOS reports
    $sos_total   = $conn->query("SELECT COUNT(*) c FROM rescues WHERE user_id=$uid")->fetch_assoc()['c'];
    $sos_pending = $conn->query("SELECT COUNT(*) c FROM rescues WHERE user_id=$uid AND status='Pending'")->fetch_assoc()['c'];
    $sos_resolved= $conn->query("SELECT COUNT(*) c FROM rescues WHERE user_id=$uid AND status='Resolved'")->fetch_assoc()['c'];

    // Adoptions
    $adopt_applied  = $conn->query("SELECT COUNT(*) c FROM adoption_requests WHERE user_id=$uid")->fetch_assoc()['c'];
    $adopt_approved = $conn->query("SELECT COUNT(*) c FROM adoption_requests WHERE user_id=$uid AND status='approved'")->fetch_assoc()['c'];
    $adopt_pending  = $conn->query("SELECT COUNT(*) c FROM adoption_requests WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'];

    // Surrenders
    $sur_total    = $conn->query("SELECT COUNT(*) c FROM surrender_requests WHERE user_id=$uid")->fetch_assoc()['c'];
    $sur_approved = $conn->query("SELECT COUNT(*) c FROM surrender_requests WHERE user_id=$uid AND status='Approved'")->fetch_assoc()['c'];

    // Donations
    $don_total  = $conn->query("SELECT COUNT(*) c FROM donations WHERE user_id=$uid AND status='Approved'")->fetch_assoc()['c'];
    $don_amount = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM donations WHERE user_id=$uid AND status='Approved'")->fetch_assoc()['s'];

    // Sponsorships
    $spon_total  = $conn->query("SELECT COUNT(*) c FROM resident_sponsorships WHERE user_id=$uid AND status='Active'")->fetch_assoc()['c'];

    // Recent SOS
    $recent_sos = $conn->query("SELECT id, species, location, status, severity_score, created_at FROM rescues WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");

    // Recent adoptions
    $recent_adopt = $conn->query("SELECT ar.id, a.name, a.species, ar.status, ar.request_date FROM adoption_requests ar LEFT JOIN animals a ON ar.animal_id=a.id WHERE ar.user_id=$uid ORDER BY ar.request_date DESC LIMIT 8");

    // Recent surrenders
    $recent_sur = $conn->query("SELECT id, species, breed, status, urgency, created_at FROM surrender_requests WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");

} elseif ($role === 'rescuer') {

    $r_total     = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_rescuer=$uid")->fetch_assoc()['c'];
    $r_active    = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_rescuer=$uid AND status='In Progress'")->fetch_assoc()['c'];
    $r_review    = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_rescuer=$uid AND status='Under Review'")->fetch_assoc()['c'];
    $r_resolved  = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_rescuer=$uid AND status='Resolved'")->fetch_assoc()['c'];
    $r_vet       = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_rescuer=$uid AND status IN ('Assigned to Vet','Vet Cleared')")->fetch_assoc()['c'];

    $active_cases   = $conn->query("SELECT id, species, location, status, severity_score, created_at FROM rescues WHERE assigned_rescuer=$uid AND status NOT IN ('Resolved') ORDER BY created_at DESC LIMIT 8");
    $resolved_cases = $conn->query("SELECT id, species, location, status, severity_score, resolved_at FROM rescues WHERE assigned_rescuer=$uid AND status='Resolved' ORDER BY resolved_at DESC LIMIT 8");

} elseif ($role === 'vet') {

    $v_total    = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_vet=$uid")->fetch_assoc()['c'];
    $v_active   = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_vet=$uid AND status='Assigned to Vet'")->fetch_assoc()['c'];
    $v_cleared  = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_vet=$uid AND status='Vet Cleared'")->fetch_assoc()['c'];
    $v_resolved = $conn->query("SELECT COUNT(*) c FROM rescues WHERE assigned_vet=$uid AND status='Resolved'")->fetch_assoc()['c'];

    $active_patients  = $conn->query("SELECT id, species, breed, status, created_at FROM rescues WHERE assigned_vet=$uid AND status='Assigned to Vet' ORDER BY created_at DESC LIMIT 8");
    $cleared_patients = $conn->query("SELECT id, species, breed, status, vet_cleared_at FROM rescues WHERE assigned_vet=$uid AND status IN ('Vet Cleared','Resolved') ORDER BY vet_cleared_at DESC LIMIT 8");
}

// ── Severity label helper ────────────────────────────────────────────────────
function severityLabel($score) {
    $s = intval($score);
    if ($s >= 5) return ['Critical',   '#ef4444'];
    if ($s >= 4) return ['Urgent',     '#f97316'];
    if ($s >= 3) return ['Moderate',   '#eab308'];
    return              ['Stable',     '#22c55e'];
}

function statusBadge($status) {
    $map = [
        'Pending'         => '#94a3b8',
        'Approved'        => '#22c55e',
        'approved'        => '#22c55e',
        'Rejected'        => '#ef4444',
        'rejected'        => '#ef4444',
        'pending'         => '#94a3b8',
        'In Progress'     => '#3b82f6',
        'Under Review'    => '#a855f7',
        'Assigned to Vet' => '#f97316',
        'Vet Cleared'     => '#10b981',
        'Resolved'        => '#6366f1',
        'Active'          => '#22c55e',
        'cancelled'       => '#ef4444',
    ];
    $color = $map[$status] ?? '#94a3b8';
    return "<span style='background:{$color}20;color:{$color};padding:2px 10px;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:0.5px'>{$status}</span>";
}

$avatar = !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : null;
$initials = strtoupper(substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Report — <?= htmlspecialchars($user['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --navy: #0a1329;
            --gold:  #B8860B;
            --gold-light: #f0c040;
        }

        * { box-sizing: border-box; }

        body {
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* ── TOP BAR ── */
        .rpt-topbar {
            background: var(--navy);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--gold);
        }
        .rpt-topbar .back-btn {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color .2s;
        }
        .rpt-topbar .back-btn:hover { color: var(--gold-light); }
        .rpt-topbar .title {
            color: var(--gold-light);
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .rpt-topbar .print-btn {
            background: var(--gold);
            color: var(--navy);
            border: none;
            padding: 7px 18px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
        }
        .rpt-topbar .print-btn:hover { background: var(--gold-light); }

        /* ── PROFILE CARD ── */
        .profile-card {
            background: var(--navy);
            margin: 28px 32px 0;
            border-radius: 16px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            border: 1px solid rgba(184,134,11,0.3);
        }
        .profile-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            border: 3px solid var(--gold);
            object-fit: cover;
            flex-shrink: 0;
        }
        .profile-initials {
            width: 72px; height: 72px;
            border-radius: 50%;
            border: 3px solid var(--gold);
            background: rgba(184,134,11,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; font-weight: 800;
            color: var(--gold-light);
            flex-shrink: 0;
        }
        .profile-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 4px;
        }
        .profile-meta {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.55);
            margin: 0;
        }
        .profile-meta span { margin-right: 16px; }
        .role-pill {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 8px;
        }
        .role-user     { background: rgba(59,130,246,0.2);  color: #60a5fa; }
        .role-rescuer  { background: rgba(249,115,22,0.2);  color: #fb923c; }
        .role-vet      { background: rgba(16,185,129,0.2);  color: #34d399; }
        .role-admin    { background: rgba(239,68,68,0.2);   color: #f87171; }
        .status-active     { background: rgba(34,197,94,0.15);  color: #4ade80; }
        .status-restricted { background: rgba(239,68,68,0.15);  color: #f87171; }

        /* ── CONTENT AREA ── */
        .rpt-body { padding: 24px 32px 40px; }

        /* ── STAT CARDS ── */
        .stat-grid { display: grid; gap: 14px; margin-bottom: 28px; }
        .stat-grid-user    { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat-grid-rescuer { grid-template-columns: repeat(5, 1fr); }
        .stat-grid-vet     { grid-template-columns: repeat(4, 1fr); }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            border-top: 3px solid var(--navy);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--navy);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
        }
        .stat-card.gold { border-top-color: var(--gold); }
        .stat-card.gold .stat-number { color: var(--gold); }
        .stat-card.green { border-top-color: #22c55e; }
        .stat-card.green .stat-number { color: #16a34a; }
        .stat-card.blue  { border-top-color: #3b82f6; }
        .stat-card.blue  .stat-number { color: #2563eb; }
        .stat-card.red   { border-top-color: #ef4444; }
        .stat-card.red   .stat-number { color: #dc2626; }
        .stat-card.purple{ border-top-color: #a855f7; }
        .stat-card.purple .stat-number { color: #9333ea; }

        /* ── SECTION ── */
        .rpt-section { margin-bottom: 28px; }
        .rpt-section-title {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--navy);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rpt-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* ── TABLE ── */
        .rpt-table {
            width: 100%;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            border-collapse: collapse;
        }
        .rpt-table thead tr {
            background: var(--navy);
            color: rgba(255,255,255,0.7);
        }
        .rpt-table thead th {
            padding: 10px 16px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
        }
        .rpt-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .15s;
        }
        .rpt-table tbody tr:last-child { border-bottom: none; }
        .rpt-table tbody tr:hover { background: #f8fafc; }
        .rpt-table tbody td {
            padding: 10px 16px;
            font-size: 0.8rem;
            color: #334155;
            vertical-align: middle;
        }
        .empty-row td {
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            padding: 24px;
        }

        /* ── PRINT STYLES ── */
        @media print {
            .rpt-topbar .print-btn,
            .rpt-topbar .back-btn { display: none !important; }
            body { background: #fff; }
            .profile-card { border: 1px solid #ccc; }
            .rpt-topbar { border-bottom: 1px solid #ccc; }
            .stat-card { border: 1px solid #ccc; break-inside: avoid; }
            .rpt-table { break-inside: avoid; }
            .rpt-section { break-inside: avoid; }
        }

        @media (max-width: 768px) {
            .profile-card { flex-direction: column; text-align: center; margin: 16px; }
            .rpt-body { padding: 16px; }
            .rpt-topbar { padding: 12px 16px; }
            .stat-grid-rescuer,
            .stat-grid-vet { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="rpt-topbar" id="topbar">
    <a href="manage_users.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
    <span class="title"><i class="fas fa-chart-bar me-2"></i>Activity Report</span>
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<!-- PROFILE CARD -->
<div class="profile-card" id="profile-section">
    <?php if ($avatar): ?>
        <img src="<?= $avatar ?>" class="profile-avatar" alt="Avatar">
    <?php else: ?>
        <div class="profile-initials"><?= $initials ?></div>
    <?php endif; ?>
    <div>
        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <p class="profile-meta">
            <span><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></span>
            <?php if ($user['phone']): ?>
                <span><i class="fas fa-phone me-1"></i><?= htmlspecialchars($user['phone']) ?></span>
            <?php endif; ?>
            <span><i class="fas fa-calendar me-1"></i>Joined <?= date('d M Y', strtotime($user['created_at'])) ?></span>
        </p>
        <span class="role-pill role-<?= $role ?>"><?= ucfirst($role) ?></span>
        <span class="role-pill status-<?= $user['status'] === 'active' ? 'active' : 'restricted' ?> ms-1">
            <?= ucfirst($user['status']) ?>
        </span>
    </div>
</div>

<!-- REPORT BODY -->
<div class="rpt-body">

    <?php if ($role === 'user'): ?>

    <!-- ── USER STATS ── -->
    <div class="stat-grid stat-grid-user">
        <div class="stat-card blue">
            <div class="stat-number"><?= $sos_total ?></div>
            <div class="stat-label">SOS Reports</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?= $sos_resolved ?></div>
            <div class="stat-label">Resolved SOS</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number"><?= $adopt_applied ?></div>
            <div class="stat-label">Adoptions Applied</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?= $adopt_approved ?></div>
            <div class="stat-label">Adoptions Approved</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-number"><?= $sur_total ?></div>
            <div class="stat-label">Surrenders</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number">৳<?= number_format($don_amount, 0) ?></div>
            <div class="stat-label">Total Donated</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?= $don_total ?></div>
            <div class="stat-label">Donations Made</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number"><?= $spon_total ?></div>
            <div class="stat-label">Active Sponsors</div>
        </div>
    </div>

    <!-- SOS HISTORY -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-ambulance"></i> SOS Reports</div>
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Species</th>
                    <th>Location</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_sos->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="6">No SOS reports submitted</td></tr>
                <?php else: while ($row = $recent_sos->fetch_assoc()):
                    [$slabel, $scolor] = severityLabel($row['severity_score']); ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><span style="color:<?= $scolor ?>;font-weight:700;font-size:0.75rem"><?= $slabel ?> (<?= $row['severity_score'] ?>)</span></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ADOPTION HISTORY -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-heart"></i> Adoption Requests</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Animal</th><th>Species</th><th>Status</th><th>Applied</th></tr>
            </thead>
            <tbody>
                <?php if ($recent_adopt->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="5">No adoption requests submitted</td></tr>
                <?php else: while ($row = $recent_adopt->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars($row['name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= date('d M Y', strtotime($row['request_date'])) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SURRENDER HISTORY -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-paw"></i> Surrender Requests</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Species</th><th>Breed</th><th>Urgency</th><th>Status</th><th>Submitted</th></tr>
            </thead>
            <tbody>
                <?php if ($recent_sur->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="6">No surrender requests submitted</td></tr>
                <?php else: while ($row = $recent_sur->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'])) ?></td>
                        <td><?= htmlspecialchars($row['breed'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($row['urgency']) ?></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($role === 'rescuer'): ?>

    <!-- ── RESCUER STATS ── -->
    <div class="stat-grid stat-grid-rescuer">
        <div class="stat-card blue">
            <div class="stat-number"><?= $r_total ?></div>
            <div class="stat-label">Total Assigned</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number"><?= $r_active ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-number"><?= $r_review ?></div>
            <div class="stat-label">Under Review</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number"><?= $r_vet ?></div>
            <div class="stat-label">Sent to Vet</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?= $r_resolved ?></div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>

    <!-- ACTIVE CASES -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-person-running"></i> Active & Ongoing Cases</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Species</th><th>Location</th><th>Severity</th><th>Status</th><th>Assigned</th></tr>
            </thead>
            <tbody>
                <?php if ($active_cases->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="6">No active cases</td></tr>
                <?php else: while ($row = $active_cases->fetch_assoc()):
                    [$slabel, $scolor] = severityLabel($row['severity_score']); ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><span style="color:<?= $scolor ?>;font-weight:700;font-size:0.75rem"><?= $slabel ?></span></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- RESOLVED CASES -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-check-circle"></i> Completed Rescues</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Species</th><th>Location</th><th>Severity</th><th>Status</th><th>Resolved</th></tr>
            </thead>
            <tbody>
                <?php if ($resolved_cases->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="6">No completed rescues yet</td></tr>
                <?php else: while ($row = $resolved_cases->fetch_assoc()):
                    [$slabel, $scolor] = severityLabel($row['severity_score']); ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><span style="color:<?= $scolor ?>;font-weight:700;font-size:0.75rem"><?= $slabel ?></span></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= $row['resolved_at'] ? date('d M Y', strtotime($row['resolved_at'])) : '—' ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($role === 'vet'): ?>

    <!-- ── VET STATS ── -->
    <div class="stat-grid stat-grid-vet">
        <div class="stat-card blue">
            <div class="stat-number"><?= $v_total ?></div>
            <div class="stat-label">Total Patients</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number"><?= $v_active ?></div>
            <div class="stat-label">Under Treatment</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?= $v_cleared ?></div>
            <div class="stat-label">Cleared</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-number"><?= $v_resolved ?></div>
            <div class="stat-label">Fully Resolved</div>
        </div>
    </div>

    <!-- ACTIVE PATIENTS -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-stethoscope"></i> Current Patients (Under Treatment)</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Species</th><th>Breed</th><th>Status</th><th>Assigned</th></tr>
            </thead>
            <tbody>
                <?php if ($active_patients->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="5">No patients currently under treatment</td></tr>
                <?php else: while ($row = $active_patients->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($row['breed'] ?: '—') ?></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- CLEARED PATIENTS -->
    <div class="rpt-section">
        <div class="rpt-section-title"><i class="fas fa-clipboard-check"></i> Cleared Animals</div>
        <table class="rpt-table">
            <thead>
                <tr><th>#ID</th><th>Species</th><th>Breed</th><th>Status</th><th>Cleared On</th></tr>
            </thead>
            <tbody>
                <?php if ($cleared_patients->num_rows === 0): ?>
                    <tr class="empty-row"><td colspan="5">No animals cleared yet</td></tr>
                <?php else: while ($row = $cleared_patients->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($row['species'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($row['breed'] ?: '—') ?></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td><?= $row['vet_cleared_at'] ? date('d M Y', strtotime($row['vet_cleared_at'])) : '—' ?></td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <p>Activity report is not available for <?= ucfirst($role) ?> accounts.</p>
        </div>
    <?php endif; ?>

    <!-- REPORT FOOTER -->
    <div style="text-align:right;color:#94a3b8;font-size:0.72rem;margin-top:32px;padding-top:16px;border-top:1px solid #e2e8f0">
        Report generated on <?= date('d M Y, h:i A') ?> &nbsp;·&nbsp; Admin: <?= htmlspecialchars($_SESSION['name'] ?? 'Administrator') ?>
    </div>

</div><!-- /rpt-body -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>