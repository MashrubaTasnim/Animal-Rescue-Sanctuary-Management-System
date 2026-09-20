<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Logic: Remove Participant
if (isset($_GET['remove_user']) && isset($_GET['event_id'])) {
    $u_id = intval($_GET['remove_user']);
    $e_id = intval($_GET['event_id']);
    $conn->query("DELETE FROM event_interests WHERE event_id = $e_id AND user_id = $u_id");
    header("Location: event_participants.php?id=$e_id&msg=removed");
    exit();
}

// Fetch event details for the header
$event_res = $conn->query("SELECT title, location, event_date FROM events WHERE id = $event_id");
$event = $event_res->fetch_assoc();

// Fetch participants using your exact column names: full_name, email, phone
$participants = $conn->query("
    SELECT u.id, u.full_name, u.email, u.phone, u.profile_image, ei.created_at as joined_at 
    FROM event_interests ei 
    JOIN users u ON ei.user_id = u.id 
    WHERE ei.event_id = $event_id 
    ORDER BY ei.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Participants | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        
        /* Matching your manage_events table style */
        .table thead th { 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: #6c757d; 
            background: #f8f9fa; 
            padding: 20px; 
            border: none; 
        }
        .table tbody td { padding: 20px; border-bottom: 1px solid #f1f1f1; }
        
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
        .back-link { color: #B8860B; text-decoration: none; font-weight: 700; font-size: 0.85rem; }
        .back-link:hover { color: white; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <a href="manage_events.php" class="back-link mb-3 d-inline-block">
            <i class="fas fa-arrow-left me-1"></i> RETURN TO MISSION CONTROL
        </a>
        <h2 class="display-6 fw-bold"><?php echo htmlspecialchars($event['title'] ?? 'Participants List'); ?></h2>
        <div class="mt-2 text-white-50 small">
            <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($event['location'] ?? ''); ?> 
            <span class="mx-2">|</span> 
            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
        </div>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="glass-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Contact Information</th>
                        <th>Status</th>
                        <th>Joined On</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($participants && $participants->num_rows > 0): ?>
                        <?php while($user = $participants->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if(!empty($user['profile_image'])): ?>
                                        <img src="<?php echo $user['profile_image']; ?>" class="user-avatar me-3">
                                    <?php else: ?>
                                        <div class="user-avatar me-3 d-flex align-items-center justify-content-center bg-light text-muted">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="small"><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="small"><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($user['phone'] ?? 'No Phone'); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Interested</span>
                            </td>
                            <td>
                                <div class="small text-muted"><?php echo date('M d, Y', strtotime($user['joined_at'])); ?></div>
                            </td>
                            <td class="text-end">
                                <a href="?remove_user=<?php echo $user['id']; ?>&event_id=<?php echo $event_id; ?>" 
                                   class="text-danger action-link" 
                                   onclick="return confirm('Remove this user from the participant list?')">
                                    <i class="fas fa-user-minus"></i> Remove
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3 opacity-25"></i>
                                    <p>No participants have registered interest for this event yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>