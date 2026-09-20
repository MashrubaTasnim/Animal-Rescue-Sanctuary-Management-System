<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- IMPACT SUMMARY (prepared statements per NFR-03) ---
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_sum FROM donations WHERE user_id = ? AND status = 'Approved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_donated = $stmt->get_result()->fetch_assoc()['total_sum'];

$stmt = $conn->prepare("SELECT COUNT(id) as total FROM rescues WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rescue_count = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(id) as total FROM adoption_requests WHERE user_id = ? AND status = 'approved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adoption_count = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(id) as total FROM resident_sponsorships WHERE user_id = ? AND status = 'Active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sponsor_count = $stmt->get_result()->fetch_assoc()['total'];

// --- DATA FETCHING (all prepared statements) ---
$stmt = $conn->prepare("SELECT ar.*, a.name as pet_name, a.species, a.image_path 
                         FROM adoption_requests ar 
                         JOIN animals a ON ar.animal_id = a.id 
                         WHERE ar.user_id = ? 
                         ORDER BY ar.request_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adoptions = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM donations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$donations = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM rescues WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_rescues = $stmt->get_result();

$stmt = $conn->prepare("SELECT ja.*, v.title as job_title 
                         FROM job_applications ja 
                         JOIN vacancies v ON ja.job_id = v.id 
                         WHERE ja.user_id = ? 
                         ORDER BY ja.applied_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_applications = $stmt->get_result();

$stmt = $conn->prepare("SELECT rs.*, a.name as pet_name, a.species, a.image_path 
                         FROM resident_sponsorships rs 
                         JOIN animals a ON rs.animal_id = a.id 
                         WHERE rs.user_id = ? 
                         ORDER BY rs.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_sponsorships = $stmt->get_result();

$stmt = $conn->prepare("SELECT sr.* FROM surrender_requests sr WHERE sr.user_id = ? ORDER BY sr.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_surrenders = $stmt->get_result();

$stmt = $conn->prepare("SELECT ei.*, e.title as event_title, e.event_date, e.location as event_location 
                         FROM event_interests ei 
                         JOIN events e ON ei.event_id = e.id 
                         WHERE ei.user_id = ? 
                         ORDER BY ei.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_events = $stmt->get_result();

// Helper: status pill class
function statusClass($status) {
    $s = strtolower($status);
    if (in_array($s, ['approved', 'active', 'resolved', 'cleared', 'accepted'])) return 'pill-success';
    if (in_array($s, ['pending', 'in progress', 'under treatment', 'under review'])) return 'pill-warning';
    if (in_array($s, ['rejected', 'cancelled', 'ended'])) return 'pill-danger';
    return 'pill-neutral';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Hub | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f0f2f7; }

        /* Hero */
        .hub-hero {
            background: linear-gradient(135deg, rgba(10,19,41,0.92) 0%, rgba(30,50,90,0.88) 100%),
                        url('https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=2000');
            background-size: cover; background-position: center;
            color: white; padding: 90px 0 70px;
        }
        .hub-hero h2 { font-family: 'Playfair Display', serif; font-size: 2.6rem; font-style: italic; margin-bottom: 8px; }
        .hub-hero p { opacity: 0.7; font-size: 0.95rem; letter-spacing: 0.5px; }

        /* Impact Stats */
        .impact-stats { margin-top: -52px; position: relative; z-index: 10; }
        .stat-box {
            background: white; border-radius: 18px; padding: 22px 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.08); text-align: center;
            border-top: 4px solid #B8860B; transition: transform 0.2s;
        }
        .stat-box:hover { transform: translateY(-3px); }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: #0a1329; display: block; }
        .stat-label { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 1.5px; color: #B8860B; font-weight: 700; margin-top: 4px; display: block; }

        /* Filter Nav */
        .filter-nav { margin: 44px 0 28px; }
        .nav-pills .nav-link {
            color: #444 !important; font-weight: 600; font-size: 0.78rem;
            border-radius: 50px; padding: 9px 22px; transition: all 0.25s;
            border: 1.5px solid transparent;
        }
        .nav-pills .nav-link:hover { border-color: #0a1329; }
        .nav-pills .nav-link.active { background-color: #0a1329 !important; color: #fff !important; }

        /* Log Card */
        .log-card {
            background: white; border-radius: 20px; padding: 28px 30px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04); border: 1px solid #eaeef5;
        }
        .log-card h6 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #0a1329; font-weight: 700; }

        /* Pet thumbnail */
        .pet-thumb {
            width: 40px; height: 40px; border-radius: 10px; object-fit: cover;
            border: 2px solid #eee; flex-shrink: 0;
        }
        .pet-thumb-placeholder {
            width: 40px; height: 40px; border-radius: 10px;
            background: #f0f2f7; display: flex; align-items: center; justify-content: center;
            color: #aaa; font-size: 18px; flex-shrink: 0;
        }

        /* Status pills */
        .status-pill { padding: 4px 12px; border-radius: 50px; font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block; }
        .pill-success { background: #d4f5e4; color: #1a7a4a; }
        .pill-warning { background: #fff3cd; color: #7d5a00; }
        .pill-danger  { background: #fde8e8; color: #a42020; }
        .pill-neutral { background: #eef0f4; color: #555; }

        /* Empty state */
        .empty-state { text-align: center; padding: 50px 20px; color: #aaa; }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; display: block; }
        .empty-state p { font-size: 0.9rem; margin: 0; }
        .empty-state a { color: #B8860B; font-weight: 600; }

        /* Table styles */
        .table { font-size: 0.84rem; }
        .table td { vertical-align: middle; border-color: #f0f2f7; padding: 12px 10px; }
        .table thead th { border-color: #eaeef5; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.8px; color: #999; font-weight: 700; padding: 10px; }
        .table tbody tr:hover td { background-color: #fafbfd; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="hub-hero text-center">
    <div class="container">
        <h2>Personal Activity Hub</h2>
        <p>Thank you for being a part of Heartbeat Heaven.</p>
    </div>
</section>

<div class="container impact-stats">
    <div class="row g-3 justify-content-center">
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <span class="stat-value">৳<?php echo number_format($total_donated); ?></span>
                <span class="stat-label">Contributions</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <span class="stat-value"><?php echo (int)$rescue_count; ?></span>
                <span class="stat-label">Rescues Reported</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <span class="stat-value"><?php echo (int)$adoption_count; ?></span>
                <span class="stat-label">Animals Adopted</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <span class="stat-value"><?php echo (int)$sponsor_count; ?></span>
                <span class="stat-label">Active Sponsors</span>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="text-center filter-nav">
        <ul class="nav nav-pills justify-content-center flex-wrap gap-2" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#adoptions"><i class="fas fa-paw me-1"></i>Adoptions</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#sponsorships"><i class="fas fa-heart me-1"></i>Sponsorships</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#donations"><i class="fas fa-hand-holding-heart me-1"></i>Donations</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#sos"><i class="fas fa-ambulance me-1"></i>SOS History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#surrenders"><i class="fas fa-sign-out-alt me-1"></i>Surrenders</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#events"><i class="fas fa-calendar-alt me-1"></i>Events</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#career"><i class="fas fa-briefcase me-1"></i>Applications</button></li>
        </ul>
    </div>

    <div class="tab-content">

        <!-- ADOPTIONS -->
        <div class="tab-pane fade show active" id="adoptions">
            <div class="log-card">
                <h6 class="mb-4">Adoption Logs</h6>
                <?php if ($adoptions->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-paw"></i>
                    <p>You haven't submitted any adoption requests yet.<br>
                    <a href="animals.php">Browse our animals</a> to find your perfect match.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Pet</th><th>Species</th><th>Date</th><th>Appointment</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($row = $adoptions->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($row['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="pet-thumb">
                                    <?php else: ?>
                                        <div class="pet-thumb-placeholder"><i class="fas fa-dog"></i></div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($row['pet_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($row['species'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                            <td><?php echo $row['appointment_date'] ? date('M d, Y', strtotime($row['appointment_date'])) : '<span class="text-muted">—</span>'; ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($row['status']); ?>">
                                    <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SPONSORSHIPS -->
        <div class="tab-pane fade" id="sponsorships">
            <div class="log-card">
                <h6 class="mb-4">Sponsorships</h6>
                <?php if ($my_sponsorships->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-heart"></i>
                    <p>You're not sponsoring any residents yet.<br>
                    <a href="animals.php">Find a sanctuary resident</a> to sponsor.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Animal</th><th>Monthly Amount</th><th>Since</th><th>Until</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($sp = $my_sponsorships->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($sp['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($sp['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="pet-thumb">
                                    <?php else: ?>
                                        <div class="pet-thumb-placeholder"><i class="fas fa-cat"></i></div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($sp['pet_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                            </td>
                            <td class="text-success fw-bold">৳<?php echo number_format($sp['monthly_amount']); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($sp['start_date'])); ?></td>
                            <td class="text-muted"><?php echo $sp['end_date'] ? date('M d, Y', strtotime($sp['end_date'])) : 'Ongoing'; ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($sp['status']); ?>">
                                    <?php echo htmlspecialchars($sp['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DONATIONS -->
        <div class="tab-pane fade" id="donations">
            <div class="log-card">
                <h6 class="mb-4">Donation History</h6>
                <?php if ($donations->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-hand-holding-heart"></i>
                    <p>No donations yet. <a href="donate.php">Make your first contribution</a> today.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Transaction ID</th><th>Amount</th><th>Method</th><th>Date</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($don = $donations->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted">#<?php echo htmlspecialchars($don['trx_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-success fw-bold">৳<?php echo number_format($don['amount']); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper($don['method']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($don['created_at'])); ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($don['status']); ?>">
                                    <?php echo htmlspecialchars($don['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SOS HISTORY -->
        <div class="tab-pane fade" id="sos">
            <div class="log-card">
                <h6 class="mb-4">SOS Missions</h6>
                <?php if ($my_rescues->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-ambulance"></i>
                    <p>No SOS reports yet. <a href="sos.php">Report an animal in need</a>.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Species</th><th>Location</th><th>Severity</th><th>Date</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($res = $my_rescues->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($res['species'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td class="text-muted" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?php echo htmlspecialchars(substr($res['location'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <?php if ($res['severity_score'] > 0): ?>
                                    <span class="status-pill <?php echo $res['severity_score'] >= 7 ? 'pill-danger' : ($res['severity_score'] >= 4 ? 'pill-warning' : 'pill-success'); ?>">
                                        <?php echo (int)$res['severity_score']; ?>/10
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo $res['created_at'] ? date('M d, Y', strtotime($res['created_at'])) : '—'; ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($res['status']); ?>">
                                    <?php echo htmlspecialchars($res['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SURRENDERS -->
        <div class="tab-pane fade" id="surrenders">
            <div class="log-card">
                <h6 class="mb-4">Surrender Requests</h6>
                <?php if ($my_surrenders->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-sign-out-alt"></i>
                    <p>You have no surrender requests on file.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Animal</th><th>Urgency</th><th>Appointment</th><th>Date</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($sur = $my_surrenders->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sur['species'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($sur['breed']): ?><br><small class="text-muted"><?php echo htmlspecialchars($sur['breed'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $sur['urgency'] === 'urgent' ? 'pill-danger' : ($sur['urgency'] === 'soon' ? 'pill-warning' : 'pill-neutral'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($sur['urgency']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-muted"><?php echo $sur['appointment_date'] ? date('M d, Y', strtotime($sur['appointment_date'])) : '—'; ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($sur['created_at'])); ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($sur['status']); ?>">
                                    <?php echo htmlspecialchars($sur['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EVENTS -->
        <div class="tab-pane fade" id="events">
            <div class="log-card">
                <h6 class="mb-4">Event Registrations</h6>
                <?php if ($my_events->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>You haven't registered for any events. <a href="events.php">Check upcoming events</a>.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Event</th><th>Location</th><th>Event Date</th><th>Registered On</th></tr></thead>
                        <tbody>
                        <?php while($ev = $my_events->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($ev['event_title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td class="text-muted"><?php echo htmlspecialchars($ev['event_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($ev['event_date'])); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($ev['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CAREERS -->
        <div class="tab-pane fade" id="career">
            <div class="log-card">
                <h6 class="mb-4">Job Applications</h6>
                <?php if ($my_applications->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-briefcase"></i>
                    <p>No applications submitted. <a href="careers.php">View open positions</a>.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Position</th><th>Applied On</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                        <?php while($app = $my_applications->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($app['job_title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo statusClass($app['status']); ?>">
                                    <?php echo htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>