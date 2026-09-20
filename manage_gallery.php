<?php
session_start();
include 'db_config.php';

// Strict Admin Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// --- LOGIC: UPLOAD IMAGE TO DIRECTORY ---
if (isset($_POST['upload_img'])) {
    $caption = mysqli_real_escape_string($conn, $_POST['caption']);
    
    // ডিরেক্টরি পাথ: আপনার প্রজেক্টের uploads/gallery ফোল্ডারে যাবে
    $target_dir = "uploads/gallery/";
    
    // ফোল্ডার না থাকলে তৈরি করবে
    if (!is_dir($target_dir)) { 
        mkdir($target_dir, 0777, true); 
    }

    $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $file_name = "GAL_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    // ফাইল ভ্যালিডেশন
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // ডাটাবেসে পাথ সেভ করা হচ্ছে
            $conn->query("INSERT INTO gallery (image_path, caption, status) VALUES ('$target_file', '$caption', 'active')");
            header("Location: manage_gallery.php?msg=uploaded");
        } else {
            header("Location: manage_gallery.php?msg=error");
        }
    } else {
        header("Location: manage_gallery.php?msg=invalid");
    }
    exit();
}

// --- LOGIC: DELETE IMAGE & FILE ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // প্রথমে ফাইল পাথ খুঁজে বের করা
    $res = $conn->query("SELECT image_path FROM gallery WHERE id = $id");
    if ($img = $res->fetch_assoc()) {
        // ফোল্ডার থেকেও ডিলিট করে দিবে
        if (file_exists($img['image_path'])) { 
            unlink($img['image_path']); 
        }
    }
    
    $conn->query("DELETE FROM gallery WHERE id = $id");
    header("Location: manage_gallery.php?msg=deleted");
    exit();
}

// ডাটা ফেচ করা
$images = $conn->query("SELECT * FROM gallery ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gallery Hub | Praner Tan Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        .img-preview { width: 100px; height: 70px; object-fit: cover; border-radius: 12px; border: 2px solid #f1f1f1; }
        .btn-gold { background: #B8860B; color: white; border-radius: 10px; font-weight: 600; border: none; transition: 0.3s; }
        .btn-gold:hover { background: #966d08; transform: translateY(-2px); color: white; }
        .table thead th { background: #f8f9fa; padding: 20px; font-size: 0.8rem; color: #6c757d; text-transform: uppercase; border: none; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Visual Content</span>
        <h2 class="display-6 fw-bold mt-2">Gallery Hub</h2>
        <button class="btn btn-gold mt-3 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-upload me-2"></i>Upload New Photo
        </button>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 rounded-3">
            <?php 
                if($_GET['msg'] == 'uploaded') echo "Image successfully added to gallery!";
                if($_GET['msg'] == 'deleted') echo "Image removed from server and database.";
            ?>
        </div>
    <?php endif; ?>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Preview</th>
                        <th>Caption & Details</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $images->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <a href="<?php echo $row['image_path']; ?>" target="_blank">
                                <img src="<?php echo $row['image_path']; ?>" class="img-preview shadow-sm">
                            </a>
                        </td>
                        <td>
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['caption'] ?: 'No Caption'); ?></div>
                            <div class="extra-small text-muted" style="font-size: 0.7rem;">
                                <i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge rounded-pill <?php echo ($row['status'] == 'active') ? 'bg-light text-success' : 'bg-light text-secondary'; ?> border shadow-sm" style="font-size: 0.65rem;">
                                <?php echo strtoupper($row['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="?delete_id=<?php echo $row['id']; ?>" 
                               class="btn btn-sm btn-outline-danger border-0" 
                               onclick="return confirm('Delete this image permanently from folder?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($images->num_rows == 0): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted small">No images in gallery. Start by uploading one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="admin.php" class="text-muted text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i> Back to HQ
        </a>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Upload to Praner Tan Gallery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="small fw-bold mb-1 text-muted">Select Image File</label>
                    <input type="file" name="image" class="form-control" required style="border-radius: 12px; font-size: 0.9rem;">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1 text-muted">Caption (Optional)</label>
                    <input type="text" name="caption" class="form-control" placeholder="e.g. Happy puppy after rescue" style="border-radius: 10px;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="upload_img" class="btn btn-gold w-100 py-2 shadow-sm">Confirm Upload</button>
            </div>
        </form>
    </div>
</div>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>