<?php
session_start();
include 'db_config.php';

// 1. SECURITY CHECK: Only Admins can process events
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    
    $image_path = null;

    // 2. IMAGE HANDLING
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "uploads/events/";
        
        // Create folder if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES["event_image"]["name"]);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["event_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    // 3. DATABASE INSERTION
    $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, location, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $title, $description, $event_date, $location, $image_path);

    if ($stmt->execute()) {
        // Redirect back to Admin HQ with success message
        header("Location: admin.php?event=success");
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>