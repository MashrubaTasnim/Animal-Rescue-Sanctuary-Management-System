<?php
session_start();
include 'db_config.php';

// ১. সিকিউরিটি চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if (isset($_POST['submit_final'])) {
    $rescue_id = intval($_POST['rescue_id']);
    
    // ডাটা রিসিভ (Breed, Age, Gender সহ)
    $animal_name = mysqli_real_escape_with_str($conn, $_POST['animal_name']);
    $species     = mysqli_real_escape_with_str($conn, $_POST['species']);
    $breed       = mysqli_real_escape_with_str($conn, $_POST['breed']);
    $gender      = mysqli_real_escape_with_str($conn, $_POST['gender']);
    $age         = mysqli_real_escape_with_str($conn, $_POST['age']);
    
    $vaccination       = intval($_POST['vaccination']); 
    $spay_neuter       = intval($_POST['spay_neuter']);
    $vet_notes         = mysqli_real_escape_with_str($conn, $_POST['vet_notes']);
    // Whitelist status — never trust raw POST for DB values
    $allowed_statuses  = ['Available for Adoption', 'Resident of Sanctuary'];
    $deployment_status = in_array($_POST['status'], $allowed_statuses)
                         ? $_POST['status']
                         : 'Available for Adoption'; // safe default

    // Image focus point — whitelist valid CSS object-position values
    $allowed_focus = ['center','top center','bottom center','center left','center right',
                      'top left','top right','bottom left','bottom right'];
    $image_focus   = in_array($_POST['image_focus'] ?? 'center', $allowed_focus)
                     ? $_POST['image_focus']
                     : 'center';

    // ── Separate medical_history from vet_notes ───────────
    $medical_history = !empty($vet_notes)
        ? $vet_notes
        : 'Enrolled from rescue #' . $rescue_id;
    // ─────────────────────────────────────────────────────

    // ২. ইমেজ হ্যান্ডলিং
    $final_image_path = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $target_dir = "uploads/animals/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext     = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "animal_" . time() . "_" . uniqid() . "." . $file_ext;
        $target_file  = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $final_image_path = $target_file;
        }
    }

    if (empty($final_image_path)) {
        $res = $conn->query("SELECT media_path FROM rescues WHERE id = $rescue_id");
        if ($res && $row = $res->fetch_assoc()) {
            $final_image_path = $row['media_path'];
        }
    }

    // ৩. ট্রানজ্যাকশন শুরু
    $conn->begin_transaction();

    try {
        // ৪. animals টেবিলে ইনসার্ট
        $sql = "INSERT INTO animals (
                    name, species, breed, gender, age, 
                    medical_history, image_path, status, 
                    is_vaccinated, is_spayed_neutered, 
                    medical_clearance_status, vet_notes, 
                    image_focus,
                    clearance_date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Cleared', ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("ssssssssiiis", 
            $animal_name, 
            $species, 
            $breed, 
            $gender, 
            $age, 
            $medical_history,   // ← medical_history (separate from vet_notes)
            $final_image_path, 
            $deployment_status, 
            $vaccination, 
            $spay_neuter, 
            $vet_notes,         // ← actual vet notes (blank if not entered)
            $image_focus        // ← CSS object-position value for card display
        );
        
        $stmt->execute();

        // ৫. rescues টেবিলের স্ট্যাটাস 'Resolved' করা
        $update_stmt = $conn->prepare("UPDATE rescues SET status = 'Resolved' WHERE id = ?");
        $update_stmt->bind_param("i", $rescue_id);
        $update_stmt->execute();

        $conn->commit();
        header("Location: animals.php?success=finalized");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        die("System Error: " . $e->getMessage());
    }

    $conn->close();
}

// Helper function
function mysqli_real_escape_with_str($conn, $data) {
    if (empty($data)) return "";
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}
?>