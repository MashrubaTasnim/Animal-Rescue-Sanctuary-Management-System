<?php
session_start();
include 'db_config.php';

// ── Login check ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$success_msg = "";
$error_msg   = "";

// ── CSRF token generation ─────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("CSRF validation failed. Go back and try again.");
    }
}

// ── Fetch user ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── Preset avatars — Robohash set4 cute animal faces (free, no API key needed) ───────
$preset_avatars = [
    'https://robohash.org/Cat?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Dog?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Rabbit?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Fox?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Bear?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Panda?set=set4&size=200x200&bgset=bg2',
    'https://robohash.org/Owl?set=set4&size=200x200&bgset=bg1',
    'https://robohash.org/Deer?set=set4&size=200x200&bgset=bg2',
];

$animal_labels = ['Cat', 'Dog', 'Rabbit', 'Fox', 'Bear', 'Panda', 'Owl', 'Deer'];

// ── Helper: check if a URL is a Robohash or DiceBear preset ───────────────────────────────
function is_dicebear_preset($url) {
    return !empty($url) && (
        str_starts_with($url, 'https://api.dicebear.com/') ||
        str_starts_with($url, 'https://robohash.org/')
    );
}

// ── Helper: safe old-image cleanup (skip DiceBear URLs and presets) ───────────
function maybe_unlink_old_image($path, $preset_avatars) {
    if (
        !empty($path) &&
        !in_array($path, $preset_avatars, true) &&
        !is_dicebear_preset($path) &&
        file_exists($path)
    ) {
        unlink($path);
    }
}

// ── 1. Profile update ─────────────────────────────────────────────────────────
if (isset($_POST['update_profile'])) {
    verify_csrf();

    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $bio       = trim($_POST['bio']);
    $address   = trim($_POST['address']);
    $new_email = trim($_POST['email']);

    // Basic validation
    if (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error_msg = "Full name must be between 2 and 100 characters.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } elseif (strlen($bio) > 500) {
        $error_msg = "Bio must be under 500 characters.";
    } else {
        // Email uniqueness check
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $new_email, $user_id);
        $email_check->execute();

        if ($email_check->get_result()->num_rows > 0) {
            $error_msg = "That email address is already in use by another account.";
        } else {
            $image_path = $user['profile_image'];

            // ── Preset avatar chosen ──────────────────────────────────────────
            if (!empty($_POST['preset_avatar'])) {
                $chosen = $_POST['preset_avatar'];
                // Only allow known presets or validated Robohash/DiceBear URLs
                if (
                    in_array($chosen, $preset_avatars, true) ||
                    (is_dicebear_preset($chosen) && filter_var($chosen, FILTER_VALIDATE_URL))
                ) {
                    $image_path = $chosen;
                }
            }
            // ── File upload (overrides preset if both sent) ───────────────────
            elseif (!empty($_FILES['profile_pic']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $max_size      = 2 * 1024 * 1024; // 2 MB

                $file = $_FILES['profile_pic'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $mime = mime_content_type($file['tmp_name']); // real MIME, not spoofable

                if ($file['size'] > $max_size) {
                    $error_msg = "Image must be under 2 MB.";
                } elseif (!in_array($mime, $allowed_types) || !in_array($ext, $allowed_exts)) {
                    $error_msg = "Only JPG, PNG, GIF, or WEBP images are allowed.";
                } else {
                    $target_dir = "uploads/profiles/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                    $file_name   = "user_" . $user_id . "_" . time() . "." . $ext;
                    $target_file = $target_dir . $file_name;

                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        // Delete old custom upload (not preset, not DiceBear/Robohash)
                        maybe_unlink_old_image($user['profile_image'], $preset_avatars);
                        $image_path = $target_file;
                    } else {
                        $error_msg = "Failed to save the uploaded image. Please try again.";
                    }
                }
            }

            if (empty($error_msg)) {
                $update = $conn->prepare(
                    "UPDATE users SET full_name=?, phone=?, bio=?, address=?, profile_image=?, email=? WHERE id=?"
                );
                $update->bind_param("ssssssi", $full_name, $phone, $bio, $address, $image_path, $new_email, $user_id);

                if ($update->execute()) {
                    $_SESSION['flash_success'] = "Profile updated successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $error_msg = "Database error. Please try again.";
                }
            }
        }
    }
}

