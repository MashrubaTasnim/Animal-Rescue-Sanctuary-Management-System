<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'navbar.php'; 

$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : "Guardian";
$message = "";

// Check if already applied
$check = $conn->query("SELECT status FROM rescuer_applications WHERE user_id = $user_id");
$existing = $check->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $details = $conn->real_escape_string($_POST['experience']);
    $sql = "INSERT INTO rescuer_applications (user_id, experience_details) VALUES ($user_id, '$details')";
    
    if ($conn->query($sql)) {
        $message = "<div class='alert alert-success border-0 shadow-sm mb-4'>Application submitted successfully! Our team will review it.</div>";
        header("Refresh:2");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Squad | Moonlight of Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .hub-header {
            background: linear-gradient(rgba(10, 19, 41, 0.85), rgba(10, 19, 41, 0.85)), 
                        url('https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: -60px;
        }
        .form-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
            border: none;
            overflow: hidden;
        }
        .gold-accent { color: #B8860B; }
        .icon-box {
            width: 80px;
            height: 80px;
            background: #0a1329;
            color: #B8860B;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: -40px auto 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-apply {
            background: #0a1329;
            color: #B8860B;
            border: 2px solid #0a1329;
            font-weight: 700;
            padding: 15px;
            border-radius: 12px;
            transition: 0.3s;
        }
        .btn-apply:hover {
            background: #B8860B;
            color: #0a1329;
            border-color: #B8860B;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body style="background-color: #f6f8fb;">


<header class="hub-header text-center">
    <div class="container">
        <h2 class="display-5 fw-bold mb-2">Join the Rescue Squad</h2>
        <p class="lead opacity-75">Become a lifeline for the voiceless.</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card form-card p-4 p-md-5 mt-4">
                <div class="icon-box"><i class="fas fa-shield-dog"></i></div>
                
                <?php echo $message; ?>

                <?php if ($existing): ?>
                    <div class="text-center py-4">
                        <h4 class="fw-bold">Application Received</h4>
                        <div class="status-badge bg-warning text-dark mt-2 mb-4">
                            <i class="fas fa-clock me-1"></i> <?php echo $existing['status']; ?>
                        </div>
                        <p class="text-muted">Thank you, <?php echo htmlspecialchars($user_name); ?>. Our administrators are reviewing your application. You will be granted access to the Rescuer Dashboard once verified.</p>
                        <hr class="my-4 opacity-25">
                        <a href="user.php" class="btn btn-outline-secondary rounded-pill px-4">Return to Profile</a>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <h5 class="fw-bold">Volunteer Application</h5>
                        <p class="small text-muted">Tell us why you're a perfect fit for our field team.</p>
                    </div>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Experience & Motivation</label>
                            <textarea name="experience" class="form-control border-0 bg-light p-3" rows="6" 
                                placeholder="Describe your experience with animals, any medical knowledge, or why you want to help..." 
                                style="border-radius: 15px;" required></textarea>
                        </div>

                        <div class="p-3 rounded-3 mb-4" style="background: #fff8eb; border-left: 4px solid #B8860B;">
                            <p class="small mb-0 text-dark">
                                <i class="fas fa-info-circle me-2 gold-accent"></i>
                                Rescuers are expected to respond to SOS alerts and assist with on-ground animal transport.
                            </p>
                        </div>

                        <button type="submit" class="btn btn-apply w-100 shadow-sm">
                            SUBMIT APPLICATION <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<<?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>