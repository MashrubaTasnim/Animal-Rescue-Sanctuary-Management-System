<?php
session_start();
include 'db_config.php';

// Security: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// --- DYNAMIC DATA FETCHING ---
$rescue_count    = $conn->query("SELECT id FROM rescues WHERE status = 'Pending'")->num_rows;
$job_app_count   = $conn->query("SELECT id FROM job_applications WHERE status = 'Pending'")->num_rows;
$animal_count    = $conn->query("SELECT id FROM animals")->num_rows;
$clinic_count    = $conn->query("SELECT id FROM vet_clinics")->num_rows;
$user_count      = $conn->query("SELECT id FROM users WHERE role != 'admin'")->num_rows;
$event_count     = $conn->query("SELECT id FROM events")->num_rows;
$donation_count  = $conn->query("SELECT id FROM donations")->num_rows;
$pending_reviews = $conn->query("SELECT COUNT(*) as total FROM testimonials WHERE status = 'Pending'")->fetch_assoc()['total'];
$quote_count     = $conn->query("SELECT id FROM site_quotes")->num_rows;
$gallery_count   = $conn->query("SELECT id FROM gallery")->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin HQ | Heartbeat Heaven</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <style>
        /* ===== BASE ===== */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: #f0f2f7 !important;
            font-family: 'Montserrat', sans-serif;
        }

        /* ===== HERO HEADER ===== */
        .admin-hero {
            margin-top: -1px;
            background: linear-gradient(135deg, rgba(6,10,22,0.96) 0%, rgba(10,19,41,0.93) 100%),
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }

        .admin-hero::before {
            content: '';
            position: absolute;
            top: -100px; right: -100px;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,193,7,0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .admin-hero::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 60px;
            background: linear-gradient(to bottom, transparent, #f0f2f7);
            pointer-events: none;
        }

        .admin-hero .container { position: relative; z-index: 2; }

        .admin-hero .hero-eyebrow {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,193,7,0.7);
            display: block;
            margin-bottom: 12px;
        }

        .admin-hero h1 {
            font-family: 'Cinzel', serif !important;
            font-weight: 700 !important;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem) !important;
            color: #ffffff !important;
            letter-spacing: 3px !important;
            margin: 0 0 14px !important;
            line-height: 1.2 !important;
        }

        .admin-hero .hero-sub {
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255,239,194,0.5);
            letter-spacing: 0.5px;
        }

        .admin-hero .hero-divider {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, #ffc107, transparent);
            margin: 16px 0;
            border-radius: 2px;
        }

        /* ===== STAT PILLS (hero) ===== */
        .hero-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .hero-stat-pill {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,239,194,0.12);
            border-radius: 40px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255,239,194,0.75);
            letter-spacing: 0.5px;
        }

        .hero-stat-pill i {
            color: #ffc107;
            font-size: 0.75rem;
        }

        .hero-stat-pill strong {
            color: #ffc107;
            font-weight: 700;
        }

        /* ===== MAIN CONTENT ===== */
        .admin-content {
            position: relative;
            z-index: 5;
            padding: 50px 0 70px;
        }

        /* ===== SECTION LABEL ===== */
        .section-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-label-icon {
            width: 32px;
            height: 32px;
            background: #0a1329;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: #ffc107;
            flex-shrink: 0;
        }

        .section-label span {
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

        /* ===== OP CARD ===== */
        .op-card {
            background: #ffffff;
            border-radius: 18px !important;
            border: 1px solid rgba(255,255,255,0.9) !important;
            box-shadow: 0 2px 12px rgba(10,19,41,0.06) !important;
            padding: 20px 22px !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center;
            gap: 16px;
            transition: transform 0.3s cubic-bezier(0.165,0.84,0.44,1),
                        box-shadow 0.3s ease,
                        border-color 0.3s ease !important;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        /* accent bar on left */
        .op-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            border-radius: 18px 0 0 18px;
            background: #e2e8f0;
            transition: background 0.3s ease;
        }

        .op-card:hover {
            transform: translateY(-6px) !important;
            box-shadow: 0 16px 36px rgba(10,19,41,0.11) !important;
            border-color: rgba(255,193,7,0.25) !important;
        }

        .op-card:hover::before { background: #ffc107; }

        /* ===== ICON ===== */
        .op-icon {
            width: 48px;
            min-width: 48px;
            height: 48px;
            background: #080f22;
            color: #ffc107;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }

        .op-card:hover .op-icon {
            transform: scale(1.1) rotate(-5deg);
        }

        /* ===== CARD TEXT ===== */
        .op-info { flex: 1; min-width: 0; }

        .op-info h6 {
            margin: 0 0 4px !important;
            font-family: 'Montserrat', sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.85rem !important;
            color: #0a1329 !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .op-info span {
            font-size: 0.72rem !important;
            font-weight: 500 !important;
            color: #94a3b8 !important;
            display: block !important;
            margin-top: 0 !important;
        }

        .op-info span.text-alert {
            color: #ef4444 !important;
            font-weight: 700 !important;
        }

        /* ===== CMS CHIP TAGS ===== */
        .op-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 6px;
        }

        .op-chip {
            font-size: 0.6rem !important;
            font-weight: 600 !important;
            color: #64748b !important;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2px 8px;
            letter-spacing: 0.3px;
            display: inline-block !important;
            margin-top: 0 !important;
        }

        .op-chip.chip-alert {
            background: #fef2f2;
            border-color: #fecaca;
            color: #ef4444 !important;
        }

        /* ===== ALERT BADGE ===== */
        .op-badge {
            position: absolute;
            top: 12px; right: 12px;
            background: #ef4444;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            animation: badge-pulse 1.8s ease-in-out infinite;
        }

        @keyframes badge-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* ===== STAGGER ANIMATION ===== */
        .op-col {
            opacity: 0;
            transform: translateY(20px);
            animation: op-rise 0.45s ease forwards;
        }

        .op-col:nth-child(1)  { animation-delay: 0.05s; }
        .op-col:nth-child(2)  { animation-delay: 0.10s; }
        .op-col:nth-child(3)  { animation-delay: 0.15s; }
        .op-col:nth-child(4)  { animation-delay: 0.20s; }
        .op-col:nth-child(5)  { animation-delay: 0.25s; }
        .op-col:nth-child(6)  { animation-delay: 0.30s; }
        .op-col:nth-child(7)  { animation-delay: 0.35s; }
        .op-col:nth-child(8)  { animation-delay: 0.40s; }
        .op-col:nth-child(9)  { animation-delay: 0.45s; }

        @keyframes op-rise {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== SECTION SPACING ===== */
        .admin-section { margin-bottom: 48px; }

        /* ===== SOLO CARD (Settings) ===== */
        .op-card-solo {
            max-width: 360px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ===== HERO ===== -->
<div class="admin-hero">
    <div class="container">
        <span class="hero-eyebrow">Admin Panel</span>
        <h1>Command Dashboard</h1>
        <div class="hero-divider"></div>
        <p class="hero-sub">Full operational control over Heartbeat Heaven.</p>
        <div class="hero-stats">
            <div class="hero-stat-pill">
                <i class="fas fa-paw"></i> <strong><?= $animal_count ?></strong> Animals
            </div>
            <div class="hero-stat-pill">
                <i class="fas fa-users"></i> <strong><?= $user_count ?></strong> Members
            </div>
            <div class="hero-stat-pill">
                <i class="fas fa-calendar-alt"></i> <strong><?= $event_count ?></strong> Events
            </div>
            <div class="hero-stat-pill">
                <i class="fas fa-hand-holding-heart"></i> <strong><?= $donation_count ?></strong> Donations
            </div>
            <?php if($pending_reviews > 0): ?>
            <div class="hero-stat-pill" style="border-color: rgba(239,68,68,0.3); color: #fca5a5;">
                <i class="fas fa-star" style="color:#ef4444;"></i> <strong style="color:#ef4444;"><?= $pending_reviews ?></strong> Reviews Pending
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="container admin-content">

    <!-- RESCUE & HR -->
    <div class="admin-section">
        <div class="section-label">
            <div class="section-label-icon"><i class="fas fa-shield-alt"></i></div>
            <span>Rescue Operations &amp; HR</span>
        </div>
        <div class="row g-3">
            <div class="col-md-6 op-col">
                <a href="manage_rescues.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="op-info">
                        <h6>Mission Control (SOS to Vet)</h6>
                        <span class="<?= ($rescue_count > 0) ? 'text-alert' : '' ?>">
                            <?= $rescue_count ?> Rescues Awaiting Review
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 op-col">
                <a href="manage_vacancies.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="op-info">
                        <h6>Hiring &amp; Recruitment</h6>
                        <span class="<?= ($job_app_count > 0) ? 'text-alert' : '' ?>">
                            <?= $job_app_count ?> New Candidates to Review
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- ASSET & USER CONTROL -->
    <div class="admin-section">
        <div class="section-label">
            <div class="section-label-icon"><i class="fas fa-database"></i></div>
            <span>Asset &amp; User Control</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4 op-col">
                <a href="manage_animals.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-paw"></i></div>
                    <div class="op-info">
                        <h6>Animal &amp; Adoption Records</h6>
                        <span><?= $animal_count ?> Profiles Active</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4 op-col">
                <a href="manage_clinics.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-clinic-medical"></i></div>
                    <div class="op-info">
                        <h6>Vet Clinic Partners</h6>
                        <span><?= $clinic_count ?> Registered Clinics</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4 op-col">
                <a href="manage_users.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="op-info">
                        <h6>User Directory</h6>
                        <span><?= $user_count ?> Registered Members</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4 op-col">
                <a href="manage_events.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="op-info">
                        <h6>Event Control</h6>
                        <span><?= $event_count ?> Active Events</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4 op-col">
                <a href="manage_donations.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="op-info">
                        <h6>Donations</h6>
                        <span>Track ৳ currency flows &mdash; <?= $donation_count ?> Total</span>
                    </div>
                </a>
            </div>
            <div class="col-md-4 op-col">
                <a href="finance_dashboard.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="op-info">
                        <h6>Finance Dashboard</h6>
                        <span>Revenue, expenses &amp; financial overview</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- CMS -->
    <div class="admin-section">
        <div class="section-label">
            <div class="section-label-icon"><i class="fas fa-pen-nib"></i></div>
            <span>Website Content Control (CMS)</span>
        </div>
        <div class="row g-3">
            <div class="col-md-6 op-col">
                <a href="manage_contents.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="op-info">
                        <h6>Content Manager</h6>
                        <div class="op-chips">
                            <span class="op-chip"><i class="fas fa-quote-left fa-xs"></i> <?= $quote_count ?> Quotes</span>
                            <span class="op-chip"><i class="fas fa-images fa-xs"></i> <?= $gallery_count ?> Gallery</span>
                            <span class="op-chip <?= ($pending_reviews > 0) ? 'chip-alert' : '' ?>">
                                <i class="fas fa-star fa-xs"></i> <?= $pending_reviews ?> Review<?= $pending_reviews != 1 ? 's' : '' ?> Pending
                            </span>
                        </div>
                    </div>
                    <?php if($pending_reviews > 0): ?>
                        <span class="op-badge"><?= $pending_reviews ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- SYSTEM SETTINGS -->
    <div class="admin-section">
        <div class="section-label">
            <div class="section-label-icon"><i class="fas fa-sliders-h"></i></div>
            <span>System</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4 op-col">
                <a href="admin_settings.php" class="op-card">
                    <div class="op-icon"><i class="fas fa-cog"></i></div>
                    <div class="op-info">
                        <h6>Settings</h6>
                        <span>System Configuration</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>