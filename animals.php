<?php
session_start();
include 'db_config.php';

// ─────────────────────────────────────────────────────────────────────────────
// 1. SECURITY: whitelist filter input (Improvement #11)
// ─────────────────────────────────────────────────────────────────────────────
$allowed_filters = ['all', 'adoption', 'residents'];
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters))
    ? $_GET['filter']
    : 'all';
$user_id = (int)($_SESSION['user_id'] ?? 0);

// ─────────────────────────────────────────────────────────────────────────────
// 2. MAIN ANIMAL QUERY
// ─────────────────────────────────────────────────────────────────────────────
$where = "status IN ('Available for Adoption', 'Resident of Sanctuary')";
if ($filter === 'adoption')  $where = "status = 'Available for Adoption'";
if ($filter === 'residents') $where = "status = 'Resident of Sanctuary'";

$result = $conn->query("
    SELECT id, name, species, breed, age, gender, image_path, image_focus, status,
           is_vaccinated, is_spayed_neutered, medical_history,
           medical_clearance_status, vet_notes, created_at
    FROM animals
    WHERE $where
    ORDER BY created_at DESC
");

$animal_list = [];
$animal_ids  = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $animal_list[]               = $row;
        $animal_ids[]                = (int)$row['id'];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. N+1 FIX: Batch-fetch adoption requests & sponsorships (Improvement #1)
//    Use prepared statements throughout                       (Improvement #2)
// ─────────────────────────────────────────────────────────────────────────────
$adoption_statuses   = []; // [ animal_id => status ]
$sponsorship_statuses = []; // [ animal_id => status ]

if ($user_id && !empty($animal_ids)) {
    $placeholders = implode(',', array_fill(0, count($animal_ids), '?'));
    $types        = str_repeat('i', count($animal_ids));

    // --- Adoption requests ---
    $stmt = $conn->prepare("
        SELECT animal_id, status
        FROM adoption_requests
        WHERE user_id = ? AND animal_id IN ($placeholders)
        ORDER BY request_date DESC
    ");
    $params = array_merge([$user_id], $animal_ids);
    $stmt->bind_param('i' . $types, ...$params);
    $stmt->execute();
    $ar = $stmt->get_result();
    while ($r = $ar->fetch_assoc()) {
        // Keep only the most-recent row per animal (ORDER BY DESC already handled)
        if (!isset($adoption_statuses[$r['animal_id']])) {
            $adoption_statuses[$r['animal_id']] = $r['status'];
        }
    }
    $stmt->close();

    // --- Sponsorships ---
    $stmt = $conn->prepare("
        SELECT animal_id, status
        FROM resident_sponsorships
        WHERE user_id = ? AND animal_id IN ($placeholders)
          AND status IN ('Pending','Active')
        ORDER BY id DESC
    ");
    $stmt->bind_param('i' . $types, ...$params);
    $stmt->execute();
    $sr = $stmt->get_result();
    while ($r = $sr->fetch_assoc()) {
        if (!isset($sponsorship_statuses[$r['animal_id']])) {
            $sponsorship_statuses[$r['animal_id']] = $r['status'];
        }
    }
    $stmt->close();
}

// Attach statuses to each animal row
foreach ($animal_list as &$row) {
    $row['request_status'] = $adoption_statuses[$row['id']]    ?? null;
    $row['sponsor_status'] = $sponsorship_statuses[$row['id']] ?? null;
}
unset($row);

// ─────────────────────────────────────────────────────────────────────────────
// 4. Pre-fill user profile (prepared statement)
// ─────────────────────────────────────────────────────────────────────────────
$user_profile = [];
if ($user_id) {
    $stmt = $conn->prepare("SELECT full_name, phone, address FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $ur = $stmt->get_result();
    if ($ur && $ur->num_rows > 0) $user_profile = $ur->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Gallery | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Root Variables ──────────────────────────────────────────────────── */
        :root { --navy:#0a1329; --gold:#B8860B; }
        body  { font-family:'Inter',sans-serif; }

        /* ── Layout ──────────────────────────────────────────────────────────── */
        .gallery-container-wrapper { background:#f6f8fb; padding-bottom:50px; min-height:100vh; }

        /* ── Hero ────────────────────────────────────────────────────────────── */
        .hub-header {
            background: linear-gradient(rgba(10,19,41,0.85), rgba(10,19,41,0.85)),
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&q=80&w=2000');
            background-size:cover; background-position:center;
            color:white; padding:80px 0;
        }
        .hero-title { font-weight:800; letter-spacing:-1px; }
        .hero-note  { opacity:.8; font-size:1.1rem; }

        /* ── Sticky filter bar ───────────────────────────────────────────────── */
        /* Improvement #8: use top:70px to avoid navbar overlap on mobile */
        .filter-bar {
            background:#ffffff;
            border-bottom:1px solid #e9ecef;
            padding:18px 0;
            position:sticky;
            top:70px;          /* safe offset below navbar */
            z-index:100;
            box-shadow:0 2px 12px rgba(0,0,0,.06);
        }

        /* ── Cards ───────────────────────────────────────────────────────────── */
        /* Improvement #13: fade-up animation on cards */
        .animal-card {
            border:none; border-radius:20px; overflow:hidden;
            background:white; transition:.4s; height:100%;
            box-shadow:0 5px 15px rgba(0,0,0,.05);
            animation: fadeUp .5s ease both;
        }
        .animal-card:hover { transform:translateY(-8px); box-shadow:0 15px 35px rgba(0,0,0,.1); }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0);    }
        }

        /* stagger each card slightly */
        .searchable-item:nth-child(1)  .animal-card { animation-delay:.05s; }
        .searchable-item:nth-child(2)  .animal-card { animation-delay:.10s; }
        .searchable-item:nth-child(3)  .animal-card { animation-delay:.15s; }
        .searchable-item:nth-child(4)  .animal-card { animation-delay:.20s; }
        .searchable-item:nth-child(5)  .animal-card { animation-delay:.25s; }
        .searchable-item:nth-child(6)  .animal-card { animation-delay:.30s; }
        .searchable-item:nth-child(7)  .animal-card { animation-delay:.35s; }
        .searchable-item:nth-child(8)  .animal-card { animation-delay:.40s; }
        .searchable-item:nth-child(9)  .animal-card { animation-delay:.45s; }

        .animal-img-container { height:230px; overflow:hidden; position:relative; }
        .animal-img {
            width:100%; height:100%; object-fit:cover;
            cursor:zoom-in; transition:transform .3s;
        }
        .animal-img:hover { transform:scale(1.03); }

        .status-pill {
            position:absolute; top:15px; right:15px;
            padding:4px 12px; border-radius:50px;
            font-size:.65rem; font-weight:700;
            text-transform:uppercase; z-index:2;
        }
        .bg-healthy         { background:#dcfce7; color:#166534; }
        .bg-warning-custom  { background:#fff3cd; color:#856404; }

        /* ── Buttons ─────────────────────────────────────────────────────────── */
        .btn-gold { background:var(--gold); color:white; border:none; font-weight:700; border-radius:50px; padding:8px 20px; cursor:pointer; transition:.3s; }
        .btn-gold:hover { background:var(--navy); color:var(--gold); }

        .btn-sponsor {
            background:linear-gradient(135deg,#ffc107,#f59e0b);
            color:var(--navy); border:none; font-weight:800;
            border-radius:50px; padding:8px 18px; cursor:pointer;
            transition:.3s; font-size:0.78rem;
            display:inline-flex; align-items:center; gap:6px;
        }
        .btn-sponsor:hover { background:var(--navy); color:#ffc107; }

        /* ── Sponsor status badges ───────────────────────────────────────────── */
        .sponsor-badge-pending { background:#fff3cd; color:#856404; font-size:.68rem; font-weight:800; padding:4px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:5px; }
        .sponsor-badge-active  { background:#dcfce7; color:#166534; font-size:.68rem; font-weight:800; padding:4px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:5px; }

        /* ── Info grid ───────────────────────────────────────────────────────── */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:15px 0; }
        .info-item  { font-size:.75rem; color:#555; display:flex; align-items:center; }
        .info-item i { color:var(--gold); width:18px; margin-right:5px; }

        /* ── Request badges ──────────────────────────────────────────────────── */
        .req-badge      { font-size:.68rem; font-weight:800; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; display:inline-block; }
        .req-pending    { background:#e0f2fe; color:#0369a1; }
        .req-approved   { background:#dcfce7; color:#166534; }
        .req-rejected   { background:#fee2e2; color:#991b1b; }
        .req-scheduled  { background:#eff6ff; color:#1d4ed8; }

        /* ── Empty state ─────────────────────────────────────────────────────── */
        /* Improvement #3 */
        .empty-state { background:white; border-radius:20px; padding:60px 30px; text-align:center; box-shadow:0 5px 15px rgba(0,0,0,.05); }
        .empty-state i { color:#d1d5db; }

        /* ── No-result inline message ────────────────────────────────────────── */
        /* Improvement #9 */
        #noResult { display:none; }
        #noResult.show { display:block; }

        /* ── Lightbox ────────────────────────────────────────────────────────── */
        .lightbox-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:99999; justify-content:center; align-items:center; cursor:zoom-out; }
        .lightbox-overlay.active { display:flex; }
        .lightbox-overlay img { max-width:90vw; max-height:90vh; object-fit:contain; border-radius:12px; }
        .lightbox-close { position:absolute; top:20px; right:30px; color:white; font-size:2rem; cursor:pointer; }

        /* ── ADOPTION MODAL ──────────────────────────────────────────────────── */
        .adopt-modal-content { border-radius:24px !important; border:none !important; overflow:hidden; }
        .adopt-modal-header  { background:var(--navy); padding:20px 28px; }
        .adopt-modal-title   { color:var(--gold); font-weight:800; font-size:1rem; letter-spacing:.5px; }

        .form-section { margin-bottom:24px; }
        .form-section-title { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--gold); border-bottom:2px solid #f1f5f9; padding-bottom:8px; margin-bottom:16px; }

        .adopt-input { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.85rem; font-family:inherit; background:#fff; transition:border-color .2s; }
        .adopt-input:focus { outline:none; border-color:var(--gold); }
        .adopt-label { font-size:0.72rem; font-weight:700; color:#475569; display:block; margin-bottom:5px; }

        .toggle-group { display:flex; gap:8px; }
        .toggle-btn { flex:1; padding:9px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.78rem; font-weight:700; text-align:center; cursor:pointer; transition:all .2s; background:#f8fafc; color:#475569; user-select:none; }
        .toggle-btn.selected-yes { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
        .toggle-btn.selected-no  { border-color:#dc2626; background:#fef2f2; color:#dc2626; }

        .option-card { border:1.5px solid #e2e8f0; border-radius:12px; padding:12px 14px; cursor:pointer; transition:all .2s; font-size:0.8rem; font-weight:600; color:#475569; text-align:center; }
        .option-card:hover { border-color:var(--gold); }
        .option-card.selected { border-color:var(--gold); background:#fffbeb; color:var(--navy); }
        .option-card i { display:block; font-size:1.3rem; margin-bottom:5px; color:var(--gold); }

        .terms-box { background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0; font-size:0.78rem; color:#475569; max-height:160px; overflow-y:auto; }
        .terms-box ul { padding-left:18px; margin:0; }
        .terms-box li { margin-bottom:8px; }

        .adopt-submit-btn { background:var(--gold); color:#fff; border:none; border-radius:12px; padding:14px; font-size:0.85rem; font-weight:800; width:100%; cursor:pointer; transition:all .2s; letter-spacing:.5px; }
        .adopt-submit-btn:hover    { background:var(--navy); }
        .adopt-submit-btn:disabled { background:#cbd5e1; cursor:not-allowed; }

        .conditional-field { display:none; }
        .conditional-field.show { display:block; }

        .pet-preview-img { width:80px; height:80px; object-fit:cover; border-radius:50%; border:3px solid var(--gold); }

        .custom-scrollbar::-webkit-scrollbar       { width:5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background:var(--gold); border-radius:10px; }

        /* ── SPONSOR MODAL ───────────────────────────────────────────────────── */
        .sponsor-modal-content { border-radius:24px !important; border:none !important; overflow:hidden; }
        .sponsor-modal-header  { background:linear-gradient(135deg,#0a1329,#1e3a5f); padding:20px 28px; }
        .sponsor-modal-title   { color:#ffc107; font-weight:800; font-size:1rem; letter-spacing:.5px; }

        .tier-row  { display:flex; gap:10px; margin-bottom:6px; flex-wrap:wrap; }
        .tier-pill { flex:1; min-width:80px; padding:14px 10px; border:2px solid #e2e8f0; border-radius:14px; text-align:center; cursor:pointer; transition:all .2s; background:#fff; }
        .tier-pill:hover    { border-color:#ffc107; background:#fffbeb; }
        .tier-pill.selected { border-color:#ffc107; background:#fffbeb; box-shadow:0 0 0 3px rgba(255,193,7,.15); }
        .tier-pill .tier-amount { font-size:1.1rem; font-weight:800; color:var(--navy); display:block; }
        .tier-pill .tier-label  { font-size:0.65rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; display:block; }
        .tier-pill.selected .tier-amount { color:#b45309; }
        .tier-pill.selected .tier-label  { color:#d97706; }

        .tier-divider { display:flex; align-items:center; gap:10px; margin:14px 0; }
        .tier-divider span { font-size:0.72rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; white-space:nowrap; }
        .tier-divider::before,.tier-divider::after { content:''; flex:1; height:1px; background:#e2e8f0; }

        .sponsor-submit-btn { background:linear-gradient(135deg,#ffc107,#f59e0b); color:var(--navy); border:none; border-radius:12px; padding:14px; font-size:0.85rem; font-weight:800; width:100%; cursor:pointer; transition:all .2s; letter-spacing:.5px; }
        .sponsor-submit-btn:hover    { background:var(--navy); color:#ffc107; }
        .sponsor-submit-btn:disabled { background:#cbd5e1; color:#fff; cursor:not-allowed; }

        .sponsor-info-box { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:14px 16px; font-size:0.78rem; color:#92400e; margin-bottom:16px; }
        .sponsor-info-box i { color:#f59e0b; margin-right:6px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- ── Flash messages ───────────────────────────────────────────────────────── -->
<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show m-3">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="gallery-container-wrapper">

    <!-- ── Hero ─────────────────────────────────────────────────────────────── -->
    <header class="hub-header text-center">
        <div class="container">
            <h2 class="display-4 hero-title">Animal Gallery</h2>
            <p class="hero-note">Connecting compassionate hearts with lives that matter.</p>
        </div>
    </header>

    <!-- ── Filter + Search bar ───────────────────────────────────────────────── -->
    <!-- Improvement #8: top:70px instead of top:0 -->
    <div class="filter-bar">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

                <!-- Toggle pills -->
                <div style="background:#f1f3f5;padding:5px;border-radius:50px;display:inline-flex;gap:3px;">
                    <?php foreach (['all' => 'All', 'adoption' => 'Ready for Home', 'residents' => 'Sanctuary'] as $key => $label): ?>
                    <a href="?filter=<?= $key ?>"
                       style="border-radius:50px;padding:7px 20px;font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s;
                              <?= $filter === $key ? 'background:var(--navy);color:#fff;' : 'background:transparent;color:#6c757d;' ?>">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Improvement #4: search also checks breed, gender, status -->
                <div style="position:relative;flex:1;max-width:360px;min-width:200px;">
                    <i class="fas fa-search" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--gold);font-size:.85rem;pointer-events:none;"></i>
                    <input type="text" id="animalSearch" class="form-control"
                           style="border-radius:50px;padding-left:42px;border:1.5px solid #e2e8f0;font-size:.85rem;height:42px;"
                           placeholder="Search name, species, breed…"
                           aria-label="Search animals">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gallery ───────────────────────────────────────────────────────────── -->
    <div class="container py-5">

        <?php if (empty($animal_list)): ?>
        <!-- Improvement #3: empty state UI -->
        <div class="empty-state">
            <i class="fas fa-paw fa-3x mb-3"></i>
            <h4 class="fw-bold">No Animals Found</h4>
            <p class="text-muted">There are no animals in this category right now. Please check back soon!</p>
            <a href="?filter=all" class="btn btn-gold mt-2">View All Animals</a>
        </div>

        <?php else: ?>
        <div class="row g-4" id="animalGallery">
        <?php foreach ($animal_list as $row):
            $aid            = $row['id'];
            $status         = $row['status'];
            $req_status     = $row['request_status'];
            $sponsor_status = $row['sponsor_status'];
            $is_resident    = ($status === 'Resident of Sanctuary');
            $badge          = $is_resident ? 'bg-warning-custom' : 'bg-healthy';
            $text           = $is_resident ? 'Resident'          : 'Available';
        ?>
        <!-- Improvement #4: data-breed, data-gender, data-status added for JS search -->
        <div class="col-md-4 searchable-item"
             data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
             data-species="<?= strtolower(htmlspecialchars($row['species'])) ?>"
             data-breed="<?= strtolower(htmlspecialchars($row['breed'] ?? '')) ?>"
             data-gender="<?= strtolower(htmlspecialchars($row['gender'])) ?>"
             data-status="<?= strtolower(htmlspecialchars($status)) ?>">

            <div class="animal-card">
                <div class="animal-img-container">
                    <span class="status-pill <?= $badge ?>"><?= $text ?></span>

                    <!-- Improvement #5: loading="lazy" for performance -->
                    <!-- Improvement #6: onerror fallback image -->
                    <img src="<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png') ?>"
                         class="animal-img"
                         loading="lazy"
                         alt="Photo of <?= htmlspecialchars($row['name']) ?>"
                         style="object-position: <?= htmlspecialchars($row['image_focus'] ?: 'center') ?>;"
                         onerror="this.src='assets/img/placeholder.png';this.onerror=null;"
                         onclick="openLightbox(this.src)">
                </div>

                <div class="p-4">
                    <div class="d-flex align-items-start justify-content-between mb-1">
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($row['name']) ?></h4>
                        <!-- Improvement #10: medical clearance badge -->
                        <?php if ($row['medical_clearance_status'] === 'Cleared'): ?>
                            <span class="badge bg-success ms-2" style="font-size:.62rem;white-space:nowrap;">
                                <i class="fas fa-check-circle me-1"></i>Vet Cleared
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark ms-2" style="font-size:.62rem;white-space:nowrap;">
                                <i class="fas fa-notes-medical me-1"></i>Under Treatment
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($row['species']) ?> • <?= htmlspecialchars($row['breed']) ?></p>

                    <div class="info-grid">
                        <div class="info-item"><i class="fas fa-venus-mars"></i><?= htmlspecialchars($row['gender']) ?></div>
                        <div class="info-item"><i class="fas fa-birthday-cake"></i><?= htmlspecialchars($row['age']) ?></div>
                        <div class="info-item"><i class="fas fa-syringe"></i>Vac: <?= $row['is_vaccinated'] ? 'Yes' : 'No' ?></div>
                        <div class="info-item"><i class="fas fa-shield-alt"></i>Spayed: <?= $row['is_spayed_neutered'] ? 'Yes' : 'No' ?></div>
                    </div>

                    <?php if (!empty($row['vet_notes']) && $row['vet_notes'] !== 'Ready for forever home.'): ?>
                    <div class="mt-2 p-2 bg-light rounded" style="font-size:11px;border-left:3px solid var(--gold);">
                        <strong>Vet Notes:</strong> <?= htmlspecialchars($row['vet_notes']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex align-items-center pt-3 border-top mt-3 justify-content-between">
                        <span class="small fw-bold text-uppercase"><?= $text ?></span>

                        <?php if ($is_resident): ?>
                            <!-- Improvement #7: aria-label on sponsor button -->
                            <?php if ($sponsor_status === 'Active'): ?>
                                <span class="sponsor-badge-active"><i class="fas fa-star"></i>You're Sponsoring</span>
                            <?php elseif ($sponsor_status === 'Pending'): ?>
                                <span class="sponsor-badge-pending"><i class="fas fa-clock"></i>Sponsorship Pending</span>
                            <?php elseif ($user_id): ?>
                                <button type="button" class="btn-sponsor"
                                        aria-label="Sponsor <?= htmlspecialchars($row['name']) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#sponsorModal<?= $aid ?>">
                                    <i class="fas fa-star"></i> Sponsor
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-sm btn-outline-warning rounded-pill" style="font-size:.75rem;">
                                    <i class="fas fa-lock me-1"></i>Login to Sponsor
                                </a>
                            <?php endif; ?>

                        <?php else: /* Adoption */ ?>
                            <?php if ($req_status === 'approved'): ?>
                                <div class="text-end">
                                    <span class="req-badge req-approved"><i class="fas fa-check-circle me-1"></i>Approved</span>
                                    <div class="text-muted mt-1" style="font-size:10px;">Appointment will be scheduled!</div>
                                </div>
                            <?php elseif ($req_status === 'appointment_scheduled'): ?>
                                <div class="text-end">
                                    <span class="req-badge req-scheduled"><i class="fas fa-calendar-check me-1"></i>Appointment Set</span>
                                    <div class="text-muted mt-1" style="font-size:10px;">Check your email for details.</div>
                                </div>
                            <?php elseif ($req_status === 'rejected'): ?>
                                <div class="text-end">
                                    <span class="req-badge req-rejected"><i class="fas fa-times-circle me-1"></i>Not Selected</span>
                                    <?php if ($status === 'Available for Adoption'): ?>
                                        <button type="button" class="btn btn-sm btn-gold d-block mt-1" style="font-size:.68rem;"
                                                aria-label="Apply again to adopt <?= htmlspecialchars($row['name']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#adoptModal<?= $aid ?>">
                                            Apply Again
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($req_status === 'pending'): ?>
                                <div class="text-end">
                                    <span class="req-badge req-pending"><i class="fas fa-clock me-1"></i>Under Review</span>
                                    <form method="POST" action="process_adoption.php" class="mt-1"
                                          onsubmit="return confirm('Cancel your application for <?= htmlspecialchars($row['name'], ENT_QUOTES) ?>?')">
                                        <input type="hidden" name="submit_cancel"    value="1">
                                        <input type="hidden" name="cancel_animal_id" value="<?= $aid ?>">
                                        <button type="submit" class="btn btn-link p-0 text-danger fw-bold"
                                                style="font-size:10px;"
                                                aria-label="Cancel adoption request for <?= htmlspecialchars($row['name']) ?>">
                                            CANCEL REQUEST
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($user_id): ?>
                                <button type="button" class="btn btn-sm btn-gold"
                                        aria-label="Adopt <?= htmlspecialchars($row['name']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#adoptModal<?= $aid ?>">
                                    Adopt Me
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-sm btn-outline-warning rounded-pill" style="font-size:.75rem;">
                                    <i class="fas fa-lock me-1"></i>Login to Adopt
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div><!-- /.p-4 -->
            </div><!-- /.animal-card -->
        </div><!-- /.col searchable-item -->
        <?php endforeach; ?>
        </div><!-- /#animalGallery -->

        <!-- Improvement #9: no-result message shown by JS -->
        <div id="noResult" class="text-center py-5">
            <i class="fas fa-search fa-2x text-muted mb-3"></i>
            <h5 class="fw-bold">No Animals Match Your Search</h5>
            <p class="text-muted">Try a different name, species, breed, or gender.</p>
        </div>

        <?php endif; /* end empty check */ ?>
    </div><!-- /.container -->
</div><!-- /.gallery-container-wrapper -->

<!-- ════════════════════════════════════════════════════════════════════════════
     SPONSOR MODALS  (Resident animals, no active/pending sponsorship, user logged in)
═══════════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($animal_list as $row):
    if ($row['status'] !== 'Resident of Sanctuary') continue;
    if ($row['sponsor_status'] !== null) continue;
    if (!$user_id) continue;
    $aid = $row['id'];
?>
<div class="modal fade" id="sponsorModal<?= $aid ?>" tabindex="-1"
     aria-label="Sponsor <?= htmlspecialchars($row['name']) ?>"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content sponsor-modal-content">
            <div class="modal-header sponsor-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png') ?>"
                         class="pet-preview-img"
                         loading="lazy"
                         onerror="this.src='assets/img/placeholder.png';this.onerror=null;"
                         alt="<?= htmlspecialchars($row['name']) ?>">
                    <div>
                        <div style="font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;">Monthly Sponsorship</div>
                        <h5 class="sponsor-modal-title mb-0"><?= htmlspecialchars($row['name']) ?></h5>
                        <div style="font-size:.72rem;color:rgba(255,255,255,.6);">
                            <?= htmlspecialchars($row['species']) ?><?= $row['breed'] ? ' · '.htmlspecialchars($row['breed']) : '' ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto"
                        aria-label="Close sponsorship modal"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form action="sponsor_payment.php" method="POST" id="sponsorForm<?= $aid ?>">
                    <input type="hidden" name="animal_id"   value="<?= $aid ?>">
                    <input type="hidden" name="animal_name" value="<?= htmlspecialchars($row['name']) ?>">
                    <input type="hidden" name="amount"      id="sponsorAmount<?= $aid ?>" value="">

                    <div class="sponsor-info-box">
                        <i class="fas fa-info-circle"></i>
                        Your monthly contribution directly funds <strong><?= htmlspecialchars($row['name']) ?>'s</strong>
                        food, medical care, and shelter. You will be billed monthly via SSLCommerz.
                    </div>

                    <label class="adopt-label mb-2">Choose a Monthly Amount *</label>
                    <div class="tier-row" id="tierRow<?= $aid ?>">
                        <div class="tier-pill" onclick="selectTier(this, <?= $aid ?>, 500)" role="button" tabindex="0" aria-label="Sponsor for 500 taka per month, Basic Care tier">
                            <span class="tier-amount">৳500</span>
                            <span class="tier-label">Basic Care</span>
                        </div>
                        <div class="tier-pill" onclick="selectTier(this, <?= $aid ?>, 1000)" role="button" tabindex="0" aria-label="Sponsor for 1000 taka per month, Full Support tier">
                            <span class="tier-amount">৳1,000</span>
                            <span class="tier-label">Full Support</span>
                        </div>
                        <div class="tier-pill" onclick="selectTier(this, <?= $aid ?>, 2000)" role="button" tabindex="0" aria-label="Sponsor for 2000 taka per month, Champion tier">
                            <span class="tier-amount">৳2,000</span>
                            <span class="tier-label">Champion</span>
                        </div>
                    </div>

                    <div class="tier-divider"><span>Or enter custom amount</span></div>
                    <div class="mb-4">
                        <input type="number" id="customAmount<?= $aid ?>"
                               class="adopt-input" min="100" step="50"
                               placeholder="e.g. ৳1500 (minimum ৳100)"
                               aria-label="Custom monthly sponsorship amount"
                               oninput="onCustomAmount(this, <?= $aid ?>)">
                        <div style="font-size:.7rem;color:#94a3b8;margin-top:5px;">Minimum ৳100 per month.</div>
                    </div>

                    <div id="amountPreview<?= $aid ?>" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;font-weight:700;color:#166534;text-align:center;">
                        <i class="fas fa-check-circle me-2"></i>Monthly contribution:
                        <span id="previewVal<?= $aid ?>"></span>
                    </div>

                    <button type="submit" class="sponsor-submit-btn"
                            id="sponsorSubmit<?= $aid ?>"
                            disabled
                            aria-label="Confirm sponsorship for <?= htmlspecialchars($row['name']) ?>">
                        <i class="fas fa-heart me-2"></i>Sponsor <?= htmlspecialchars($row['name']) ?> — Pay via SSLCommerz
                    </button>
                    <div style="text-align:center;font-size:.7rem;color:#94a3b8;margin-top:10px;">
                        <i class="fas fa-lock me-1"></i>Secured by SSLCommerz &nbsp;·&nbsp; Cancel anytime
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ════════════════════════════════════════════════════════════════════════════
     ADOPTION MODALS  (Available animals, no existing/pending request)
═══════════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($animal_list as $row):
    $req_status = $row['request_status'];
    $can_apply  = ($row['status'] === 'Available for Adoption')
               && ($req_status === null || $req_status === 'rejected');
    if (!$can_apply) continue;
    $aid = $row['id'];
?>
<div class="modal fade" id="adoptModal<?= $aid ?>" tabindex="-1"
     aria-label="Adoption application for <?= htmlspecialchars($row['name']) ?>"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content adopt-modal-content">

            <div class="modal-header adopt-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png') ?>"
                         class="pet-preview-img"
                         loading="lazy"
                         onerror="this.src='assets/img/placeholder.png';this.onerror=null;"
                         alt="<?= htmlspecialchars($row['name']) ?>">
                    <div>
                        <div style="font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;">Adoption Application</div>
                        <h5 class="adopt-modal-title mb-0"><?= htmlspecialchars($row['name']) ?></h5>
                        <div style="font-size:.72rem;color:rgba(255,255,255,.6);">
                            <?= htmlspecialchars($row['species']) ?>
                            <?= $row['breed'] ? ' · '.htmlspecialchars($row['breed']) : '' ?>
                            · <?= htmlspecialchars($row['gender']) ?>
                            · <?= htmlspecialchars($row['age']) ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto"
                        aria-label="Close adoption modal"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form action="process_adoption.php" method="POST" id="adoptForm<?= $aid ?>">
                    <input type="hidden" name="animal_id" value="<?= $aid ?>">

                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-user me-2"></i>Personal Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="adopt-label" for="fullName<?= $aid ?>">Full Name *</label>
                                <input type="text" id="fullName<?= $aid ?>" name="full_name" class="adopt-input" required
                                       value="<?= htmlspecialchars($user_profile['full_name'] ?? '') ?>"
                                       placeholder="Your full name">
                            </div>
                            <div class="col-md-6">
                                <label class="adopt-label" for="phone<?= $aid ?>">Phone Number *</label>
                                <input type="tel" id="phone<?= $aid ?>" name="phone" class="adopt-input" required
                                       value="<?= htmlspecialchars($user_profile['phone'] ?? '') ?>"
                                       placeholder="e.g. 017XXXXXXXX">
                            </div>
                            <div class="col-12">
                                <label class="adopt-label" for="address<?= $aid ?>">Home Address *</label>
                                <textarea id="address<?= $aid ?>" name="address" class="adopt-input" rows="2" required
                                          placeholder="Your current home address"><?= htmlspecialchars($user_profile['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Living Situation -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-home me-2"></i>Living Situation</div>
                        <label class="adopt-label mb-2">Type of Housing *</label>
                        <div class="row g-2 mb-3" id="housingGroup<?= $aid ?>">
                            <div class="col-6 col-md-3"><div class="option-card" onclick="selectOption(this,'housing<?= $aid ?>','apartment')"><i class="fas fa-building"></i>Apartment</div></div>
                            <div class="col-6 col-md-3"><div class="option-card" onclick="selectOption(this,'housing<?= $aid ?>','house')"><i class="fas fa-house"></i>House</div></div>
                            <div class="col-6 col-md-3"><div class="option-card" onclick="selectOption(this,'housing<?= $aid ?>','rented_apartment')"><i class="fas fa-key"></i>Rented Apt</div></div>
                            <div class="col-6 col-md-3"><div class="option-card" onclick="selectOption(this,'housing<?= $aid ?>','rented_house')"><i class="fas fa-door-open"></i>Rented House</div></div>
                        </div>
                        <input type="hidden" name="housing_type" id="housing<?= $aid ?>" required>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="adopt-label">Do you have outdoor space? *</label>
                                <div class="toggle-group">
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'outdoor<?= $aid ?>',1)"><i class="fas fa-check me-1"></i>Yes</div>
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'outdoor<?= $aid ?>',0)"><i class="fas fa-times me-1"></i>No</div>
                                </div>
                                <input type="hidden" name="has_outdoor" id="outdoor<?= $aid ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="adopt-label" for="aloneHours<?= $aid ?>">Avg hours pet will be alone daily *</label>
                                <input type="number" id="aloneHours<?= $aid ?>" name="alone_hours"
                                       class="adopt-input" min="0" max="24" required placeholder="e.g. 4">
                            </div>
                        </div>
                    </div>

                    <!-- Household -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-people-roof me-2"></i>Household</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="adopt-label">Do you have other pets? *</label>
                                <div class="toggle-group">
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'otherpets<?= $aid ?>',1,'petsDetail<?= $aid ?>')"><i class="fas fa-paw me-1"></i>Yes</div>
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'otherpets<?= $aid ?>',0,'petsDetail<?= $aid ?>')"><i class="fas fa-times me-1"></i>No</div>
                                </div>
                                <input type="hidden" name="has_other_pets" id="otherpets<?= $aid ?>">
                                <div id="petsDetail<?= $aid ?>" class="conditional-field mt-2">
                                    <input type="text" name="other_pets_detail" class="adopt-input" placeholder="e.g. 1 dog, 2 cats">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="adopt-label">Do you have children at home? *</label>
                                <div class="toggle-group">
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'children<?= $aid ?>',1,'childrenAges<?= $aid ?>')"><i class="fas fa-child me-1"></i>Yes</div>
                                    <div class="toggle-btn" onclick="toggleYesNo(this,'children<?= $aid ?>',0,'childrenAges<?= $aid ?>')"><i class="fas fa-times me-1"></i>No</div>
                                </div>
                                <input type="hidden" name="has_children" id="children<?= $aid ?>">
                                <div id="childrenAges<?= $aid ?>" class="conditional-field mt-2">
                                    <input type="text" name="children_ages" class="adopt-input" placeholder="e.g. 3 years, 7 years">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Experience & Intent -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-heart me-2"></i>Experience &amp; Intent</div>
                        <label class="adopt-label mb-2">Pet Ownership Experience *</label>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><div class="option-card" onclick="selectOption(this,'experience<?= $aid ?>','none')"><i class="fas fa-seedling"></i>First Timer</div></div>
                            <div class="col-4"><div class="option-card" onclick="selectOption(this,'experience<?= $aid ?>','some')"><i class="fas fa-star-half-stroke"></i>Some Experience</div></div>
                            <div class="col-4"><div class="option-card" onclick="selectOption(this,'experience<?= $aid ?>','experienced')"><i class="fas fa-star"></i>Experienced</div></div>
                        </div>
                        <input type="hidden" name="pet_experience" id="experience<?= $aid ?>" required>
                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="adopt-label" for="adoptReason<?= $aid ?>">Why do you want to adopt <?= htmlspecialchars($row['name']) ?>? *</label>
                                <textarea id="adoptReason<?= $aid ?>" name="adoption_reason"
                                          class="adopt-input" rows="3" required
                                          placeholder="Tell us why you'd be a great match…"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="adopt-label" for="caretaker<?= $aid ?>">Who will be the primary caretaker? *</label>
                                <input type="text" id="caretaker<?= $aid ?>" name="primary_caretaker"
                                       class="adopt-input" required placeholder="e.g. Myself, My spouse, Family">
                            </div>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-file-contract me-2"></i>Adoption Standards</div>
                        <div class="terms-box custom-scrollbar mb-3">
                            <ul>
                                <li><strong>No-Kill Policy:</strong> You agree never to euthanize the animal except in cases of terminal illness under veterinary guidance.</li>
                                <li><strong>Indoor-Only Policy:</strong> The animal must be kept safely indoors at all times.</li>
                                <li><strong>Return Policy:</strong> If you can no longer care for the pet, it <strong>MUST</strong> be returned to Praner Tan — not given away or abandoned.</li>
                                <li><strong>Veterinary Care:</strong> You commit to providing regular veterinary checkups and necessary medical care.</li>
                                <li><strong>Right to Visit:</strong> Praner Tan reserves the right to conduct welfare checks within the first 6 months.</li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-start gap-3" style="background:#fffbeb;border-radius:12px;padding:14px;border:1px solid #fde68a;">
                            <input type="checkbox" name="agreed_terms" value="1"
                                   id="terms<?= $aid ?>"
                                   style="width:18px;height:18px;margin-top:2px;accent-color:var(--gold);"
                                   onchange="document.getElementById('submitBtn<?= $aid ?>').disabled = !this.checked"
                                   required>
                            <label for="terms<?= $aid ?>" style="font-size:.8rem;font-weight:700;color:var(--navy);cursor:pointer;">
                                I have read and agree to all Praner Tan Adoption Standards. I understand this is a lifetime commitment.
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="submit_adoption"
                            id="submitBtn<?= $aid ?>" class="adopt-submit-btn"
                            disabled
                            aria-label="Submit adoption application for <?= htmlspecialchars($row['name']) ?>">
                        <i class="fas fa-paw me-2"></i>SUBMIT ADOPTION APPLICATION
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ── Lightbox ──────────────────────────────────────────────────────────────── -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()" role="dialog" aria-label="Image preview">
    <span class="lightbox-close" aria-label="Close lightbox">&times;</span>
    <img id="lightboxImg" src="" alt="Full size animal photo">
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Lightbox ──────────────────────────────────────────────────────────────────
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

// ── Search (Improvement #4 + #9) ─────────────────────────────────────────────
// Searches: name, species, breed, gender, status
document.getElementById('animalSearch').addEventListener('input', function () {
    const val     = this.value.toLowerCase().trim();
    const items   = document.querySelectorAll('.searchable-item');
    let   visible = 0;

    items.forEach(item => {
        const match = !val
            || item.dataset.name.includes(val)
            || item.dataset.species.includes(val)
            || (item.dataset.breed  || '').includes(val)
            || (item.dataset.gender || '').includes(val)
            || (item.dataset.status || '').includes(val);

        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    // Improvement #9: toggle no-result message
    const noResult = document.getElementById('noResult');
    if (noResult) noResult.classList.toggle('show', visible === 0 && val !== '');
});

// ── Option cards (housing, experience) ────────────────────────────────────────
function selectOption(el, hiddenId, val) {
    el.closest('.row').querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(hiddenId).value = val;
}

// ── Yes/No toggles ────────────────────────────────────────────────────────────
function toggleYesNo(el, hiddenId, val, conditionalId = null) {
    el.closest('.toggle-group').querySelectorAll('.toggle-btn')
      .forEach(b => b.classList.remove('selected-yes', 'selected-no'));
    el.classList.add(val == 1 ? 'selected-yes' : 'selected-no');
    document.getElementById(hiddenId).value = val;
    if (conditionalId) {
        const cf = document.getElementById(conditionalId);
        if (val == 1) {
            cf.classList.add('show');
        } else {
            cf.classList.remove('show');
            const inp = cf.querySelector('input,textarea');
            if (inp) inp.value = '';
        }
    }
}

// ── Sponsor tier selection ────────────────────────────────────────────────────
function selectTier(el, animalId, amount) {
    document.querySelectorAll('#tierRow' + animalId + ' .tier-pill')
            .forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');

    const custom = document.getElementById('customAmount' + animalId);
    if (custom) custom.value = '';

    document.getElementById('sponsorAmount' + animalId).value = amount;
    showAmountPreview(animalId, amount);
}

function onCustomAmount(input, animalId) {
    document.querySelectorAll('#tierRow' + animalId + ' .tier-pill')
            .forEach(p => p.classList.remove('selected'));

    const val = parseFloat(input.value);
    if (val >= 100) {
        document.getElementById('sponsorAmount' + animalId).value = val;
        showAmountPreview(animalId, val);
    } else {
        document.getElementById('sponsorAmount' + animalId).value = '';
        document.getElementById('amountPreview' + animalId).style.display = 'none';
        document.getElementById('sponsorSubmit'  + animalId).disabled = true;
    }
}

function showAmountPreview(animalId, amount) {
    const preview = document.getElementById('amountPreview' + animalId);
    document.getElementById('previewVal' + animalId).textContent =
        '৳' + Number(amount).toLocaleString('en-BD');
    preview.style.display = 'block';
    document.getElementById('sponsorSubmit' + animalId).disabled = false;
}

// ── Keyboard accessibility for tier pills ────────────────────────────────────
document.querySelectorAll('.tier-pill').forEach(pill => {
    pill.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            pill.click();
        }
    });
});
</script>
</body>
</html>