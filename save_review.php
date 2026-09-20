<?php
session_start();
include 'db_config.php';

// Block guests
if (!isset($_SESSION['user_id'])) {
    echo 'login_required';
    exit;
}

$user_id = intval($_SESSION['user_id']);
$name    = mysqli_real_escape_string($conn, trim($_POST['name']));
$role    = mysqli_real_escape_string($conn, trim($_POST['role']));
$message = mysqli_real_escape_string($conn, trim($_POST['message']));
$rating  = intval($_POST['rating']);

if (!$name || !$message || $rating < 1 || $rating > 5) {
    echo 'invalid';
    exit;
}

// Prevent duplicate review by same user (optional but recommended)
$check = mysqli_query($conn, "SELECT id FROM testimonials WHERE user_id = $user_id LIMIT 1");
if (mysqli_num_rows($check) > 0) {
    echo 'already_reviewed';
    exit;
}

$sql = "INSERT INTO testimonials (user_id, user_name, user_role, message, rating, status) 
        VALUES ($user_id, '$name', '$role', '$message', $rating, 'pending')";

echo mysqli_query($conn, $sql) ? 'success' : 'db_error';