// ── 2. Password change ────────────────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    verify_csrf();

    $current_pw = $_POST['current_pw'];
    $new_pw     = $_POST['new_pw'];
    $confirm_pw = $_POST['confirm_pw'];

    if (strlen($new_pw) < 8) {
        $error_msg = "New password must be at least 8 characters.";
    } elseif ($new_pw !== $confirm_pw) {
        $error_msg = "New passwords do not match.";
    } elseif (!password_verify($current_pw, $user['password'])) {
        $error_msg = "Current password is incorrect.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $pw_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pw_update->bind_param("si", $hashed, $user_id);
        $pw_update->execute();

        // Regenerate CSRF token after sensitive action
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['flash_success'] = "Password changed successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ── 3. Resignation request ────────────────────────────────────────────────────
if (isset($_POST['request_resign'])) {
    verify_csrf();

    $reason  = trim($_POST['resignation_reason']);
    $reason  = substr($reason, 0, 500); // cap length
    $res_msg = "Resignation Reason: " . $reason;
    $status  = 'pending';

    $status_update = $conn->prepare(
        "UPDATE users SET resignation_status = ?, status_note = ? WHERE id = ?"
    );
    $status_update->bind_param("ssi", $status, $res_msg, $user_id);

    if ($status_update->execute()) {
        $_SESSION['flash_success'] = "Resignation request submitted. Pending admin approval.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ── 4. Delete account ─────────────────────────────────────────────────────────
if (isset($_POST['delete_account'])) {
    verify_csrf();

    $confirm_delete_pw = $_POST['confirm_delete_pw'] ?? '';
    if (!password_verify($confirm_delete_pw, $user['password'])) {
        $error_msg = "Password confirmation failed. Account not deleted.";
    } else {
        // Remove uploaded profile image from disk (not presets, not DiceBear/Robohash URLs)
        maybe_unlink_old_image($user['profile_image'], $preset_avatars);

        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $user_id);

        if ($delete->execute()) {
            session_unset();
            session_destroy();
            header("Location: register.php?msg=deleted");
            exit();
        } else {
            $error_msg = "Could not delete account. Please contact support.";
        }
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────
if (!empty($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ── Current avatar src ────────────────────────────────────────────────────────
$img_src = (
    (!empty($user['profile_image']) && file_exists($user['profile_image'])) ||
    is_dicebear_preset($user['profile_image'])
)
    ? $user['profile_image']
    : 'https://robohash.org/' . urlencode($user['full_name']) . '?set=set4&size=200x200&bgset=bg1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | HEARTBEAT HEAVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark: #0a1329;
            --accent: #ffefc2;
            --danger-soft: #fff8f8;
        }
        body { padding-top: 80px; background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }

        .header-section {
            background: linear-gradient(rgba(10,19,41,0.9), rgba(10,19,41,0.9)),
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?q=80&w=2000');
            background-size: cover; background-position: center;
            padding: 60px 0 80px; text-align: center; color: white;
        }
        .profile-card-shift { margin-top: -60px; }

        /* Avatar */
        .avatar-box { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 5px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
        .role-badge { background: var(--accent); color: var(--dark); font-size: 11px; padding: 4px 12px; border-radius: 50px; font-weight: 800; text-transform: uppercase; display: inline-block; }

        /* Sidebar nav */
        .nav-pill-btn { color: #6c757d; font-weight: 700; border-radius: 12px; padding: 12px 20px; border: none; text-align: left; background: none; width: 100%; margin-bottom: 5px; transition: .25s; }
        .nav-pill-btn.active, .nav-pill-btn[aria-selected="true"] { background: var(--dark) !important; color: white !important; }
        .nav-pill-btn:hover:not(.active) { background: rgba(10,19,41,0.06); }

        /* Form labels */
        .form-label-small { font-size: .7rem; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 5px; }

        /* Buttons */
        .btn-update-profile { background: var(--dark); color: white; border-radius: 50px; padding: 12px 40px; font-weight: 700; border: none; }
        .btn-update-profile:hover { background: #1a2a4a; color: white; }

        /* Danger zone */
        .danger-zone-card { border: 1px solid rgba(255,0,0,.17); background: var(--danger-soft); border-radius: 15px; padding: 20px; }

        /* ── Avatar Picker ───────────────────────────────────────────────── */
        .avatar-picker-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px; }
        .avatar-option { position: relative; cursor: pointer; }
        .avatar-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .avatar-option img {
            width: 100%; aspect-ratio: 1; border-radius: 50%; object-fit: cover;
            border: 3px solid transparent; transition: .2s;
            filter: grayscale(30%);
            background: #f0f4ff; /* fallback bg while image loads */
        }
        .avatar-option input:checked + img,
        .avatar-option img:hover {
            border-color: var(--dark);
            filter: none;
            transform: scale(1.06);
            box-shadow: 0 4px 12px rgba(10,19,41,0.2);
        }
        .avatar-option input:checked + img { box-shadow: 0 0 0 3px white, 0 0 0 5px var(--dark); }
        .avatar-option .avatar-label {
            display: block; text-align: center; font-size: .65rem;
            font-weight: 700; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px;
        }

        .avatar-source-tabs .btn { border-radius: 50px; font-size: .78rem; font-weight: 700; padding: 6px 18px; }
        .avatar-source-tabs .btn-dark { background: var(--dark); border-color: var(--dark); }

        /* Password strength bar */
        #pw-strength-bar { height: 4px; border-radius: 2px; transition: width .3s, background .3s; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="header-section">
    <div class="container">
        <h2 class="display-6 fw-bold" style="font-family:'Playfair Display',serif;">Account Settings</h2>
    </div>
</header>

<div class="container profile-card-shift mb-5">
  <div class="row justify-content-center">
    <div class="col-lg-11">
      <div class="card bg-white border-0 shadow-sm" style="border-radius:24px;overflow:hidden;">
        <div class="row g-0">

          <!-- ── Sidebar ─────────────────────────────────────────────── -->
          <div class="col-md-4 bg-light border-end p-4 text-center">
            <div class="mb-3">
              <img src="<?php echo htmlspecialchars($img_src); ?>" class="avatar-box mb-3" id="preview" alt="Profile photo">
              <div class="d-block"><span class="role-badge"><?php echo htmlspecialchars($user['role']); ?></span></div>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <p class="text-muted small mb-4"><?php echo htmlspecialchars($user['email']); ?></p>

            <div class="nav flex-column nav-pills text-start" role="tablist">
              <button class="nav-link nav-pill-btn active" data-bs-toggle="pill" data-bs-target="#profile-form">
                <i class="fas fa-user-edit me-2"></i> Profile Info
              </button>
              <button class="nav-link nav-pill-btn" data-bs-toggle="pill" data-bs-target="#security-form">
                <i class="fas fa-lock me-2"></i> Password & Security
              </button>
              <button class="nav-link nav-pill-btn text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
              </button>
            </div>
          </div>

          <!-- ── Main Content ───────────────────────────────────────── -->
          <div class="col-md-8 p-4 p-lg-5">

            <?php if ($success_msg): ?>
              <div class="alert alert-success border-0 small shadow-sm d-flex align-items-center gap-2">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
              </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
              <div class="alert alert-danger border-0 small shadow-sm d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
              </div>
            <?php endif; ?>

            <div class="tab-content">

              <!-- ══ TAB 1: Profile Info ════════════════════════════════ -->
              <div class="tab-pane fade show active" id="profile-form">
                <h5 class="fw-bold mb-4">Profile Information</h5>
                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                  <!-- Avatar source toggle -->
                  <div class="mb-4">
                    <label class="form-label-small">Profile Photo</label>
                    <div class="avatar-source-tabs d-flex gap-2 mb-3">
                      <button type="button" class="btn btn-dark btn-sm" onclick="showTab('upload-tab', this)">Upload Photo</button>
                      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showTab('preset-tab', this)">Choose Animal Avatar</button>
                    </div>

                    <!-- Upload tab -->
                    <div id="upload-tab">
                      <input type="file" name="profile_pic" id="profile_pic_input"
                             class="form-control form-control-sm"
                             accept="image/jpeg,image/png,image/gif,image/webp"
                             onchange="previewUpload(this)">
                      <small class="text-muted d-block mt-1">Max 2 MB · JPG, PNG, GIF, WEBP</small>
                    </div>

                    <!-- Preset animal avatar tab -->
                    <div id="preset-tab" style="display:none;">
                      <div class="avatar-picker-grid">
                        <?php foreach ($preset_avatars as $i => $av):
                            $label = $animal_labels[$i] ?? 'Animal ' . ($i + 1);
                        ?>
                          <label class="avatar-option" title="<?php echo htmlspecialchars($label); ?>">
                            <input type="radio" name="preset_avatar"
                                   value="<?php echo htmlspecialchars($av); ?>"
                                   onchange="previewPreset('<?php echo htmlspecialchars($av); ?>')"
                                   <?php echo ($user['profile_image'] === $av) ? 'checked' : ''; ?>>
                            <img src="<?php echo htmlspecialchars($av); ?>"
                                 alt="<?php echo htmlspecialchars($label); ?> avatar"
                                 style="background:#f0f4ff;">
                            <span class="avatar-label"><?php echo htmlspecialchars($label); ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                      <small class="text-muted d-block mt-2">Click an animal to select it.</small>
                    </div>
                  </div>

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label-small">Full Name</label>
                      <input type="text" name="full_name" class="form-control" maxlength="100"
                             value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label-small">Email Address</label>
                      <input type="email" name="email" class="form-control"
                             value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label-small">Phone</label>
                      <input type="tel" name="phone" class="form-control" maxlength="20"
                             value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label-small">Address</label>
                      <input type="text" name="address" class="form-control" maxlength="200"
                             value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                      <label class="form-label-small">Bio <span class="text-muted fw-normal">(max 500 chars)</span></label>
                      <textarea name="bio" class="form-control" rows="3" maxlength="500"
                                id="bio-field" oninput="updateBioCount()"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                      <small class="text-muted"><span id="bio-count">0</span>/500</small>
                    </div>
                  </div>
                  <button type="submit" name="update_profile" class="btn btn-update-profile mt-4 w-100">
                    UPDATE PROFILE
                  </button>
                </form>
              </div>

              <!-- ══ TAB 2: Password & Security ═════════════════════════ -->
              <div class="tab-pane fade" id="security-form">
                <h5 class="fw-bold mb-4">Change Password</h5>
                <form method="POST" class="mb-5">
                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                  <div class="mb-3">
                    <label class="form-label-small">Current Password</label>
                    <input type="password" name="current_pw" class="form-control" required autocomplete="current-password">
                  </div>
                  <div class="row g-3 mb-1">
                    <div class="col-md-6">
                      <label class="form-label-small">New Password</label>
                      <input type="password" name="new_pw" class="form-control" required minlength="8"
                             id="new-pw-field" oninput="checkStrength(this.value)" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label-small">Confirm New Password</label>
                      <input type="password" name="confirm_pw" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                  </div>
                  <div class="mb-3">
                    <div style="background:#eee;border-radius:2px;">
                      <div id="pw-strength-bar" style="width:0%;height:4px;border-radius:2px;"></div>
                    </div>
                    <small id="pw-strength-label" class="text-muted"></small>
                  </div>
                  <button type="submit" name="change_password" class="btn btn-dark btn-sm w-100">Update Password</button>
                </form>

                <hr class="my-4">
                <h5 class="fw-bold text-danger mb-3">Danger Zone</h5>
                <div class="danger-zone-card">

                  <?php if ($user['role'] === 'vet' || $user['role'] === 'rescuer'): ?>
                  <div class="mb-4">
                    <h6 class="fw-bold"><i class="fas fa-file-contract me-2"></i>Request Resignation</h6>
                    <p class="small text-muted mb-2">Submit a request to Admin to step down. Cannot be undone once approved.</p>
                    <?php if (!empty($user['resignation_status']) && $user['resignation_status'] === 'pending'): ?>
                      <div class="alert alert-warning small py-2">
                        <i class="fas fa-clock me-1"></i> Your resignation is currently <strong>pending</strong> admin review.
                      </div>
                    <?php else: ?>
                      <form method="POST" onsubmit="return confirm('Send resignation request to admin?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <textarea name="resignation_reason" class="form-control mb-2"
                                  placeholder="Briefly explain why you are resigning…"
                                  rows="2" required maxlength="500"
                                  style="font-size:.85rem;border-radius:10px;"></textarea>
                        <button type="submit" name="request_resign" class="btn btn-outline-dark btn-sm">
                          Submit Resignation Request
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <hr style="border-top:1px dashed #ddd;">
                  <?php endif; ?>

                  <!-- Delete Account — requires password confirmation -->
                  <div>
                    <h6 class="fw-bold text-danger"><i class="fas fa-user-times me-2"></i>Delete Account</h6>
                    <p class="small text-muted mb-2">
                      Permanently deletes your profile and all records. <strong>This cannot be undone.</strong>
                    </p>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                      Delete My Account
                    </button>
                  </div>
                </div>
              </div>
            </div><!-- /tab-content -->
          </div><!-- /col main -->
        </div><!-- /row -->
      </div><!-- /card -->
    </div>
  </div>
</div>

<!-- ── Logout Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:20px;">
      <div class="modal-body text-center p-5">
        <i class="fas fa-sign-out-alt fa-3x text-danger mb-3"></i>
        <h4 class="fw-bold">Logging Out?</h4>
        <p class="text-muted">Are you sure you want to end your session?</p>
        <div class="d-flex gap-2 justify-content-center mt-4">
          <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
          <a href="logout.php" class="btn btn-danger px-4">Yes, Logout</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Delete Account Modal (password confirmation) ───────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:20px;">
      <div class="modal-body text-center p-5">
        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
        <h4 class="fw-bold text-danger">Delete Account Forever?</h4>
        <p class="text-muted mb-4">This will permanently remove your account and all associated data. Enter your password to confirm.</p>
        <form method="POST" onsubmit="return validateDeleteForm()">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="password" name="confirm_delete_pw" id="confirm-delete-pw"
                 class="form-control mb-3 text-center" placeholder="Enter your current password" required autocomplete="current-password">
          <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="delete_account" class="btn btn-danger px-4">Yes, Delete Forever</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// ── Preview uploaded photo ────────────────────────────────────────────────
function previewUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Preview preset avatar ─────────────────────────────────────────────────
function previewPreset(src) {
    document.getElementById('preview').src = src;
    // Clear file input so it doesn't override the preset
    document.getElementById('profile_pic_input').value = '';
}

// ── Toggle avatar source tabs ─────────────────────────────────────────────
function showTab(tabId, btn) {
    document.getElementById('upload-tab').style.display = 'none';
    document.getElementById('preset-tab').style.display  = 'none';
    document.getElementById(tabId).style.display = 'block';

    // Update button styles
    btn.closest('.avatar-source-tabs').querySelectorAll('.btn').forEach(b => {
        b.className = b.className.replace('btn-dark', 'btn-outline-secondary');
    });
    btn.className = btn.className.replace('btn-outline-secondary', 'btn-dark');
}

// ── Bio character counter ─────────────────────────────────────────────────
function updateBioCount() {
    const bio = document.getElementById('bio-field');
    document.getElementById('bio-count').textContent = bio.value.length;
}
updateBioCount();

// ── Password strength meter ───────────────────────────────────────────────
function checkStrength(pw) {
    const bar   = document.getElementById('pw-strength-bar');
    const label = document.getElementById('pw-strength-label');
    let score = 0;
    if (pw.length >= 8)                     score++;
    if (/[A-Z]/.test(pw))                   score++;
    if (/[0-9]/.test(pw))                   score++;
    if (/[^A-Za-z0-9]/.test(pw))           score++;

    const levels = [
        { width: '25%', color: '#e74c3c', text: 'Weak' },
        { width: '50%', color: '#e67e22', text: 'Fair' },
        { width: '75%', color: '#f1c40f', text: 'Good' },
        { width: '100%',color: '#2ecc71', text: 'Strong' },
    ];
    const lvl = levels[score - 1] || { width: '0%', color: '', text: '' };
    bar.style.width      = lvl.width;
    bar.style.background = lvl.color;
    label.textContent    = lvl.text ? 'Strength: ' + lvl.text : '';
}

// ── Validate delete form ──────────────────────────────────────────────────
function validateDeleteForm() {
    const pw = document.getElementById('confirm-delete-pw').value.trim();
    if (!pw) {
        alert('Please enter your password to confirm deletion.');
        return false;
    }
    return confirm('This is permanent. Are you absolutely sure?');
}
</script>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>