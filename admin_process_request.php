<?php
session_start();
include 'db_config.php';

// 1. Security Check: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $animal_id = mysqli_real_escape_string($conn, $_POST['animal_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        // Start a transaction to ensure both updates happen together
        $conn->begin_transaction();

        try {
            // Update the adoption request status
            $update_request = "UPDATE adoption_requests SET status = 'approved' WHERE id = '$request_id'";
            $conn->query($update_request);

            // Update the animal status to 'Adopted'
            $update_animal = "UPDATE animals SET status = 'Adopted' WHERE id = '$animal_id'";
            $conn->query($update_animal);

            // If everything is fine, commit the changes
            $conn->commit();
            $_SESSION['success'] = "Application approved! The resident is now marked as Adopted.";

        } catch (Exception $e) {
            // If there's an error, rollback changes
            $conn->rollback();
            $_SESSION['error'] = "Critical Error: Could not process approval.";
        }

    } elseif ($action === 'reject') {
        // Simply update the request status to rejected
        $reject_query = "UPDATE adoption_requests SET status = 'rejected' WHERE id = '$request_id'";
        
        if ($conn->query($reject_query)) {
            $_SESSION['success'] = "Adoption request has been rejected.";
        } else {
            $_SESSION['error'] = "Database error during rejection.";
        }
    }

    // Redirect back to the management oversight page
    header("Location: manage_adoptions.php");
    exit();

} else {
    // Redirect if accessed directly without POST
    header("Location: manage_adoptions.php");
    exit();
}
?>