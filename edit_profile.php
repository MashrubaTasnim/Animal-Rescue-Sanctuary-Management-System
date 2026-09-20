<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Handle Image Upload
    if (!empty($_FILES['profile_image']['name'])) {
        $img_name = time() . '_' . $_FILES['profile_image']['name'];
        move_uploaded_file($_FILES['profile_image']['tmp_name'], 'uploads/' . $img_name);
        $conn->query("UPDATE users SET profile_image = '$img_name' WHERE id = $user_id");
    }

    $update = "UPDATE users SET full_name='$full_name', phone='$phone', email='$email' WHERE id=$user_id";
    
    if ($conn->query($update)) {
        header("Location: my_profile.php?update=success");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: #f6f8fb; padding-top: 50px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 card p-4 shadow-sm border-0" style="border-radius: 20px;">
                <h4 class="mb-4 fw-bold" style="color: #B8860B;">Update Your Information</h4>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3 text-center">
                        <label class="form-label d-block small fw-bold">Profile Picture</label>
                        <input type="file" name="profile_image" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark w-100">Save Changes</button>
                        <a href="my_profile.php" class="btn btn-outline-secondary w-100">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include 'chat_widget.php'; ?>
    <?php include 'footer.php'; ?>

</body>
</html>