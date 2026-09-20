<?php
session_start();
include 'db_config.php';

// ১. অথেন্টিকেশন: শুধুমাত্র অ্যাডমিন একসেস পাবেন
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// ২. ভেট এসাইন করার লজিক (Step 7)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_vet'])) {
    $vet_id = intval($_POST['vet_id']);
    $rescue_id = intval($_POST['rescue_id']);
    
    // স্ট্যাটাস আপডেট করে 'Assigned to Vet' করা হচ্ছে
    // এখানে আমরা animals টেবিলে ডাটা পাঠাচ্ছি না, কারণ আপনি এটি পরে এডিট করে পাঠাতে চান।
    $update_res = $conn->prepare("UPDATE rescues SET status = 'Assigned to Vet', assigned_vet = ? WHERE id = ?");
    $update_res->bind_param("ii", $vet_id, $rescue_id);
    
    if ($update_res->execute()) {
        $_SESSION['success'] = "Medical professional assigned successfully! The case is now under treatment.";
    } else {
        $_SESSION['error'] = "Error assigning vet: " . $conn->error;
    }
    
    header("Location: manage_assignments.php");
    exit();
}

// ৩. ডাটা রিট্রিভাল

// ৩.১ নিউ রেসকিউ: যাদের অ্যাডমিন 'Ready for Vet' স্ট্যাটাস দিয়েছেন (manage_rescues.php থেকে আসা)
$ready_for_vets = $conn->query("SELECT id, species, media_path, location, clinic_name 
                                FROM rescues 
                                WHERE status = 'Ready for Vet' 
                                ORDER BY id DESC");

// ৩.২ চিকিৎসাধীন: রেসকিউ টেবিলে যারা 'Assigned to Vet' অবস্থায় আছে
$under_treatment = $conn->query("SELECT r.*, u.full_name as vet_name 
                                 FROM rescues r 
                                 JOIN users u ON r.assigned_vet = u.id 
                                 WHERE r.status = 'Assigned to Vet'");

// ভেটদের লিস্ট আনা (ড্রপডাউনের জন্য)
$vets_query = $conn->query("SELECT id, full_name FROM users WHERE role = 'vet'");
$vets = []; while($v = $vets_query->fetch_assoc()) { $vets[] = $v; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Logistics | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; margin-bottom: 30px; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        .nav-tabs { border-bottom: 1px solid #eee; background: #fdfdfd; }
        .nav-tabs .nav-link { color: #6c757d; font-weight: 600; border: none; padding: 15px 25px; }
        .nav-tabs .nav-link.active { color: #0a1329; border-bottom: 3px solid #B8860B; background: white; }
        .animal-avatar { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; }
        .btn-assign-command { background: #0a1329; color: white; border-radius: 10px; font-weight: 700; padding: 8px 20px; border: none; transition: 0.3s; }
        .btn-assign-command:hover { background: #B8860B; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Medical Assignment Center</span>
        <h2 class="display-6 fw-bold mt-2">Vet Logistics HQ</h2>
        <p class="opacity-50 small">Deploy veterinary doctors for verified rescue cases</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="glass-card">
        <ul class="nav nav-tabs px-2" id="medTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#assign-tab">
                    Waiting for Vet (<?= $ready_for_vets->num_rows ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#treatment-tab">
                    Under Treatment (<?= $under_treatment->num_rows ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="assign-tab">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Rescue Info</th>
                                <th>Source/Location</th>
                                <th class="text-end pe-4">Deploy Professional</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ready_for_vets->num_rows > 0): while($row = $ready_for_vets->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $row['media_path']; ?>" class="animal-avatar me-3" onclick="window.open(this.src)">
                                        <div>
                                            <div class="fw-bold"><?= $row['species']; ?></div>
                                            <div class="small text-muted">ID: #<?= $row['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark"><?= $row['clinic_name'] ?? 'Street Rescue'; ?></div>
                                    <div class="small text-muted"><?= $row['location']; ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <form action="" method="POST" class="d-inline-flex gap-2">
                                        <input type="hidden" name="rescue_id" value="<?= $row['id']; ?>">
                                        <select name="vet_id" class="form-select form-select-sm" required style="width: 200px;">
                                            <option value="">Choose Vet</option>
                                            <?php foreach($vets as $vet): ?>
                                                <option value="<?= $vet['id']; ?>">Dr. <?= $vet['full_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_vet" class="btn-assign-command">ASSIGN</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" class="text-center p-5 text-muted">No cases awaiting medical professional assignment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="treatment-tab">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Patient Info</th>
                                <th>Assigned Vet</th>
                                <th>Current Status</th>
                                <th class="text-center">Next Step</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($under_treatment->num_rows > 0): while($row = $under_treatment->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= $row['species']; ?> (#<?= $row['id']; ?>)</div>
                                    <div class="small text-muted"><?= $row['location']; ?></div>
                                </td>
                                <td>
                                    <div class="small fw-bold"><i class="fas fa-user-md me-1 text-primary"></i> Dr. <?= $row['vet_name']; ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-heartbeat me-1"></i> Medical Care</span>
                                </td>
                                <td class="text-center">
                                    <span class="small text-muted">Waiting for Vet Clearance</span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center p-5 text-muted">No animals are currently in the treatment phase.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>