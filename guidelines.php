<?php
session_start();
include 'db_config.php';

include 'navbar.php'; 

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : "Guardian";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidelines | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .guideline-card { 
            cursor: pointer; 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
            height: 100%; 
            border: none !important; 
            border-radius: 20px;
            overflow: hidden;
            background: white;
        }
        .guideline-card:hover { 
            transform: translateY(-12px); 
            box-shadow: 0 20px 40px rgba(10, 19, 41, 0.15) !important; 
        }
        .icon-circle { 
            width: 70px; 
            height: 70px; 
            border-radius: 18px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 20px; 
            font-size: 1.5rem;
        }
        .hub-header {
            /* High-quality cute photo of a person hugging a dog */
            background: linear-gradient(rgba(10, 19, 41, 0.6), rgba(10, 19, 41, 0.6)), 
                        url('https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            margin-bottom: -50px;
        }
        .hero-title { font-family: 'Montserrat', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
        .hero-note { font-family: 'Montserrat', serif; font-style: normal; opacity: 0.8; font-size: 1.1rem; }        
        .modal-content { border-radius: 30px; border: none; overflow: hidden; }
        .modal-header { padding: 30px; border: none; }
        .modal-body { padding: 40px; }

        .info-box {
            background: #f8f9fa;
            border-left: 5px solid #B8860B;
            padding: 20px;
            border-radius: 0 15px 15px 0;
            margin-bottom: 25px;
        }
        .info-box h6 { color: #B8860B; font-weight: 700; margin-bottom: 10px; text-transform: uppercase; font-size: 0.85rem; }
        .info-box p { font-size: 0.9rem; line-height: 1.6; color: #555; margin-bottom: 0; }

        .step-tag {
            background: #B8860B;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 10px;
        }

        .check-list { list-style: none; padding-left: 0; }
        .check-list li { position: relative; padding-left: 30px; margin-bottom: 15px; font-size: 0.9rem; color: #444; }
        .check-list li i { position: absolute; left: 0; top: 3px; color: #28a745; }
    </style>
</head>
<body style="background-color: #f6f8fb;">

<header class="hub-header text-center">
    <div class="container">
        <h2 class="display-4 hero-title">Kindness Knowledge Base</h2>
        <p class="lead hero-note">Practical insights for animal lovers and guardians.</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row g-4 text-center justify-content-center">
        <div class="col-md-3">
            <div class="card guideline-card p-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#pettingModal" style="background: #fff0f6;">
                <div class="icon-circle bg-danger text-white shadow-sm"><i class="fas fa-hand-holding-heart"></i></div>
                <h6 class="fw-bold">Interaction</h6>
                <p class="small text-muted mb-0">The art of bonding.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card guideline-card p-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#rescueModal" style="background: #f0f4ff;">
                <div class="icon-circle bg-primary text-white shadow-sm"><i class="fas fa-ambulance"></i></div>
                <h6 class="fw-bold">Rescue Protocol</h6>
                <p class="small text-muted mb-0">Emergency handling.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card guideline-card p-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#medicalCareModal" style="background: #fffef0;">
                <div class="icon-circle bg-warning text-dark shadow-sm"><i class="fas fa-notes-medical"></i></div>
                <h6 class="fw-bold">Medical Care</h6>
                <p class="small text-muted mb-0">Special needs support.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card guideline-card p-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#adoption333Modal" style="background: #ebfffa;">
                <div class="icon-circle bg-success text-white shadow-sm"><i class="fas fa-calendar-check"></i></div>
                <h6 class="fw-bold">3-3-3 Rule</h6>
                <p class="small text-muted mb-0">Adoption success.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pettingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="fw-bold mb-0"><i class="fas fa-heart me-2"></i>Petting & Interaction Etiquette</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <h6>The Consent Test</h6>
                    <p>Never assume an animal wants touch. Extend your hand as a closed fist for them to sniff. If they approach or rub against you, proceed. If they lean back or look away, respect their boundary.</p>
                </div>
                <ul class="check-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Crouch Down:</strong> Approaching from above is intimidating. Getting on their level builds immediate trust.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Safe Zones:</strong> Focus on the chin, chest, or shoulders. Avoid the paws, tail, and belly until a strong bond is formed.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Pause Principle:</strong> Pet for a few seconds, then stop. If they nudge your hand, they want more. This prevents overstimulation.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rescueModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="fw-bold mb-0"><i class="fas fa-ambulance me-2"></i>Emergency Rescue Steps</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <h6>Shock Management</h6>
                    <p>Injured animals often enter a state of shock. Keep them warm and in a dark environment to lower their heart rate and anxiety levels.</p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold small">1. Immediate Safety</h6>
                        <p class="extra-small text-muted mb-3">Cover the animal with a blanket. This limits their vision and prevents bites during the panic of rescue.</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold small">2. Secure Transport</h6>
                        <p class="extra-small text-muted mb-3">Use a cardboard box with air holes or a pet carrier. Line the bottom with soft towels to minimize movement trauma.</p>
                    </div>
                </div>
                <div class="bg-light p-3 rounded-3 border-start border-4 border-danger">
                    <p class="small mb-0 text-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>WARNING: Never force-feed water to an unconscious animal; it can lead to fatal aspiration.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="medicalCareModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="fw-bold mb-0"><i class="fas fa-notes-medical me-2"></i>Special Health Care</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <h6>Paralyzed Animal Support</h6>
                    <p>Animals with hind-leg paralysis need their bladders expressed manually 3-4 times daily to prevent UTI and kidney failure. Consult a vet for the correct technique.</p>
                </div>
                <h6 class="fw-bold mb-3">Hygiene & Comfort Checklist:</h6>
                <ul class="check-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Bedding:</strong> Use orthopedic memory foam to prevent "bedsores" (pressure necrosis).</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Physiotherapy:</strong> Perform gentle 'bicycle legs' movements to maintain joint range and blood circulation.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Skin Care:</strong> Use unscented baby wipes to clean 'bathroom accidents' immediately to avoid skin scald.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adoption333Modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2"></i>The International 3-3-3 Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="timeline-step">
                    <span class="step-tag">Phase 1: First 3 Days</span>
                    <h6>Decompression & Safety</h6>
                    <p class="small text-muted">The pet feels overwhelmed. They may hide, refuse food, or have accidents. Focus on keeping them calm. Don't force interaction; let them come to you.</p>
                </div>
                <div class="timeline-step">
                    <span class="step-tag">Phase 2: First 3 Weeks</span>
                    <h6>Routine & Personality</h6>
                    <p class="small text-muted">They start feeling comfortable. Their true personality (and behavior challenges) will emerge. Start basic positive-reinforcement training and set firm boundaries.</p>
                </div>
                <div class="timeline-step" style="margin-bottom:0;">
                    <span class="step-tag">Phase 3: First 3 Months</span>
                    <h6>Trust & Belonging</h6>
                    <p class="small text-muted">The permanent bond is formed. They finally feel "Home." They trust you and understand the household routine completely. Success achieved!</p>
                </div>
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