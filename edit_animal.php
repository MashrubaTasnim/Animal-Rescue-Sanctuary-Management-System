<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- 1. Fetching existing animal data for pre-filling the form ---
if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $result = $conn->query("SELECT * FROM animals WHERE id = '$id'");
    $animal = $result->fetch_assoc();

    if (!$animal) {
        die("Asset not found!");
    }
}

// --- 2. Handling form submission for updates ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $species = $conn->real_escape_string($_POST['species']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // if no new image is uploaded, keep the old one. Otherwise, upload the new image and update the path)
    $image_path = $animal['image_path']; // আগের ইমেজটি ডিফল্ট হিসেবে রাখা
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/";
        $image_path = $target_dir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);
    }

    $update_query = "UPDATE animals SET 
                    name = '$name', 
                    species = '$species', 
                    status = '$status', 
                    image_path = '$image_path' 
                    WHERE id = '$id'";

    if ($conn->query($update_query)) {
        header("Location: manage_animals.php?update=success");
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Asset | Moonlight of Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
        .edit-card { border-radius: 24px; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .preview-img { width: 120px; height: 120px; object-fit: cover; border-radius: 15px; border: 3px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-update { background: #0a1329; color: #B8860B; border: none; font-weight: 800; border-radius: 12px; padding: 12px; }
        .btn-update:hover { background: #14213d; color: #fff; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card edit-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold m-0 text-navy">Edit Asset Information</h4>
                    <a href="manage_animals.php" class="btn btn-light btn-sm rounded-pill px-3">Back</a>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error; ?></div>
                <?php endif; ?>

                <form action="edit_animal.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $animal['id']; ?>">

                    <div class="text-center mb-4">
                        <img src="<?= $animal['image_path']; ?>" class="preview-img mb-2" id="imgPreview">
                        <p class="small text-muted">Current Identity Image</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Asset Name</label>
                        <input type="text" name="name" class="form-control rounded-3" value="<?= $animal['name']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Species</label>
                        <input type="text" name="species" class="form-control rounded-3" value="<?= $animal['species']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Current Status</label>
                        <select name="status" class="form-select rounded-3">
                            <option value="Available for Adoption" <?= ($animal['status'] == 'Available for Adoption') ? 'selected' : ''; ?>>Available for Adoption</option>
                            <option value="Resident of Sanctuary" <?= ($animal['status'] == 'Resident of Sanctuary') ? 'selected' : ''; ?>>Resident of Sanctuary</option>
                            <option value="Pending Confirmation" <?= ($animal['status'] == 'Pending Confirmation') ? 'selected' : ''; ?>>Pending Confirmation</option>
                            <option value="Adopted" <?= ($animal['status'] == 'Adopted') ? 'selected' : ''; ?>>Adopted</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase">Change Photo (Optional)</label>
                        <input type="file" name="image" class="form-control rounded-3" onchange="previewFile()">
                    </div>

                    <button type="submit" class="btn btn-update w-100 text-uppercase">Update Asset Record</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewFile() {
    const preview = document.getElementById('imgPreview');
    const file = document.querySelector('input[type=file]').files[0];
    const reader = new FileReader();

    reader.addEventListener("load", function () {
        preview.src = reader.result;
    }, false);

    if (file) {
        reader.readAsDataURL(file);
    }
}
</script>

</body>
</html>