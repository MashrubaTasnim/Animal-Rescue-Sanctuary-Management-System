<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$rescue_id = 0;
if (isset($_REQUEST['rescue_id'])) {
    $rescue_id = (int)$_REQUEST['rescue_id'];
}

$selected_clinic_name = "";
if (isset($_POST['clinic_name'])) {
    $selected_clinic_name = $_POST['clinic_name'];
}

$message = "";

if (isset($_POST['submit_report'])) {
    $user_id          = $_SESSION['user_id'];
    $current_id       = (int)$_POST['rescue_id'];
    $clinic_name_to_save = $_POST['clinic_name'];
    $condition        = $_POST['condition'];
    $status           = 'At Clinic';

    // ── Resolve clinic_id from clinic_name ──────────────────────────
    $resolved_clinic_id = null;
    $stmt_lookup = $conn->prepare("SELECT id FROM vet_clinics WHERE name = ? LIMIT 1");
    $stmt_lookup->bind_param("s", $clinic_name_to_save);
    $stmt_lookup->execute();
    $stmt_lookup->bind_result($found_id);
    if ($stmt_lookup->fetch()) {
        $resolved_clinic_id = $found_id;
    }
    $stmt_lookup->close();
    // ────────────────────────────────────────────────────────────────

    $target_dir = "uploads/rescue_updates/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

    $file_name   = time() . "_" . basename($_FILES["update_photo"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["update_photo"]["tmp_name"], $target_file)) {

        if ($current_id > 0) {
            // Update existing rescue — also stamp clinic_id now that we have it
            $sql  = "UPDATE rescues SET 
                        clinic_name = ?, 
                        clinic_id   = ?,
                        status      = ?, 
                        media_path  = ?, 
                        description = CONCAT(description, ' | Clinic Update: ', ?) 
                     WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisssi",
                $clinic_name_to_save,
                $resolved_clinic_id,
                $status,
                $target_file,
                $condition,
                $current_id
            );
        } else {
            // New direct report — save both clinic_name and clinic_id
            $species  = $_POST['species'];
            $location = "Reported from Clinic: " . $clinic_name_to_save;

            $sql  = "INSERT INTO rescues 
                        (user_id, species, location, description, media_path, status, clinic_name, clinic_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssi",
                $user_id,
                $species,
                $location,
                $condition,
                $target_file,
                $status,
                $clinic_name_to_save,
                $resolved_clinic_id
            );
        }

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success shadow-sm'>Reported successfully! Current Status: <strong>At Clinic</strong>. Admin will verify it shortly.</div>";
        } else {
            $message = "<div class='alert alert-danger shadow-sm'>Database Error: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger shadow-sm'>Photo upload failed.</div>";
    }
}

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Arrival | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Montserrat', sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 25px rgba(0,0,0,0.07); }
        .form-label { color: #2d3436; }
        .btn-success { background-color: #27ae60; border: none; transition: 0.3s; }
        .btn-success:hover { background-color: #219150; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <h4 class="text-center mb-4 fw-bold text-success">Confirm Arrival at Clinic</h4>
                <?= $message ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="rescue_id"   value="<?= $rescue_id ?>">
                    <input type="hidden" name="clinic_name" value="<?= htmlspecialchars($selected_clinic_name) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Clinic Name</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($selected_clinic_name) ?>" disabled>
                    </div>

                    <?php if ($rescue_id == 0): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Species</label>
                        <select name="species" class="form-select" required>
                            <option value="Dog">Dog</option>
                            <option value="Cat">Cat</option>
                            <option value="Bird">Bird</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Animal Condition</label>
                        <textarea name="condition" class="form-control" rows="3" required
                                  placeholder="Describe the health condition as observed at the clinic..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Clinic Arrival Photo</label>
                        <input type="file" name="update_photo" class="form-control" required>
                        <div class="form-text">Upload a photo of the animal at the clinic for verification.</div>
                    </div>

                    <button type="submit" name="submit_report" class="btn btn-success w-100 py-2 fw-bold">
                        SUBMIT CLINIC REPORT
                    </button>

                    <div class="text-center mt-3">
                        <a href="vets.php" class="text-decoration-none text-muted small">← Back to Clinic List</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>