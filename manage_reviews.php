<?php
session_start();
include 'db_config.php'; // আপনার ডাটাবেস কানেকশন ফাইল

// Strict Admin Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Logic to Approve Review
if (isset($_GET['approve_id'])) {
    $approve_id = intval($_GET['approve_id']);
    $conn->query("UPDATE testimonials SET status = 'approved' WHERE id = $approve_id");
    header("Location: manage_reviews.php?msg=approved");
    exit();
}

// Logic to Delete Review
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM testimonials WHERE id = $delete_id");
    header("Location: manage_reviews.php?msg=deleted");
    exit();
}

// Fetch all testimonials (Pending ones first)
$reviews = $conn->query("SELECT * FROM testimonials ORDER BY status DESC, created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Management | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; }
        .admin-header {
            background: #0a1329;
            color: white;
            padding: 60px 0;
            margin-bottom: -40px;
        }
        .glass-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }
        .gold-label { 
            font-size: 0.75rem; 
            letter-spacing: 1.5px; 
            font-weight: 700; 
            color: #B8860B; 
            text-transform: uppercase;
        }
        .badge-status {
            font-size: 0.7rem;
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .bg-pending { background: #fff8eb; color: #B8860B; border: 1px solid #ffeeba; }
        .bg-approved { background: #e7f9ed; color: #198754; border: 1px solid #d1f2db; }

        .table thead th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 20px;
            border: none;
        }
        .table tbody td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f1f1;
        }
        .star-active { color: #ffc107; }
        .star-inactive { color: #dee2e6; }

        .btn-action {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: 0.2s;
            border: 1px solid #eee;
            color: #6c757d;
            text-decoration: none;
            margin-left: 5px;
        }
        .btn-approve:hover { background: #198754; color: white; border-color: #198754; }
        .btn-delete:hover { background: #dc3545; color: white; border-color: #dc3545; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Community Feedback</span>
        <h2 class="display-6 fw-bold mt-2">Testimonial Manager</h2>
        <p class="opacity-50 small">Review and moderate user-submitted stories</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row">
        <div class="col-12">
            
            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4 rounded-3">
                    <?php echo ($_GET['msg'] == 'approved') ? 'Review published successfully!' : 'Review removed permanently.'; ?>
                </div>
            <?php endif; ?>

            <div class="glass-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Author</th>
                                <th>Testimonial</th>
                                <th>Rating</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $reviews->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['user_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['user_role']); ?></div>
                                </td>
                                <td style="max-width: 300px;">
                                    <div class="small text-muted fst-italic">"<?php echo htmlspecialchars($row['message']); ?>"</div>
                                    <div class="extra-small text-muted mt-1" style="font-size: 0.65rem;">
                                        <i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star <?php echo ($i <= $row['rating']) ? 'star-active' : 'star-inactive'; ?> small"></i>
                                    <?php endfor; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo (strtolower($row['status']) == 'pending') ? 'bg-pending' : 'bg-approved'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if(strtolower($row['status']) == 'pending'): ?>
                                        <a href="?approve_id=<?php echo $row['id']; ?>" class="btn-action btn-approve" title="Approve Review">
                                            <i class="fas fa-check small"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?delete_id=<?php echo $row['id']; ?>" 
                                       class="btn-action btn-delete" 
                                       onclick="return confirm('Delete this testimonial permanently?')" 
                                       title="Remove Review">
                                        <i class="fas fa-trash-alt small"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($reviews->num_rows == 0): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No testimonials found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="admin.php" class="text-muted text-decoration-none small">
                    <i class="fas fa-arrow-left me-1"></i> Return to Command HQ
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