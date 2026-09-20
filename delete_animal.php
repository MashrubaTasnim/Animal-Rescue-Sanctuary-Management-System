<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}

if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    
    // ১. প্রথমে এই প্রাণীর সাথে যুক্ত সব রিকোয়েস্ট ডিলিট করতে হবে (Foreign Key Error এড়াতে)
    $conn->query("DELETE FROM adoption_requests WHERE animal_id = '$id'");

    // ২. এবার মূল অ্যানিম্যাল রেকর্ডটি ডিলিট করা যাবে
    if ($conn->query("DELETE FROM animals WHERE id = '$id'")) {
        header("Location: manage_animals.php?success=deleted");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>