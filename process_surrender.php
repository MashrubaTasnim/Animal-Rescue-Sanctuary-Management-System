<?php
session_start();
include 'db_config.php';

// ── Auth guard ──────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ── Sanitise inputs ─────────────────────────────────────────
$species      = $conn->real_escape_string(trim($_POST['species']      ?? ''));
$breed        = $conn->real_escape_string(trim($_POST['breed']        ?? ''));
$age          = $conn->real_escape_string(trim($_POST['age']          ?? ''));
$gender       = $conn->real_escape_string(trim($_POST['gender']       ?? ''));
$is_vaccinated= isset($_POST['is_vaccinated']) && $_POST['is_vaccinated'] !== ''
                    ? (int)$_POST['is_vaccinated'] : null;
$is_spayed    = isset($_POST['is_spayed']) && $_POST['is_spayed'] !== ''
                    ? (int)$_POST['is_spayed'] : null;
$reason       = $conn->real_escape_string(trim($_POST['reason']       ?? ''));
$description  = $conn->real_escape_string(trim($_POST['description']  ?? ''));
$urgency      = $conn->real_escape_string(trim($_POST['urgency']      ?? ''));
$contact_time = $conn->real_escape_string(trim($_POST['contact_time'] ?? ''));
$phone        = $conn->real_escape_string(trim($_POST['phone']        ?? ''));

// ── Surrender contribution (pledged amount only — NOT logged to finance yet) ──
// This is just what the pet parent is *willing* to contribute on visit.
// Actual finance entry happens only after admin confirms payment received.
$contribution_pledged = isset($_POST['surrender_contribution'])
                        ? floatval($_POST['surrender_contribution'])
                        : 0.00;
$contribution_pledged = $contribution_pledged > 0 ? $contribution_pledged : 0.00;

// ── Basic validation ────────────────────────────────────────
if (empty($species) || empty($age) || empty($gender) || empty($reason) || empty($urgency)) {
    header("Location: user.php?surrender=error&msg=missing_fields");
    exit();
}

// ── Contribution validation ─────────────────────────────────
if ($contribution_pledged < 0) {
    header("Location: user.php?surrender=error&msg=invalid_contribution");
    exit();
}

// ── File upload ─────────────────────────────────────────────
$photo_path = '';

if (isset($_FILES['pet_photo']) && $_FILES['pet_photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/surrender/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type     = mime_content_type($_FILES['pet_photo']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        header("Location: user.php?surrender=error&msg=invalid_file");
        exit();
    }

    $max_size = 5 * 1024 * 1024; // 5 MB
    if ($_FILES['pet_photo']['size'] > $max_size) {
        header("Location: user.php?surrender=error&msg=file_too_large");
        exit();
    }

    $ext       = pathinfo($_FILES['pet_photo']['name'], PATHINFO_EXTENSION);
    $file_name = 'surrender_' . $user_id . '_' . time() . '.' . $ext;
    $dest      = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['pet_photo']['tmp_name'], $dest)) {
        $photo_path = $conn->real_escape_string($dest);
    }
} else {
    // Photo is required
    header("Location: user.php?surrender=error&msg=no_photo");
    exit();
}

// ── Handle nullable fields for SQL ─────────────────────────
$vax_sql          = is_null($is_vaccinated) ? "NULL" : (int)$is_vaccinated;
$spay_sql         = is_null($is_spayed)     ? "NULL" : (int)$is_spayed;
$phone_sql        = empty($phone)           ? "NULL" : "'$phone'";
$contribution_sql = $contribution_pledged > 0 ? $contribution_pledged : "NULL";

// ── Insert into DB ──────────────────────────────────────────
// contribution_pledged  = what pet parent said they'd bring on visit (informational)
// contribution_collected = NULL until admin marks payment received after visit
// contribution_status   = 'pending' if pledged, NULL if nothing pledged
$contrib_status_sql = $contribution_pledged > 0 ? "'pending'" : "NULL";

$sql = "INSERT INTO surrender_requests
        (user_id, species, breed, age, gender, is_vaccinated, is_spayed_neutered,
         reason, description, urgency, contact_time, phone,
         contribution_pledged, contribution_collected, contribution_status,
         photo_path, status, created_at)
        VALUES
        ('$user_id', '$species', '$breed', '$age', '$gender', $vax_sql, $spay_sql,
         '$reason', '$description', '$urgency', '$contact_time', $phone_sql,
         $contribution_sql, NULL, $contrib_status_sql,
         '$photo_path', 'Pending Review', NOW())";

if ($conn->query($sql)) {
    // ── No session-based contribution carry-over needed ────
    // Finance logging happens only when admin clicks "Collect Payment"
    // after the physical visit — see manage_animals.php

    header("Location: user.php?surrender=success");
} else {
    header("Location: user.php?surrender=error&msg=db_error");
}

$conn->close();
exit();
?>