<?php
session_start();
include 'db_config.php';

// Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Approval/Rejection Logic
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $res = $conn->query("SELECT animal_id FROM adoption_requests WHERE id = $request_id");
        if ($data = $res->fetch_assoc()) {
            $animal_id = $data['animal_id'];
            $conn->query("UPDATE adoption_requests SET status = 'approved' WHERE id = $request_id");
            $conn->query("UPDATE animals SET status = 'adopted' WHERE id = $animal_id");
            $conn->query("UPDATE adoption_requests SET status = 'rejected' WHERE animal_id = $animal_id AND status = 'pending'");
        }
        $_SESSION['success'] = "Adoption approved and animal status updated!";
    } elseif ($action === 'reject') {
        $conn->query("UPDATE adoption_requests SET status = 'rejected' WHERE id = $request_id");
        $_SESSION['success'] = "Request has been rejected.";
    }
    
    header("Location: manage_adoptions.php");
    exit();
}

// Fetch Pending Adoptions
$query = "SELECT ar.*, u.full_name, u.email, u.phone, a.name as animal_name, a.species, a.image_path 
          FROM adoption_requests ar 
          JOIN users u ON ar.user_id = u.id 
          JOIN animals a ON ar.animal_id = a.id 
          WHERE ar.status = 'pending'
          ORDER BY ar.request_date DESC";
$requests = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Adoption Management | Moonlight of Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; margin-bottom: 30px; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        
        .table thead { background: #f8f9fa; }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; border: none; padding: 20px; }
        .table tbody td { padding: 20px; border-bottom: 1px solid #f1f1f1; }
        
        .animal-preview { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; border: 2px solid #eee; }
        .user-avatar { width: 45px; height: 45px; background: #f1f3f5; color: #0a1329; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; }
        
        .btn-hq-approve { background: #198754; color: white !important; border-radius: 10px; font-weight: 700; padding: 8px 16px; font-size: 0.8rem; border: none; transition: 0.3s; }
        .btn-hq-reject { background: #fff; color: #dc3545 !important; border: 1px solid #ffdee2; border-radius: 10px; font-weight: 700; padding: 8px 16px; font-size: 0.8rem; transition: 0.3s; margin-left: 5px; }
        .btn-hq-approve:hover { background: #146c43; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(25, 135, 84, 0.2); }
        .btn-hq-reject:hover { background: #dc3545; color: white !important; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Shelter Control</span>
        <h2 class="display-6 fw-bold mt-2">Pending Adoptions</h2>
        <p class="opacity-50 small">Finalize forever homes for our rescue residents</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Pet Profile</th>
                        <th>Applicant</th>
                        <th>Request Date</th>
                        <th class="text-end">Command</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests->num_rows > 0): ?>
                        <?php while($row = $requests->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo !empty($row['image_path']) ? $row['image_path'] : 'assets/img/placeholder.png'; ?>" class="animal-preview me-3">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['animal_name']); ?></div>
                                        <div class="small text-muted text-uppercase" style="font-size: 0.65rem;"><?php echo htmlspecialchars($row['species']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['phone']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold" style="font-size: 0.85rem;">
                                    <i class="far fa-calendar-alt text-muted me-1"></i>
                                    <?php echo date('M d, Y', strtotime($row['request_date'])); ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="?action=approve&id=<?php echo $row['id']; ?>" class="btn-hq-approve text-decoration-none" onclick="return confirm('Approve this adoption and finalize the record?')">
                                    APPROVE
                                </a>
                                <a href="?action=reject&id=<?php echo $row['id']; ?>" class="btn-hq-reject text-decoration-none" onclick="return confirm('Reject this request?')">
                                    REJECT
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="opacity-25 mb-3"><i class="fas fa-heart fa-3x"></i></div>
                                <h6 class="text-muted fw-normal">No pending adoption requests at the moment.</h6>
                                <a href="admin.php" class="btn btn-sm btn-outline-dark mt-2 rounded-pill px-4">Admin Home</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="admin.php" class="text-muted text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i> Back to Admin Dashboard
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>