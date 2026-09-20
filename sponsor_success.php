<?php
// sponsor_success.php — SSLCommerz success callback for sponsorship
ini_set('session.cookie_samesite', 'Lax');
session_start();
include 'db_config.php';
require_once 'sslcommerz_helper.php';

// Nothing is trusted until SSLCommerz's validation API confirms the payment.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {

    $status  = $_POST['status'];
    $tran_id = trim($_POST['tran_id'] ?? '');
    $val_id  = trim($_POST['val_id']  ?? '');

    if (($status === 'VALID' || $status === 'AUTHENTICATED') && $tran_id !== '') {

        $stmt = $conn->prepare("SELECT id, user_id, monthly_amount, notes FROM resident_sponsorships WHERE payment_trx_id = ? LIMIT 1");
        $stmt->bind_param("s", $tran_id);
        $stmt->execute();
        $sp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $verified = $sp ? sslc_validate($val_id, $tran_id, (float)$sp['monthly_amount']) : null;

        if ($verified) {
            $method = substr($verified['card_type'] ?? ($_POST['card_type'] ?? 'SSLCommerz'), 0, 50);

            // Mark payment as received (status stays Pending: an admin must approve).
            // Skip if this payment was already recorded, so a refresh cannot spam the admin.
            if (strpos((string)$sp['notes'], 'Payment confirmed') !== 0) {
                $notes = 'Payment confirmed via SSLCommerz. Awaiting admin approval.';
                $upd = $conn->prepare("UPDATE resident_sponsorships SET payment_method = ?, status = 'Pending', notes = ? WHERE id = ?");
                $upd->bind_param("ssi", $method, $notes, $sp['id']);
                $upd->execute();
                $upd->close();

                // Notify admin via notice
                $conn->query("INSERT INTO notices (title, body, type, is_active)
                              VALUES (
                                  'New Sponsorship Payment Received',
                                  'A user has completed payment for a resident animal sponsorship. Please review and approve in Finance > Sponsorships.',
                                  'success', 1
                              )");
            }

            // The gateway redirect drops the login cookie: log the confirmed payer back in
            sslc_restore_session($conn, (int)$sp['user_id']);

            $_SESSION['success'] = '🌟 Thank you! Your sponsorship payment was successful. Our team will activate your sponsorship within 24 hours.';
            header('Location: animals.php');
            exit();
        }
    }

    // Payment failed or could not be confirmed
    $_SESSION['error'] = 'Payment was not successful. Please try again.';
    header('Location: animals.php');
    exit();
}

header('Location: animals.php');
exit();
