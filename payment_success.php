<?php
ini_set('session.cookie_samesite', 'Lax'); 
session_start();
include 'db_config.php';

// Restore session if SSLCommerz killed it
if (!isset($_SESSION['user_id']) && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    $res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE id = $uid");
    if ($row = mysqli_fetch_assoc($res)) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['name']    = $row['full_name'];
        $_SESSION['email']   = $row['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status'])) {
    
    $status          = $_POST['status'];
    $tran_id         = $_POST['tran_id'];
    $amount_paid     = $_POST['amount'];
    $specific_method = $_POST['card_type'];

    if ($status == 'VALID' || $status == 'AUTHENTICATED') {
        
        // 1. Update the database
        $update_stmt = $conn->prepare("UPDATE donations SET status = 'Approved', method = ? WHERE trx_id = ?");
        $update_stmt->bind_param("ss", $specific_method, $tran_id);
        $update_stmt->execute();

        // ── AUTO-LOG TO FUNDING INCOME ────────────────────────
        $don = $conn->query("SELECT id FROM donations WHERE trx_id = '" . $conn->real_escape_string($tran_id) . "' LIMIT 1");
        if ($don && $row = $don->fetch_assoc()) {
            require_once 'finance_auto_hooks.php';
            autoSyncDonation($conn, $row['id']);
        }
        // ─────────────────────────────────────────────────────

        // ── GENERATE RECEIPT PDF ──────────────────────────────
        $receipt_path = null;
        try {
            require_once 'generate_receipt.php';
            $receipt_path = generateDonationReceipt($conn, $tran_id);

            if ($receipt_path) {
                // Save the receipt path to the donations table
                // (Add column if missing: ALTER TABLE donations ADD COLUMN receipt_path VARCHAR(255) NULL;)
                $rStmt = $conn->prepare("UPDATE donations SET receipt_path = ? WHERE trx_id = ?");
                $rStmt->bind_param("ss", $receipt_path, $tran_id);
                $rStmt->execute();
            }
        } catch (Throwable $e) {
            // Receipt failure should NOT block the success redirect
            error_log('Receipt generation failed for ' . $tran_id . ': ' . $e->getMessage());
        }
        // ─────────────────────────────────────────────────────

        // 2. Redirect to donate.php with success parameters
        //    Pass receipt flag so donate.php can show the download button
        $receipt_flag = $receipt_path ? '&receipt=1&trx=' . urlencode($tran_id) : '';
        header("Location: donate.php?payment=success&amt=" . $amount_paid . "&method=" . urlencode($specific_method) . $receipt_flag);
        exit();

    } else {
        header("Location: donate.php?payment=failed");
        exit();
    }

} else {
    header("Location: donate.php");
    exit();
}