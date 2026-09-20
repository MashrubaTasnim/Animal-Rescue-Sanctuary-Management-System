<?php
// sponsor_cancel.php — SSLCommerz cancel callback
ini_set('session.cookie_samesite', 'Lax');
session_start();
include 'db_config.php';

// Restore session
if (!isset($_SESSION['user_id']) && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    $res = $conn->query("SELECT id, full_name, email, role FROM users WHERE id=$uid");
    if ($row = $res->fetch_assoc()) {
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['full_name']= $row['full_name'];
        $_SESSION['email']    = $row['email'];
        $_SESSION['role']     = $row['role'];
    }
}

// Remove the pending sponsorship so user can try again
if (!empty($_GET['trx'])) {
    $trx = $conn->real_escape_string($_GET['trx']);
    $conn->query("DELETE FROM resident_sponsorships WHERE payment_trx_id='$trx' AND status='Pending'");
}

$_SESSION['error'] = 'Sponsorship payment was cancelled.';
header('Location: animals.php');
exit();