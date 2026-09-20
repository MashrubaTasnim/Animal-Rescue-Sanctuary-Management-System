<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Access Denied");
}

if (isset($_POST['approve_adoption'])) {
    $req_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $animal_id = mysqli_real_escape_string($conn, $_POST['animal_id']);
    $msg = mysqli_real_escape_string($conn, $_POST['admin_message']);

    // 1. Approve this specific request
    $conn->query("UPDATE adoption_requests SET status = 'approved', admin_response = '$msg' WHERE id = '$req_id'");

    // 2. WORKFLOW: Update animal status to 'adopted' so it vanishes from animals.php
    $conn->query("UPDATE animals SET status = 'adopted' WHERE id = '$animal_id'");

    // 3. Automatically reject other pending applicants for the same animal
    $conn->query("UPDATE adoption_requests SET status = 'rejected', admin_response = 'Already adopted' WHERE animal_id = '$animal_id' AND status = 'pending'");

    header("Location: admin_requests.php?msg=Approved");
}

if (isset($_POST['reject_adoption'])) {
    $req_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $msg = mysqli_real_escape_string($conn, $_POST['admin_message']);

    $conn->query("UPDATE adoption_requests SET status = 'rejected', admin_response = '$msg' WHERE id = '$req_id'");
    header("Location: admin_requests.php?msg=Rejected");
}
?>