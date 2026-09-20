<?php
// sponsor_success.php — SSLCommerz success callback for sponsorship
ini_set('session.cookie_samesite', 'Lax');
session_start();
include 'db_config.php';

// Restore session if SSLCommerz killed it
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {

    $status  = $_POST['status'];
    $tran_id = $_POST['tran_id']   ?? '';
    $method  = $_POST['card_type'] ?? 'SSLCommerz';

    if ($status === 'VALID' || $status === 'AUTHENTICATED') {

        // Update sponsorship: Pending → stays Pending (admin must approve)
        // But mark payment as received
        $tran_escaped = $conn->real_escape_string($tran_id);
        $conn->query("UPDATE resident_sponsorships
              SET payment_method = '$method',
                  status = 'Pending',
                  notes = 'Payment confirmed via SSLCommerz. Awaiting admin approval.'
              WHERE payment_trx_id = '$tran_escaped'");

        // Notify admin via notice
        $conn->query("INSERT INTO notices (title, body, type, is_active)
                      VALUES (
                          'New Sponsorship Payment Received',
                          'A user has completed payment for a resident animal sponsorship. Please review and approve in Finance > Sponsorships.',
                          'success', 1
                      )");

        $_SESSION['success'] = '🌟 Thank you! Your sponsorship payment was successful. Our team will activate your sponsorship within 24 hours.';
        header('Location: animals.php');
        exit();

    } else {
        // Payment failed — revert sponsorship to allow retry
        $tran_escaped = $conn->real_escape_string($tran_id);
        $conn->query("DELETE FROM resident_sponsorships WHERE payment_trx_id='$tran_escaped' AND status='Pending'");
        $_SESSION['error'] = 'Payment was not successful. Please try again.';
        header('Location: animals.php');
        exit();
    }

} else {
    header('Location: animals.php');
    exit();
}