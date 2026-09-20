<?php
session_start();
include 'db_config.php';

// REMOVED: hard redirect for guests
include 'navbar.php'; 

$user_id = $_SESSION['user_id'] ?? 0; // Guest-safe default

// Query adjusted: is_interested = 0 for guests
$query = "SELECT e.*, 
          " . ($user_id ? "(SELECT COUNT(*) FROM event_interests ei WHERE ei.event_id = e.id AND ei.user_id = $user_id)" : "0") . " as is_interested,
          (SELECT COUNT(*) FROM event_interests ei WHERE ei.event_id = e.id) as interest_count
          FROM events e 
          WHERE e.event_date >= CURDATE() 
          ORDER BY e.event_date ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .event-card { 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
            border: none !important; 
            border-radius: 20px;
            background: white;
            overflow: hidden;
        }
        .event-card:hover { 
            transform: translateY(-12px); 
            box-shadow: 0 20px 40px rgba(10, 19, 41, 0.15) !important; 
        }
        .hub-header {
            background: linear-gradient(rgba(10, 19, 41, 0.8), rgba(10, 19, 41, 0.8)), 
                        url('https://images.unsplash.com/photo-1548191265-cc70d3d45ba1?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: -50px;
        }
        .event-image-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        .event-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .date-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 15px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            color: #0a1329;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-action {
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        .btn-registered {
            background-color: #198754 !important;
            color: white !important;
            border: none !important;
        }
        .interest-count-badge {
            font-size: 0.75rem;
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 10px;
            border-radius: 50px;
            font-weight: 700;
        }
    </style>
</head>
<body style="background-color: #f6f8fb;">

<header class="hub-header text-center">
    <div class="container">
        <h2 class="display-4 fw-bold mb-3">Community Events</h2>
        <p class="lead opacity-75">Join our vaccination drives, rescue seminars, and animal advocacy meetups.</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row g-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                $already_interested = ($row['is_interested'] > 0);
            ?>
                <div class="col-md-4">
                    <div class="card event-card shadow-sm h-100">
                        <div class="event-image-container">
                            <div class="date-badge">
                                <div class="small text-uppercase" style="font-size: 0.7rem;"><?php echo date('M', strtotime($row['event_date'])); ?></div>
                                <div class="fs-5"><?php echo date('d', strtotime($row['event_date'])); ?></div>
                            </div>
                            <?php if($row['image_path']): ?>
                                <img src="<?php echo $row['image_path']; ?>" alt="Event">
                            <?php else: ?>
                                <div class="h-100 w-100 bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-paw fa-3x text-muted opacity-25"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body p-4 d-flex flex-column text-start">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold m-0"><?php echo htmlspecialchars($row['title']); ?></h5>
                                <span class="interest-count-badge" id="count-<?php echo $row['id']; ?>">
                                    <i class="fas fa-users me-1"></i><?php echo $row['interest_count']; ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center text-muted small mb-3">
                                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                <?php echo htmlspecialchars($row['location']); ?>
                            </div>
                            
                            <p class="small text-muted mb-4">
                                <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                            </p>
                            
                            <!-- ✅ GUEST ACCESS LOGIC: mirrors animals.php pattern -->
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php 
                                    $btnClass = $already_interested ? "btn-registered" : "btn-outline-dark";
                                    $btnText  = $already_interested ? "REGISTERED"     : "I'M INTERESTED";
                                    $btnIcon  = $already_interested ? "fa-heart"        : "fa-check";
                                ?>
                                <button id="btn-<?php echo $row['id']; ?>" 
                                        class="btn btn-action w-100 mt-auto <?php echo $btnClass; ?>" 
                                        onclick="showInterest(<?php echo $row['id']; ?>)">
                                    <i class="fas <?php echo $btnIcon; ?> me-2"></i>
                                    <span><?php echo $btnText; ?></span>
                                </button>
                            <?php else: ?>
                                <!-- Guest sees a login prompt, just like "Login to Adopt" in animals.php -->
                                <a href="login.php" class="btn btn-action btn-outline-warning w-100 mt-auto" style="font-size: 0.85rem;">
                                    <i class="fas fa-lock me-2"></i> Login to Register
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// ✅ Extra JS guard: even if button is somehow clicked by a guest, redirect to login
const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

function showInterest(eventId) {
    if (!isLoggedIn) {
        window.location.href = 'login.php';
        return;
    }

    const btn = document.getElementById('btn-' + eventId);
    const btnSpan = btn.querySelector('span');
    const btnIcon = btn.querySelector('i');
    const countBadge = document.getElementById('count-' + eventId);

    const formData = new FormData();
    formData.append('event_id', eventId);

    fetch('process_interest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "registered") {
            btn.classList.remove('btn-outline-dark');
            btn.classList.add('btn-registered');
            btnSpan.innerText = "REGISTERED";
            btnIcon.className = "fas fa-heart me-2";
        } else {
            btn.classList.remove('btn-registered');
            btn.classList.add('btn-outline-dark');
            btnSpan.innerText = "I'M INTERESTED";
            btnIcon.className = "fas fa-check me-2";
        }
        
        if (countBadge) {
            countBadge.innerHTML = `<i class="fas fa-users me-1"></i>${data.new_count}`;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>