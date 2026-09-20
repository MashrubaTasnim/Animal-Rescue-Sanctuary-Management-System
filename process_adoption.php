<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ── HANDLE NEW ADOPTION REQUEST ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_adoption'])) {

    $animal_id = (int)$_POST['animal_id'];

    // ── Sanitise all form fields ──────────────────────────────────────────────
    $full_name        = $conn->real_escape_string(trim($_POST['full_name']        ?? ''));
    $phone            = $conn->real_escape_string(trim($_POST['phone']            ?? ''));
    $address          = $conn->real_escape_string(trim($_POST['address']          ?? ''));
    $housing_type     = $conn->real_escape_string(trim($_POST['housing_type']     ?? ''));
    $has_outdoor      = isset($_POST['has_outdoor'])    && $_POST['has_outdoor']    !== '' ? (int)$_POST['has_outdoor']    : null;
    $has_other_pets   = isset($_POST['has_other_pets']) && $_POST['has_other_pets'] !== '' ? (int)$_POST['has_other_pets'] : null;
    $other_pets_detail= $conn->real_escape_string(trim($_POST['other_pets_detail'] ?? ''));
    $has_children     = isset($_POST['has_children'])   && $_POST['has_children']   !== '' ? (int)$_POST['has_children']   : null;
    $children_ages    = $conn->real_escape_string(trim($_POST['children_ages']    ?? ''));
    $pet_experience   = $conn->real_escape_string(trim($_POST['pet_experience']   ?? ''));
    $adoption_reason  = $conn->real_escape_string(trim($_POST['adoption_reason']  ?? ''));
    $primary_caretaker= $conn->real_escape_string(trim($_POST['primary_caretaker']?? ''));
    $alone_hours      = isset($_POST['alone_hours']) && $_POST['alone_hours'] !== '' ? (int)$_POST['alone_hours'] : null;
    $agreed_terms     = isset($_POST['agreed_terms']) ? 1 : 0;

    // ── Basic validation ──────────────────────────────────────────────────────
    if (empty($full_name) || empty($phone) || empty($address) || empty($housing_type)
        || is_null($has_outdoor) || is_null($has_other_pets) || is_null($has_children)
        || empty($pet_experience) || empty($adoption_reason) || empty($primary_caretaker)
        || is_null($alone_hours) || !$agreed_terms) {
        $_SESSION['error'] = "Please fill in all required fields and agree to the adoption standards.";
        header("Location: animals.php");
        exit();
    }
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
    die("Invalid phone number.");
}

    // ── Prevent duplicate pending requests ────────────────────────────────────
    $check = $conn->query("SELECT id FROM adoption_requests
                           WHERE user_id = '$user_id' AND animal_id = '$animal_id'
                           AND status = 'pending'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "You already have a pending request for this animal.";
        header("Location: animals.php");
        exit();
    }

    // ── Handle nullable SQL values ────────────────────────────────────────────
    $outdoor_sql    = is_null($has_outdoor)    ? 'NULL' : $has_outdoor;
    $otherpets_sql  = is_null($has_other_pets) ? 'NULL' : $has_other_pets;
    $children_sql   = is_null($has_children)   ? 'NULL' : $has_children;
    $alone_sql      = is_null($alone_hours)    ? 'NULL' : $alone_hours;

    // Clear conditional fields if parent toggle is No
    if (!$has_other_pets) $other_pets_detail = '';
    if (!$has_children)   $children_ages     = '';

    // ── Insert adoption request with full application data ────────────────────
    $sql = "INSERT INTO adoption_requests
                (user_id, animal_id, status, request_date,
                 phone, address, housing_type, has_outdoor,
                 has_other_pets, other_pets_detail, has_children, children_ages,
                 pet_experience, adoption_reason, primary_caretaker, alone_hours, agreed_terms)
            VALUES
                ('$user_id', '$animal_id', 'pending', NOW(),
                 '$phone', '$address', '$housing_type', $outdoor_sql,
                 $otherpets_sql, '$other_pets_detail', $children_sql, '$children_ages',
                 '$pet_experience', '$adoption_reason', '$primary_caretaker', $alone_sql, '$agreed_terms')";

    if ($conn->query($sql)) {
        $_SESSION['success'] = "Your adoption application has been submitted! We will review it and get back to you shortly.";
    } else {
        $_SESSION['error'] = "Database error. Please try again.";
    }

    header("Location: animals.php");
    exit();
}

// ── HANDLE CANCEL REQUEST ─────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cancel'])) {

    $animal_id = (int)$_POST['cancel_animal_id'];

    // Only allow cancelling a 'pending' request
    $check = $conn->query("SELECT id FROM adoption_requests
                           WHERE user_id = '$user_id'
                           AND animal_id = '$animal_id'
                           AND status = 'pending'");

    if ($check->num_rows === 0) {
        $_SESSION['error'] = "This request cannot be cancelled (it may already be approved or rejected).";
        header("Location: animals.php");
        exit();
    }

    $delete = "DELETE FROM adoption_requests
               WHERE user_id = '$user_id'
               AND animal_id = '$animal_id'
               AND status = 'pending'";

    if ($conn->query($delete)) {
        // Do NOT touch animals.status — it was never changed on submit,
        // so there is nothing to restore. Other applicants may still be pending.
        $_SESSION['success'] = "Your adoption request has been cancelled.";
    } else {
        $_SESSION['error'] = "Could not cancel. Please try again.";
    }

    header("Location: animals.php");
    exit();
}

header("Location: animals.php");
exit();
?>