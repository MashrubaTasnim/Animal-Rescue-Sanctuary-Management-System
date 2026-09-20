<?php
// sponsor_cancel.php — SSLCommerz cancel callback
ini_set('session.cookie_samesite', 'Lax');
session_start();
include 'db_config.php';

// Remove the pending sponsorship so the user can try again.
// (Transaction ids are random, and only an unpaid Pending row is ever removed.)
if (!empty($_GET['trx'])) {
    $trx  = $_GET['trx'];
    $stmt = $conn->prepare("DELETE FROM resident_sponsorships
                            WHERE payment_trx_id = ? AND status = 'Pending'
                              AND (notes IS NULL OR notes NOT LIKE 'Payment confirmed%')");
    $stmt->bind_param("s", $trx);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['error'] = 'Sponsorship payment was cancelled.';
header('Location: animals.php');
exit();
