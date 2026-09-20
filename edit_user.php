<?php
session_start();
include 'db_config.php'; 

// ১. প্রফেশনাল অ্যাডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// ২. ইউজার ডাটা রিট্রিভ করা
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM users WHERE id = $id");
    $user = $res->fetch_assoc();
    
    if (!$user) {
        die("User not found.");
    }
} else {
    header("Location: manage_users.php");
    exit();
}

// ৩. আপডেট লজিক
if (isset($_POST['update_user'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $_POST['role'];
    $bio = $conn->real_escape_string($_POST['bio']);
    $address = $conn->real_escape_string($_POST['address']);

    $sql = "UPDATE users SET 
            full_name = '$full_name', 
            email = '$email', 
            phone = '$phone', 
            role = '$role', 
            bio = '$bio', 
            address = '$address' 
            WHERE id = $id";

    if ($conn->query($sql)) {
        header("Location: manage_users.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Member | Heartbeat Heaven Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fa; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border: none; 
            padding: 40px; 
        }
        .form-label { font-weight: 700; color: #0a1329; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-control-custom {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 12px;
            background: #f8f9fa;
            transition: 0.3s;
        }
        .form-control-custom:focus {
            background: white;
            border-color: #B8860B;
            box-shadow: 0 0 0 0.25rem rgba(184, 134, 11, 0.1);
            outline: none;
        }
        .btn-gold {
            background: #0a1329;
            color: #B8860B;
            font-weight: 700;
            border: 1px solid #0a1329;
            padding: 12px 40px;
            border-radius: 12px;
            transition: 0.3s;
        }
        .btn-gold:hover { background: #B8860B; color: #0a1329; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        .profile-preview { width: 90px; height: 90px; border-radius: 15px; object-fit: cover; border: 3px solid #eee; }
        .critical-zone {
            background: #fffafa;
            border: 1px solid #ffe5e5;
            border-radius: 15px;
            padding: 25px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Administrator Control</span>
        <h2 class="display-6 fw-bold mt-2">Modify User Permissions</h2>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="glass-card">
                
                <div class="d-flex align-items-center mb-5 p-3 rounded-4" style="background: #f8f9fa;">
                    <img src="<?php echo $user['profile_image'] ? 'uploads/'.$user['profile_image'] : 'assets/default-user.png'; ?>" class="profile-preview me-4">
                    <div>
                        <h4 class="mb-1 fw-bold text-dark"><?php echo $user['full_name']; ?></h4>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-uppercase px-3"><?php echo $user['role']; ?></span>
                            <?php if(isset($user['status']) && $user['status'] == 'restricted'): ?>
                                <span class="badge bg-danger px-3"><i class="fas fa-user-slash me-1"></i> RESTRICTED</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control-custom w-100" value="<?php echo $user['full_name']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control-custom w-100" value="<?php echo $user['email']; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control-custom w-100" value="<?php echo $user['phone']; ?>">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Update Role</label>
                            <select name="role" class="form-select form-control-custom">
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="vet" <?php echo $user['role'] == 'vet' ? 'selected' : ''; ?>>Veterinarian</option>
                                <option value="rescuer" <?php echo $user['role'] == 'rescuer' ? 'selected' : ''; ?>>Rescuer</option>
                                <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>General User</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control-custom w-100" value="<?php echo $user['address']; ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">User Biography</label>
                        <textarea name="bio" class="form-control-custom w-100" rows="3"><?php echo $user['bio']; ?></textarea>
                    </div>

                    <!-- Critical Actions Box Updated -->
                    <div class="critical-zone">
                        <h6 class="text-danger fw-bold mb-3"><i class="fas fa-shield-alt me-2"></i> Safety & Access Control</h6>
                        <div class="row g-3">
                            <!-- Restrict/Unrestrict Button -->
                            <div class="col-md-4">
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php if (isset($user['status']) && $user['status'] == 'restricted'): ?>
                                        <a href="manage_users.php?unrestrict_id=<?php echo $user['id']; ?>" class="btn btn-success w-100 btn-sm fw-bold">
                                            <i class="fas fa-user-check me-2"></i> Lift Restriction
                                        </a>
                                    <?php else: ?>
                                        <a href="manage_users.php?restrict_id=<?php echo $user['id']; ?>" class="btn btn-warning w-100 btn-sm fw-bold" onclick="return confirm('Restrict this user from logging in?')">
                                            <i class="fas fa-user-lock me-2"></i> Restrict Account
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button disabled class="btn btn-light w-100 btn-sm fw-bold">Self Access Secure</button>
                                <?php endif; ?>
                            </div>

                            <!-- Sack Button -->
                            <div class="col-md-4">
                                <?php if ($user['role'] !== 'user' && $user['id'] != $_SESSION['user_id']): ?>
                                    <a href="manage_users.php?sack_id=<?php echo $user['id']; ?>" class="btn btn-outline-warning w-100 btn-sm fw-bold" onclick="return confirm('Demote to regular user?')">
                                        <i class="fas fa-user-minus me-2"></i> Sack Member
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-light w-100 btn-sm disabled fw-bold">Sack Not Applicable</button>
                                <?php endif; ?>
                            </div>

                            <!-- Remove Button -->
                            <div class="col-md-4">
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="manage_users.php?delete_id=<?php echo $user['id']; ?>" class="btn btn-outline-danger w-100 btn-sm fw-bold" onclick="return confirm('PERMANENTLY DELETE this user?')">
                                        <i class="fas fa-trash-alt me-2"></i> Remove Account
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-light w-100 btn-sm disabled fw-bold">Cannot Remove Self</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-5">
                        <a href="manage_users.php" class="text-decoration-none text-muted fw-bold small">
                            <i class="fas fa-arrow-left me-2"></i> Back to Directory
                        </a>
                        <button type="submit" name="update_user" class="btn-gold">
                            Update Member Record
                        </button>
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