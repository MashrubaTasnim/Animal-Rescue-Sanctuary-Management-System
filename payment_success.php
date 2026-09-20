<?php
ini_set('session.cookie_samesite', 'Lax');
session_start();
include 'db_config.php';
require_once 'sslcommerz_helper.php';

// SSLCommerz sends the donor back here with a POST. Nothing is recorded until
// SSLCommerz's own validation API confirms the payment (val_id + tran_id + amount).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {

    $status  = $_POST['status'];
    $tran_id = trim($_POST['tran_id'] ?? '');
    $val_id  = trim($_POST['val_id']  ?? '');

    if (($status === 'VALID' || $status === 'AUTHENTICATED') && $tran_id !== '') {

        $stmt = $conn->prepare("SELECT id, user_id, amount, status, receipt_path FROM donations WHERE trx_id = ? LIMIT 1");
        $stmt->bind_param("s", $tran_id);
        $stmt->execute();
        $don = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $verified = $don ? sslc_validate($val_id, $tran_id, (float)$don['amount']) : null;

        if ($verified) {
            $specific_method = substr($verified['card_type'] ?? ($_POST['card_type'] ?? 'SSLCommerz'), 0, 20);
            $amount_paid     = $don['amount'];
            $receipt_path    = $don['receipt_path'] ?: null;

            // Process each payment only once (a refresh or replay must not double-count it)
            if ($don['status'] !== 'Approved') {

                // 1. Update the database
                $update_stmt = $conn->prepare("UPDATE donations SET status = 'Approved', method = ? WHERE trx_id = ?");
                $update_stmt->bind_param("ss", $specific_method, $tran_id);
                $update_stmt->execute();

                // AUTO-LOG TO FUNDING INCOME
                require_once 'finance_auto_hooks.php';
                autoSyncDonation($conn, $don['id']);

                // GENERATE RECEIPT PDF
                try {
                    require_once 'generate_receipt.php';
                    $receipt_path = generateDonationReceipt($conn, $tran_id);

                    if ($receipt_path) {
                        $rStmt = $conn->prepare("UPDATE donations SET receipt_path = ? WHERE trx_id = ?");
                        $rStmt->bind_param("ss", $receipt_path, $tran_id);
                        $rStmt->execute();
                    }
                } catch (Throwable $e) {
                    // Receipt failure should NOT block the success redirect
                    error_log('Receipt generation failed for ' . $tran_id . ': ' . $e->getMessage());
                }
            }

            // The gateway redirect drops the login cookie: log the confirmed payer back in
            sslc_restore_session($conn, (int)$don['user_id']);

            $receipt_flag = $receipt_path ? '&receipt=1&trx=' . urlencode($tran_id) : '';
            header("Location: donate.php?payment=success&amt=" . urlencode($amount_paid) . "&method=" . urlencode($specific_method) . $receipt_flag);
            exit();
        }
    }

    header("Location: donate.php?payment=failed");
    exit();
}

header("Location: donate.php");
exit();
