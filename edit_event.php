<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing data
$res = $conn->query("SELECT * FROM events WHERE id = $id");
$event = $res->fetch_assoc();

if (!$event) {
    header("Location: manage_events.php?error=notfound");
    exit();
}

// Logic: Update Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $date = $_POST['event_date'];
    $location = $conn->real_escape_string($_POST['location']);
    $desc = $conn->real_escape_string($_POST['description']);
    $image_path = $event['image_path']; // Keep old image by default

    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "uploads/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        $clean_title = preg_replace("/[^a-zA-Z0-9]/", "", $title);
        $file_name = time() . "_" . $clean_title . "." . $file_ext;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            // Delete old file if it exists
            if (!empty($event['image_path']) && file_exists($event['image_path'])) {
                unlink($event['image_path']);
            }
            $image_path = $target_file;
        }
    }

    $sql = "UPDATE events SET 
            title = '$title', 
            event_date = '$date', 
            location = '$location', 
            description = '$desc', 
            image_path = '$image_path' 
            WHERE id = $id";
    
    if ($conn->query($sql)) {
        header("Location: manage_events.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; padding: 40px; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        
        .form-label { font-size: 0.85rem; font-weight: 700; color: #0a1329; margin-bottom: 8px; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #e1e1e1; background: #fcfcfc; }
        .form-control:focus { border-color: #B8860B; box-shadow: none; background: white; }
        
        .current-img-preview { width: 100%; max-height: 200px; object-fit: cover; border-radius: 15px; margin-bottom: 15px; border: 1px solid #eee; }
        .btn-gold { background: #B8860B; color: #0a1329; font-weight: 700; border-radius: 12px; border: none; padding: 15px 30px; transition: 0.3s; }
        .btn-gold:hover { background: #966d09; color: white; transform: translateY(-2px); }
        .back-link { color: #B8860B; text-decoration: none; font-weight: 700; font-size: 0.85rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <a href="manage_events.php" class="back-link mb-3 d-inline-block">
            <i class="fas fa-arrow-left me-1"></i> BACK TO EVENTS
        </a>
        <h2 class="display-6 fw-bold">Edit Campaign Details</h2>
        <p class="text-white-50 small">Updating ID: #<?php echo $id; ?></p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-4">
                                <label class="form-label">Event Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Event Date</label>
                                    <input type="date" name="event_date" class="form-control" value="<?php echo $event['event_date']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($event['location']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4 text-center">
                            <label class="form-label d-block text-start">Current Banner</label>
                            <?php if(!empty($event['image_path'])): ?>
                                <img src="<?php echo $event['image_path']; ?>" class="current-img-preview">
                            <?php else: ?>
                                <div class="current-img-preview d-flex align-items-center justify-content-center bg-light text-muted">
                                    <i class="fas fa-image fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="event_image" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted mt-2 d-block">Upload new to replace</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="update_event" class="btn btn-gold">
                            <i class="fas fa-save me-2"></i> UPDATE MISSION DETAILS
                        </button>
                        <a href="manage_events.php" class="btn btn-light border-0 py-3" style="border-radius: 12px; font-weight: 600;">Cancel Changes</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>