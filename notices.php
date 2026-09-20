<?php
session_start();
include 'db_config.php';
require_once 'auth_helper.php';
require_login('Please login to view notices and updates.');
// rest of your page...

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'navbar.php';

// Fetch active notices, newest first
$notices = [];
$result = $conn->query("SELECT * FROM notices WHERE is_active = 1 ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $notices[] = $row;
}

// Helper: map type to colors/icons
function noticeStyle($type) {
    return match($type) {
        'warning' => ['icon' => 'fa-triangle-exclamation', 'bg' => '#fffbeb', 'border' => '#f59e0b', 'badge_bg' => '#fef3c7', 'badge_text' => '#92400e', 'label' => 'Warning'],
        'danger'  => ['icon' => 'fa-circle-exclamation',  'bg' => '#fff5f5', 'border' => '#f87171', 'badge_bg' => '#fee2e2', 'badge_text' => '#991b1b', 'label' => 'Urgent'],
        'success' => ['icon' => 'fa-circle-check',        'bg' => '#f0fdf4', 'border' => '#4ade80', 'badge_bg' => '#dcfce7', 'badge_text' => '#166534', 'label' => 'Good News'],
        default   => ['icon' => 'fa-circle-info',         'bg' => '#eff6ff', 'border' => '#60a5fa', 'badge_bg' => '#dbeafe', 'badge_text' => '#1e40af', 'label' => 'Notice'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy: #0a1329;
            --gold: #B8860B;
        }

        body { background-color: #f6f8fb; }

        /* ── Header ─────────────────────────────────────────── */
        .notices-header {
            background: linear-gradient(rgba(10,19,41,0.82), rgba(10,19,41,0.82)),
                        url('https://images.unsplash.com/photo-1518717758536-85ae29035b6d?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: -50px;
        }

        .gold-label {
            font-size: 0.65rem; letter-spacing: 2px; font-weight: 800;
            color: var(--gold); text-transform: uppercase; display: block;
            margin-bottom: 6px;
        }

        /* ── Filter bar ─────────────────────────────────────── */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 16px 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            border: 1.5px solid #e2e8f0;
            background: white;
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--navy);
            border-color: var(--navy);
            color: white;
        }
        .filter-btn.active-info    { background: #1e40af; border-color: #1e40af; color: white; }
        .filter-btn.active-warning { background: #92400e; border-color: #92400e; color: white; }
        .filter-btn.active-danger  { background: #991b1b; border-color: #991b1b; color: white; }
        .filter-btn.active-success { background: #166534; border-color: #166534; color: white; }

        /* ── Notice card ────────────────────────────────────── */
        .notice-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transition: transform 0.3s cubic-bezier(0.165, 0.84, 0.44, 1), box-shadow 0.3s;
            margin-bottom: 18px;
            display: flex;
        }
        .notice-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(10,19,41,0.1);
        }

        .notice-accent {
            width: 6px;
            flex-shrink: 0;
            border-radius: 0;
        }

        .notice-inner {
            padding: 22px 24px;
            flex: 1;
            display: flex;
            align-items: flex-start;
            gap: 18px;
        }

        .notice-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notice-content { flex: 1; min-width: 0; }

        .notice-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .notice-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notice-date {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .notice-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .notice-body {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.65;
            margin: 0;
        }

        /* ── Empty state ────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
        }
        .empty-icon-wrap {
            width: 80px; height: 80px; border-radius: 24px;
            background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #cbd5e1;
            margin: 0 auto 20px;
        }

        /* ── Count pill ─────────────────────────────────────── */
        .count-pill {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            margin-top: 12px;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="notices-header text-center">
    <div class="container">
        <span class="gold-label">From the Team</span>
        <h2 class="display-5 fw-bold mb-2">Notices & Announcements</h2>
        <p class="lead opacity-75 mb-0">Stay up to date with important updates from Heartbeat Heaven.</p>
        <?php if (!empty($notices)): ?>
            <span class="count-pill">
                <i class="fas fa-bell me-1"></i>
                <?= count($notices) ?> active notice<?= count($notices) !== 1 ? 's' : '' ?>
            </span>
        <?php endif; ?>
    </div>
</header>

<div class="container pb-5" style="position:relative; z-index:5;">

    <?php if (!empty($notices)): ?>

    <!-- Filter bar -->
    <div class="filter-bar">
        <span style="font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-right:4px;">Filter:</span>
        <button class="filter-btn active" onclick="filterNotices('all', this)">All</button>
        <button class="filter-btn" onclick="filterNotices('info', this)">Info</button>
        <button class="filter-btn" onclick="filterNotices('warning', this)">Warning</button>
        <button class="filter-btn" onclick="filterNotices('danger', this)">Urgent</button>
        <button class="filter-btn" onclick="filterNotices('success', this)">Good News</button>
    </div>

    <!-- Notice list -->
    <div id="notices-list">
        <?php foreach ($notices as $n):
            $s = noticeStyle($n['type']);
        ?>
        <div class="notice-card" data-type="<?= htmlspecialchars($n['type']) ?>">
            <div class="notice-accent" style="background: <?= $s['border'] ?>;"></div>
            <div class="notice-inner">
                <div class="notice-icon-wrap" style="background: <?= $s['badge_bg'] ?>; color: <?= $s['badge_text'] ?>;">
                    <i class="fas <?= $s['icon'] ?>"></i>
                </div>
                <div class="notice-content">
                    <div class="notice-meta">
                        <span class="notice-badge" style="background:<?= $s['badge_bg'] ?>; color:<?= $s['badge_text'] ?>;">
                            <i class="fas <?= $s['icon'] ?>" style="font-size:0.6rem;"></i>
                            <?= $s['label'] ?>
                        </span>
                        <span class="notice-date">
                            <i class="fas fa-clock me-1" style="font-size:0.6rem;"></i>
                            <?= date('d M Y', strtotime($n['created_at'])) ?>
                        </span>
                    </div>
                    <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
                    <?php if (!empty($n['body'])): ?>
                        <p class="notice-body"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="no-results" class="empty-state d-none">
        <div class="empty-icon-wrap"><i class="fas fa-filter"></i></div>
        <h6 class="fw-bold text-muted">No notices in this category</h6>
        <p class="text-muted small mb-0">Try selecting a different filter above.</p>
    </div>

    <?php else: ?>

    <!-- Empty state -->
    <div class="empty-state mt-4">
        <div class="empty-icon-wrap"><i class="fas fa-bell-slash"></i></div>
        <h5 class="fw-bold" style="color:#1e293b;">No notices right now</h5>
        <p class="text-muted mb-0" style="font-size:0.9rem;">Check back later — we'll post important updates here.</p>
    </div>

    <?php endif; ?>

</div>

<script>
function filterNotices(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('active', 'active-info', 'active-warning', 'active-danger', 'active-success');
    });
    if (type === 'all') {
        btn.classList.add('active');
    } else {
        btn.classList.add('active-' + type);
    }

    const cards = document.querySelectorAll('.notice-card');
    let visible = 0;
    cards.forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
            card.style.display = 'flex';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    const noResults = document.getElementById('no-results');
    if (noResults) noResults.classList.toggle('d-none', visible > 0);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>