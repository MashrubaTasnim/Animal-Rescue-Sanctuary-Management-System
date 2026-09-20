<?php
session_start();
include 'db_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : "Guardian";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Hub | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/style.css?v=1.1">

    <style>
        /* ===== BASE ===== */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: #f0f2f7;
            font-family: 'Montserrat', sans-serif;
        }

        /* ===== HERO HEADER ===== */
        .hub-header {
            margin-top: -1px !important;
            position: relative;
            background: linear-gradient(135deg, rgba(8,14,32,0.92) 0%, rgba(10,19,41,0.85) 100%),
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0 90px;
            margin-bottom: -60px;
            overflow: hidden;
            border-bottom: 3px solid rgba(255,193,7,0.4);
        }

        .hub-header::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 380px; height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,193,7,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .hub-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 70px;
            background: linear-gradient(to bottom, transparent, #f0f2f7);
            pointer-events: none;
        }

        .hub-header .container { position: relative; z-index: 2; }

        .hub-header .hub-eyebrow {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,193,7,0.75);
            margin-bottom: 14px;
            display: block;
        }

        .hub-header h2 {
            font-family: 'Cinzel', serif !important;
            font-weight: 700 !important;
            font-size: clamp(1.9rem, 4vw, 3rem) !important;
            color: #ffffff !important;
            letter-spacing: 2px !important;
            margin: 0 0 16px !important;
            line-height: 1.2 !important;
        }

        .hub-header h2 span {
            color: #ffc107;
            font-style: italic;
        }

        .hub-header .lead {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: rgba(255,239,194,0.6);
            letter-spacing: 0.5px;
            max-width: 480px;
            margin: 0 auto;
        }

        .hub-header .hub-divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #ffc107, transparent);
            margin: 20px auto;
            border-radius: 2px;
        }

        /* ===== CARDS SECTION ===== */
        .cards-section {
            position: relative;
            z-index: 5;
            padding-bottom: 70px;
            margin-top: -50px;
        }

        /* ===== ACTION CARD ===== */
        .action-card {
            cursor: pointer;
            transition: transform 0.35s cubic-bezier(0.165, 0.84, 0.44, 1),
                        box-shadow 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
            height: 100%;
            border: 1px solid rgba(255,255,255,0.7) !important;
            border-radius: 20px !important;
            background: #ffffff;
            padding: 32px 20px 28px !important;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 20px 20px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .action-card:hover::before { opacity: 1; }
        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 24px 48px rgba(10,19,41,0.13) !important;
            border-color: rgba(255,193,7,0.3) !important;
        }

        /* Per-card accent colors */
        .card-animals::before   { background: linear-gradient(90deg, #ffc107, #ffdb70); }
        .card-rescue::before    { background: linear-gradient(90deg, #ef4444, #f87171); }
        .card-vet::before       { background: linear-gradient(90deg, #22c55e, #6ee7b7); }
        .card-donate::before    { background: linear-gradient(90deg, #3b82f6, #93c5fd); }
        .card-events::before    { background: linear-gradient(90deg, #10b981, #34d399); }
        .card-notices::before   { background: linear-gradient(90deg, #f59e0b, #fcd34d); }
        .card-vacancies::before { background: linear-gradient(90deg, #0a1329, #334155); }
        .card-guidelines::before{ background: linear-gradient(90deg, #ec4899, #f9a8d4); }

        /* ===== ICON CIRCLE ===== */
        .icon-circle {
            width: 66px;
            height: 66px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 1.4rem;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }

        .action-card:hover .icon-circle {
            transform: scale(1.12) rotate(-4deg);
        }

        .icon-animals    { background: rgba(255,193,7,0.15);  color: #d97706; }
        .icon-rescue     { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .icon-vet        { background: rgba(34,197,94,0.13);  color: #16a34a; }
        .icon-donate     { background: rgba(59,130,246,0.12); color: #2563eb; }
        .icon-events     { background: rgba(16,185,129,0.12); color: #059669; }
        .icon-notices    { background: rgba(245,158,11,0.13); color: #d97706; }
        .icon-vacancies  { background: rgba(10,19,41,0.09);   color: #0a1329; }
        .icon-guidelines { background: rgba(236,72,153,0.11); color: #db2777; }

        /* ===== CARD TEXT ===== */
        .action-card h6 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.85rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: #0a1329 !important;
            margin-bottom: 6px !important;
        }

        .action-card p {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.72rem !important;
            font-weight: 500 !important;
            color: #94a3b8 !important;
            margin: 0 !important;
            letter-spacing: 0.3px;
        }
.card-surrender::before { 
    background: linear-gradient(90deg, #f97316, #fdba74); 
}

.icon-surrender { 
    background: rgba(249,115,22,0.12); 
    color: #ea580c; 
}

.col:nth-child(9) { animation-delay: 0.45s; }
        /* ===== STAGGER ANIMATION ===== */
        .col { opacity: 0; transform: translateY(24px); animation: card-rise 0.5s ease forwards; }
        .col:nth-child(1) { animation-delay: 0.05s; }
        .col:nth-child(2) { animation-delay: 0.10s; }
        .col:nth-child(3) { animation-delay: 0.15s; }
        .col:nth-child(4) { animation-delay: 0.20s; }
        .col:nth-child(5) { animation-delay: 0.25s; }
        .col:nth-child(6) { animation-delay: 0.30s; }
        .col:nth-child(7) { animation-delay: 0.35s; }
        .col:nth-child(8) { animation-delay: 0.40s; }

        @keyframes card-rise {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== MODAL ===== */
        .modal-content {
            border: none !important;
            border-radius: 24px !important;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
        }

        .modal-header {
            background: linear-gradient(135deg, #c0392b, #e74c3c) !important;
            padding: 20px 24px !important;
            border-radius: 0 !important;
        }

        .modal-title {
            font-family: 'Montserrat', sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            letter-spacing: 1px !important;
            text-transform: uppercase !important;
        }

        .modal-body { padding: 28px 28px 24px !important; background: #fff; }

        .modal-body .form-label {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.8px !important;
            text-transform: uppercase !important;
            color: #475569 !important;
            margin-bottom: 6px !important;
        }

        .modal-body .form-control {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.82rem !important;
            font-weight: 500 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 10px 14px !important;
            color: #0a1329 !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
        }

        .modal-body .form-control:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important;
        }

        .modal-body .form-text {
            font-size: 0.68rem !important;
            color: #94a3b8 !important;
            font-style: italic;
        }

        .sos-btn {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            letter-spacing: 2px !important;
            text-transform: uppercase !important;
            background: linear-gradient(135deg, #c0392b, #e74c3c) !important;
            border: none !important;
            border-radius: 14px !important;
            padding: 14px !important;
            color: #fff !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
            box-shadow: 0 4px 16px rgba(220,38,38,0.3) !important;
        }

        .sos-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 24px rgba(220,38,38,0.4) !important;
        }

        /* ===== DROPDOWN FIX ===== */
        .dropdown-menu {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- ===== HERO HEADER ===== -->
<header class="hub-header text-center">
    <div class="container">
        <span class="hub-eyebrow">User Portal</span>
        <h2>Welcome, <span><?php echo htmlspecialchars($user_name); ?></span></h2>
        <div class="hub-divider"></div>
        <p class="lead">Your central station for animal welfare and rescue operations.</p>
    </div>
</header>

<!-- ===== ACTION CARDS ===== -->
<div class="container cards-section">
    <div class="row g-4 text-center row-cols-1 row-cols-md-3 row-cols-lg-4 justify-content-center">

        <div class="col">
            <div class="card action-card card-animals shadow-sm" id="btn-view-animals">
                <div class="icon-circle icon-animals"><i class="fas fa-paw"></i></div>
                <h6>View Animals</h6>
                <p>Find a furry friend</p>
            </div>
        </div>

        <div class="col">
            <div class="card action-card card-rescue shadow-sm" data-bs-toggle="modal" data-bs-target="#rescueModal">
                <div class="icon-circle icon-rescue"><i class="fas fa-ambulance"></i></div>
                <h6>Ask for Rescue</h6>
                <p>Emergency SOS</p>
            </div>
        </div>

        <div class="col">
    <div class="card action-card card-surrender shadow-sm" data-bs-toggle="modal" data-bs-target="#surrenderModal">
        <div class="icon-circle icon-surrender"><i class="fas fa-home"></i></div>
        <h6>Surrender a Pet</h6>
        <p>Can't keep them? We'll help</p>
    </div>
</div>

        <div class="col">
            <div class="card action-card card-vet shadow-sm" id="btn-vets">
                <div class="icon-circle icon-vet"><i class="fas fa-hospital-user"></i></div>
                <h6>Vet Clinics</h6>
                <p>Find medical help</p>
            </div>
        </div>

        <div class="col">
            <div class="card action-card card-donate shadow-sm" id="btn-donate">
                <div class="icon-circle icon-donate"><i class="fas fa-hand-holding-heart"></i></div>
                <h6>Donate</h6>
                <p>Support the mission</p>
            </div>
        </div>

        <div class="col">
            <div class="card action-card card-events shadow-sm" id="btn-events">
                <div class="icon-circle icon-events"><i class="fas fa-calendar-alt"></i></div>
                <h6>View Events</h6>
                <p>Rescue drives</p>
            </div>
        </div>

        <!-- ===== NOTICES CARD (new) ===== -->
        <div class="col">
            <div class="card action-card card-notices shadow-sm" id="btn-notices">
                <div class="icon-circle icon-notices"><i class="fas fa-bell"></i></div>
                <h6>Notices</h6>
                <p>Updates & announcements</p>
            </div>
        </div>

        <div class="col">
            <div class="card action-card card-vacancies shadow-sm" id="btn-vacancies">
                <div class="icon-circle icon-vacancies"><i class="fas fa-briefcase"></i></div>
                <h6>Vacancies</h6>
                <p>Work with us</p>
            </div>
        </div>

        <div class="col">
            <div class="card action-card card-guidelines shadow-sm" id="btn-guidelines">
                <div class="icon-circle icon-guidelines"><i class="fas fa-book-open"></i></div>
                <h6>Pet Guidelines</h6>
                <p>Parenting tips</p>
            </div>
        </div>

    </div>
</div>

<!-- ===== RESCUE MODAL ===== -->
<div class="modal fade" id="rescueModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white fw-bold py-3">
                <h5 class="modal-title"><i class="fas fa-bullhorn me-2"></i>Emergency Rescue SOS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="rescueSOS" method="POST" action="process_sos.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Animal Species</label>
                            <input type="text" name="species" class="form-control" placeholder="Cat, Dog, Bird etc." required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold text-danger">
                                <i class="fas fa-notes-medical me-1"></i> Situation Description
                            </label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Describe the animal's condition (e.g., bleeding, unconscious, unable to walk)"
                                required></textarea>
                            <div class="form-text small" style="font-size: 0.7rem;">This info helps AI prioritize critical cases.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Detailed Location</label>
                            <textarea name="location" class="form-control" rows="2" placeholder="Enter landmark or street address" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Upload Photo/Video <span class="text-danger">*</span></label>
                            <input type="file" name="rescue_media" class="form-control" accept="image/*,video/*">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold">Phone Number <span class="text-optional text-muted">(optional)</span></label>
                            <input type="tel" name="contact_phone" class="form-control" placeholder="+880 1XXXXXXXXX">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold">Email <span class="text-optional text-muted">(optional)</span></label>
                            <input type="email" name="contact_email" class="form-control" placeholder="you@example.com">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-3 fw-bold shadow-sm" style="border-radius: 12px; letter-spacing: 1px;">
                        <i class="fas fa-paper-plane me-2"></i>SEND EMERGENCY SOS
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     SURRENDER MODAL — paste this anywhere after your rescue modal
     in userhub.php (before the closing </body> tag)
     ============================================================ -->

<style>
/* ===== SURRENDER MODAL ===== */
#surrenderModal .modal-content {
    border: none !important;
    border-radius: 26px !important;
    overflow: hidden;
    font-family: 'Montserrat', sans-serif;
    box-shadow: 0 32px 64px rgba(0,0,0,0.18) !important;
}

#surrenderModal .modal-header {
    background: linear-gradient(135deg, #c2410c, #f97316) !important;
    padding: 22px 28px !important;
    border-radius: 0 !important;
    border: none !important;
}

#surrenderModal .modal-title {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 700 !important;
    font-size: 0.92rem !important;
    letter-spacing: 1.5px !important;
    text-transform: uppercase !important;
    color: #fff !important;
}

#surrenderModal .modal-body {
    padding: 0 !important;
    background: #fff;
}

/* ---- Step indicator ---- */
.sdr-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 28px 0;
    gap: 0;
}

.sdr-step-dot {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #94a3b8;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.35s ease;
    position: relative;
    z-index: 1;
}

.sdr-step-dot.active {
    background: linear-gradient(135deg, #c2410c, #f97316);
    color: #fff;
    box-shadow: 0 4px 12px rgba(249,115,22,0.4);
}

.sdr-step-dot.done {
    background: #dcfce7;
    color: #16a34a;
}

.sdr-step-line {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    max-width: 60px;
    transition: background 0.35s ease;
}

.sdr-step-line.done { background: #f97316; }

.sdr-step-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-align: center;
    color: #94a3b8;
    margin-top: 5px;
    text-transform: uppercase;
}

.sdr-step-label.active { color: #f97316; }

/* ---- Panels ---- */
.sdr-panel {
    display: none;
    padding: 24px 28px 28px;
    animation: sdr-fade-in 0.3s ease;
}

.sdr-panel.active { display: block; }

@keyframes sdr-fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ---- Checklist (Step 1) ---- */
.sdr-checklist-intro {
    background: linear-gradient(135deg, rgba(249,115,22,0.06), rgba(194,65,12,0.04));
    border: 1px solid rgba(249,115,22,0.15);
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 20px;
}

.sdr-checklist-intro p {
    font-size: 0.78rem !important;
    font-weight: 600 !important;
    color: #c2410c !important;
    margin: 0 !important;
    line-height: 1.6;
}

.sdr-check-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.25s ease;
    background: #fafafa;
}

.sdr-check-item:hover { border-color: #f97316; background: #fff7ed; }

.sdr-check-item.checked {
    border-color: #f97316;
    background: #fff7ed;
}

.sdr-check-item input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: #f97316;
    flex-shrink: 0;
    margin-top: 1px;
    cursor: pointer;
}

.sdr-check-item .sdr-check-text strong {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: #0a1329;
    margin-bottom: 2px;
}

.sdr-check-item .sdr-check-text span {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 500;
}

/* ---- Form (Step 2) ---- */
.sdr-form-label {
    font-family: 'Montserrat', sans-serif !important;
    font-size: 0.68rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.8px !important;
    text-transform: uppercase !important;
    color: #475569 !important;
    margin-bottom: 5px !important;
    display: block;
}

.sdr-form-control {
    font-family: 'Montserrat', sans-serif !important;
    font-size: 0.82rem !important;
    font-weight: 500 !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 10px 14px !important;
    color: #0a1329 !important;
    width: 100%;
    transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
    background: #fafafa;
    outline: none;
}

.sdr-form-control:focus {
    border-color: #f97316 !important;
    box-shadow: 0 0 0 3px rgba(249,115,22,0.1) !important;
    background: #fff;
}

.sdr-section-title {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #f97316;
    margin: 18px 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sdr-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(249,115,22,0.2);
}

/* urgency pills */
.sdr-urgency-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.sdr-urgency-pill {
    flex: 1;
    min-width: 80px;
    padding: 9px 12px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 0.7rem;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    transition: all 0.22s ease;
    color: #475569;
    background: #fafafa;
    user-select: none;
}

.sdr-urgency-pill:hover { border-color: #f97316; color: #f97316; }
.sdr-urgency-pill.selected {
    border-color: #f97316;
    background: #fff7ed;
    color: #c2410c;
    box-shadow: 0 2px 8px rgba(249,115,22,0.15);
}

/* ---- Buttons ---- */
.sdr-btn-next {
    font-family: 'Montserrat', sans-serif !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    letter-spacing: 1.5px !important;
    text-transform: uppercase !important;
    background: linear-gradient(135deg, #c2410c, #f97316) !important;
    border: none !important;
    border-radius: 14px !important;
    padding: 13px 28px !important;
    color: #fff !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    box-shadow: 0 4px 16px rgba(249,115,22,0.3) !important;
    cursor: pointer;
}

.sdr-btn-next:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(249,115,22,0.4) !important;
}

.sdr-btn-next:disabled {
    opacity: 0.45 !important;
    cursor: not-allowed !important;
    transform: none !important;
}

.sdr-btn-back {
    font-family: 'Montserrat', sans-serif !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    letter-spacing: 1px !important;
    background: transparent !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 14px !important;
    padding: 12px 22px !important;
    color: #64748b !important;
    transition: all 0.2s ease !important;
    cursor: pointer;
}

.sdr-btn-back:hover {
    border-color: #94a3b8 !important;
    color: #0a1329 !important;
}

/* ---- Confirmation (Step 3) ---- */
.sdr-confirm-icon {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(249,115,22,0.12), rgba(194,65,12,0.08));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #f97316;
    animation: sdr-pulse 2s infinite;
}

@keyframes sdr-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(249,115,22,0.3); }
    50%       { box-shadow: 0 0 0 12px rgba(249,115,22,0); }
}

.sdr-confirm-title {
    font-family: 'Cinzel', serif !important;
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: #0a1329 !important;
    margin-bottom: 8px !important;
}

.sdr-confirm-note {
    font-size: 0.78rem !important;
    color: #64748b !important;
    font-weight: 500 !important;
    line-height: 1.7 !important;
    max-width: 320px;
    margin: 0 auto !important;
}

.sdr-confirm-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff7ed;
    border: 1px solid rgba(249,115,22,0.25);
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.68rem;
    font-weight: 700;
    color: #c2410c;
    letter-spacing: 0.5px;
    margin-top: 14px;
}
</style>

<!-- ===== SURRENDER MODAL ===== -->
<div class="modal fade" id="surrenderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-home me-2"></i>Surrender / Rehome a Pet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">

                <!-- Step Indicator -->
                <div class="sdr-steps">
                    <div style="text-align:center;">
                        <div class="sdr-step-dot active" id="sdr-dot-1">1</div>
                        <div class="sdr-step-label active" id="sdr-lbl-1">Checklist</div>
                    </div>
                    <div class="sdr-step-line" id="sdr-line-1"></div>
                    <div style="text-align:center;">
                        <div class="sdr-step-dot" id="sdr-dot-2">2</div>
                        <div class="sdr-step-label" id="sdr-lbl-2">Pet Details</div>
                    </div>
                    <div class="sdr-step-line" id="sdr-line-2"></div>
                    <div style="text-align:center;">
                        <div class="sdr-step-dot" id="sdr-dot-3">3</div>
                        <div class="sdr-step-label" id="sdr-lbl-3">Submitted</div>
                    </div>
                </div>

                <!-- ===== STEP 1: CHECKLIST ===== -->
                <div class="sdr-panel active" id="sdr-step-1">
                    <div class="sdr-checklist-intro">
                        <p><i class="fas fa-heart me-2"></i>Before surrendering, we ask you to confirm you've explored every option. This helps us ensure every animal truly needs our care.</p>
                    </div>

                    <div class="sdr-check-item" onclick="toggleCheck(this)">
                        <input type="checkbox" class="sdr-checkbox">
                        <div class="sdr-check-text">
                            <strong>Tried rehoming with family or friends</strong>
                            <span>Have you asked people you trust if they can provide a loving home?</span>
                        </div>
                    </div>

                    <div class="sdr-check-item" onclick="toggleCheck(this)">
                        <input type="checkbox" class="sdr-checkbox">
                        <div class="sdr-check-text">
                            <strong>Consulted a vet about behavioral issues</strong>
                            <span>Many issues like aggression or anxiety are treatable with proper guidance.</span>
                        </div>
                    </div>

                    <div class="sdr-check-item" onclick="toggleCheck(this)">
                        <input type="checkbox" class="sdr-checkbox">
                        <div class="sdr-check-text">
                            <strong>Explored pet-friendly housing alternatives</strong>
                            <span>If housing is the concern, some landlords allow pets with a deposit.</span>
                        </div>
                    </div>

                    <div class="sdr-check-item" onclick="toggleCheck(this)">
                        <input type="checkbox" class="sdr-checkbox">
                        <div class="sdr-check-text">
                            <strong>I understand this is a permanent decision</strong>
                            <span>Once surrendered, ownership transfers to Praner Tan shelter.</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button class="sdr-btn-next" id="sdr-next-1" disabled onclick="goToStep(2)">
                            <i class="fas fa-arrow-right me-2"></i>Proceed to Form
                        </button>
                    </div>
                </div>

                <!-- ===== STEP 2: PET DETAILS FORM ===== -->
                <div class="sdr-panel" id="sdr-step-2">
                    <form id="surrenderForm" action="process_surrender.php" method="POST" enctype="multipart/form-data">

                        <!-- Pet Info -->
                        <div class="sdr-section-title"><i class="fas fa-paw"></i> Pet Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="sdr-form-label">Species <span class="text-danger">*</span></label>
                                <input type="text" name="species" class="sdr-form-control" placeholder="Cat, Dog, Bird..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="sdr-form-label">Breed</label>
                                <input type="text" name="breed" class="sdr-form-control" placeholder="Mixed, Labrador...">
                            </div>
                            <div class="col-md-4">
                                <label class="sdr-form-label">Age <span class="text-danger">*</span></label>
                                <input type="text" name="age" class="sdr-form-control" placeholder="2 years" required>
                            </div>
                            <div class="col-md-4">
                                <label class="sdr-form-label">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="sdr-form-control" required>
                                    <option value="" disabled selected>Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Unknown</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="sdr-form-label">Vaccinated?</label>
                                <select name="is_vaccinated" class="sdr-form-control">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                    <option value="">Not Sure</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="sdr-form-label">Spayed / Neutered?</label>
                                <select name="is_spayed" class="sdr-form-control">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                    <option value="">Not Sure</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="sdr-form-label">Photo <span class="text-danger">*</span></label>
                                <input type="file" name="pet_photo" class="sdr-form-control" accept="image/*" required>
                            </div>
                        </div>

                        <!-- Surrender Reason -->
                        <div class="sdr-section-title"><i class="fas fa-comment-alt"></i> Reason for Surrender</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="sdr-form-label">Primary Reason <span class="text-danger">*</span></label>
                                <select name="reason" class="sdr-form-control" required>
                                    <option value="" disabled selected>Select a reason</option>
                                    <option>Allergies in family</option>
                                    <option>Moving / Housing issue</option>
                                    <option>Financial difficulty</option>
                                    <option>Behavioral issues</option>
                                    <option>New baby / Family change</option>
                                    <option>Owner illness</option>
                                    <option>Too many pets</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="sdr-form-label">Additional Details</label>
                                <textarea name="description" class="sdr-form-control" rows="3" placeholder="Tell us more about the situation and the pet's personality..."></textarea>
                            </div>
                        </div>

                        <!-- Urgency -->
                        <div class="sdr-section-title"><i class="fas fa-clock"></i> How Soon?</div>
                        <div class="sdr-urgency-group" id="urgencyGroup">
                            <div class="sdr-urgency-pill" onclick="selectUrgency(this, 'urgent')">⚡ Within a Week</div>
                            <div class="sdr-urgency-pill" onclick="selectUrgency(this, 'soon')">📅 Within a Month</div>
                            <div class="sdr-urgency-pill" onclick="selectUrgency(this, 'flexible')">🕊️ No Rush</div>
                        </div>
                        <input type="hidden" name="urgency" id="urgencyInput" required>

                        <!-- Contact preference -->
                        <div class="sdr-section-title"><i class="fas fa-phone-alt"></i> Contact Preference</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="sdr-form-label">Preferred Contact Time</label>
                                <select name="contact_time" class="sdr-form-control">
                                    <option>Morning (9am – 12pm)</option>
                                    <option>Afternoon (12pm – 5pm)</option>
                                    <option>Evening (5pm – 9pm)</option>
                                    <option>Anytime</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="sdr-form-label">Phone Number</label>
                                <input type="text" name="phone" class="sdr-form-control" placeholder="Optional alternate number">
                            </div>
                        </div>
<!-- ── SURRENDER CONTRIBUTION ── -->
<div class="mb-3">
    <label class="form-label fw-bold">
        <i class="fas fa-hand-holding-heart me-1 text-warning"></i>
        Surrender Contribution (৳) <span style="font-weight:400;color:#94a3b8;font-size:0.8rem;">— optional</span>
    </label>
    <input type="number" name="surrender_contribution" class="form-control"
           min="0" step="0.01" placeholder="e.g. 500.00">
    <div class="form-text">A small contribution helps cover initial medical screening costs for your pet.</div>
</div>
<!-- ───────────────────────────── -->
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="sdr-btn-back" onclick="goToStep(1)">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            
                            <button type="submit" class="sdr-btn-next" id="sdr-submit-btn">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ===== STEP 3: CONFIRMATION ===== -->
                <div class="sdr-panel text-center" id="sdr-step-3">
                    <div class="sdr-confirm-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h5 class="sdr-confirm-title">Request Received</h5>
                    <p class="sdr-confirm-note">
                        Thank you for reaching out. Our shelter team will review your submission and contact you within <strong>24 hours</strong> to arrange a safe and comfortable drop-off for your pet.
                    </p>
                    <div class="sdr-confirm-badge">
                        <i class="fas fa-shield-alt"></i> Your pet will be cared for
                    </div>
                    <div class="mt-4">
                        <button class="sdr-btn-next" data-bs-dismiss="modal" style="letter-spacing:1px;">
                            Close
                        </button>
                    </div>
                </div>

            </div><!-- /modal-body -->
        </div>
    </div>
</div>

<script>
/* ===== SURRENDER MODAL JS ===== */

// 1. Checklist Logic
function toggleCheck(item) {
    // We look for the checkbox inside the clicked div
    const cb = item.querySelector('.sdr-checkbox');
    
    // IMPORTANT: If the user clicked the checkbox directly, 
    // the browser already changed its state. We only want to 
    // manually toggle it if they clicked the surrounding div/text.
    if (event.target !== cb) {
        cb.checked = !cb.checked;
    }
    
    // Toggle the visual 'checked' class on the wrapper div
    item.classList.toggle('checked', cb.checked);
    updateChecklistBtn();
}

function updateChecklistBtn() {
    // Count all checkboxes vs those that are checked
    const all = document.querySelectorAll('.sdr-checkbox').length;
    const done = document.querySelectorAll('.sdr-checkbox:checked').length;
    
    // Enable button only if ALL (4) are checked
    const nextBtn = document.getElementById('sdr-next-1');
    if (nextBtn) {
        nextBtn.disabled = (done < all);
    }
}

// 2. Urgency Selection
function selectUrgency(el, val) {
    // Remove 'selected' class from all pills in the group
    document.querySelectorAll('.sdr-urgency-pill').forEach(function(p) { 
        p.classList.remove('selected'); 
    });
    // Add to the one clicked
    el.classList.add('selected');
    // Set the hidden input value for the form
    document.getElementById('urgencyInput').value = val;
}

// 3. Step Navigation
function goToStep(step) {
    // Hide all panels, show the target one
    document.querySelectorAll('.sdr-panel').forEach(function(p) { 
        p.classList.remove('active'); 
    });
    const targetPanel = document.getElementById('sdr-step-' + step);
    if (targetPanel) targetPanel.classList.add('active');

    // Update Progress Dots/Labels
    for (let i = 1; i <= 3; i++) {
        const dot = document.getElementById('sdr-dot-' + i);
        const lbl = document.getElementById('sdr-lbl-' + i);
        if (!dot || !lbl) continue;

        dot.classList.remove('active', 'done');
        lbl.classList.remove('active');

        if (i < step) { 
            dot.classList.add('done'); 
            dot.innerHTML = '<i class="fas fa-check"></i>'; 
        } else if (i === step) { 
            dot.classList.add('active'); 
            dot.innerHTML = i; 
            lbl.classList.add('active'); 
        } else { 
            dot.innerHTML = i; 
        }
    }

    // Update Progress Lines
    for (let j = 1; j <= 2; j++) {
        const line = document.getElementById('sdr-line-' + j);
        if (line) line.classList.toggle('done', j < step);
    }
}

// 4. Form Submission
document.getElementById('surrenderForm').addEventListener('submit', function(e) {
    const urgency = document.getElementById('urgencyInput').value;
    if (!urgency) {
        e.preventDefault();
        alert('Please select how soon you need to surrender the pet.');
        return;
    }
    
    // Optional: If you want to show Step 3 without a page reload, 
    // you would use AJAX/Fetch here and then call goToStep(3).
    // Otherwise, the page will refresh to process_surrender.php.
});

// 5. Reset Modal on Close
const modalEl = document.getElementById('surrenderModal');
if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function() {
        goToStep(1);
        const form = document.getElementById('surrenderForm');
        if (form) form.reset();
        
        // Reset checklist visuals
        document.querySelectorAll('.sdr-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.sdr-check-item').classList.remove('checked');
        });
        
        // Reset urgency pills
        document.querySelectorAll('.sdr-urgency-pill').forEach(p => p.classList.remove('selected'));
        document.getElementById('urgencyInput').value = '';
        document.getElementById('sdr-next-1').disabled = true;
    });
}
</script>

<?php include 'footer.php'; ?>
<?php include 'chat_widget.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#btn-view-animals').click(function() { window.location.href = 'animals.php'; });
        $('#btn-events').click(function()       { window.location.href = 'events.php'; });
        $('#btn-donate').click(function()       { window.location.href = 'donate.php'; });
        $('#btn-vets').click(function()         { window.location.href = 'vets.php'; });
        $('#btn-notices').click(function()      { window.location.href = 'notices.php'; });
        $('#btn-vacancies').click(function()    { window.location.href = 'vacancies.php'; });
        $('#btn-guidelines').click(function()   { window.location.href = 'guidelines.php'; });
    });
</script>
</body>
</html>