<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'rescuer' && $_SESSION['role'] !== 'admin')) {
    echo "unauthorized"; exit();
}

$rescuer_id  = (int) $_SESSION['user_id'];
$species     = $conn->real_escape_string(trim($_POST['species']     ?? ''));
$location    = $conn->real_escape_string(trim($_POST['location']    ?? ''));
$description = $conn->real_escape_string(trim($_POST['description'] ?? ''));

if (!$species || !$location || !$description) {
    echo "missing_fields"; exit();
}

// ── Photo upload ──────────────────────────────────────────
$media_path = 'assets/no-image.jpg'; // fallback

if (!empty($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $mime    = mime_content_type($_FILES['photo']['tmp_name']);

    if (!in_array($mime, $allowed)) {
        echo "invalid_file_type"; exit();
    }
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        echo "file_too_large"; exit();
    }

    $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'SOS_' . time() . '_' . $rescuer_id . '.' . $ext;
    $dest     = 'uploads/rescues/' . $filename;

    if (!is_dir('uploads/rescues')) mkdir('uploads/rescues', 0755, true);

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        echo "upload_failed"; exit();
    }

    $media_path = $conn->real_escape_string($dest);
}
// ─────────────────────────────────────────────────────────

$sql = "INSERT INTO rescues 
            (user_id, species, location, description, status, assigned_rescuer, media_path, created_at)
        VALUES 
            ($rescuer_id, '$species', '$location', '$description', 'Under Review', $rescuer_id, '$media_path', NOW())";

if ($conn->query($sql)) {
    echo "success";
} else {
    echo "db_error: " . $conn->error;
}