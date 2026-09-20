<?php
session_start();
include 'db_config.php';

/**
 * Heartbeat Heaven - Action Engine
 * অথেন্টিকেশন ও মেথড চেক
 */
if (!isset($_SESSION['user_id'])) {
    die("unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $rescue_id  = intval($_POST['id']);
    $new_status = $_POST['status'];
    $user_id    = $_SESSION['user_id'];
    $user_role  = $_SESSION['role'];

    // ১. রেসকিউয়ার যখন কেস একসেপ্ট করে (Approved -> In Progress)
    if ($new_status === 'In Progress') {
        if ($user_role !== 'rescuer' && $user_role !== 'admin') die("unauthorized_role");

        $check = $conn->prepare("SELECT assigned_rescuer, status FROM rescues WHERE id = ?");
        $check->bind_param("i", $rescue_id);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();

        if ($res && $res['assigned_rescuer'] !== NULL) {
            die("Already assigned to another rescuer!");
        }

        if (!$res || $res['status'] !== 'Approved') {
            die("Only approved cases can be started.");
        }

        $stmt = $conn->prepare("UPDATE rescues SET status = ?, assigned_rescuer = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $user_id, $rescue_id);

    }
    // ২. রেসকিউয়ার কাজ শেষ করে রিভিউতে পাঠালে (In Progress -> Under Review)
    elseif ($new_status === 'Under Review') {
        if ($user_role !== 'rescuer' && $user_role !== 'admin') die("unauthorized_role");

        $stmt = $conn->prepare("UPDATE rescues SET status = ? WHERE id = ? AND assigned_rescuer = ?");
        $stmt->bind_param("sii", $new_status, $rescue_id, $user_id);

    }
    // ৩. অ্যাডমিন যখন ভেট এসাইন করে (Under Review -> Assigned to Vet)
    elseif ($new_status === 'Assigned to Vet') {
        if ($user_role !== 'admin') die("admin_only_action");

        if (!isset($_POST['vet_id'])) die("Missing Veterinarian ID");
        $vet_id = intval($_POST['vet_id']);

        $stmt = $conn->prepare("UPDATE rescues SET status = ?, assigned_vet = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $vet_id, $rescue_id);

    }
    // ৪. ভেট যখন ক্লিয়ার করে (Assigned to Vet -> Vet Cleared)
    elseif ($new_status === 'Vet Cleared') {
        if ($user_role !== 'vet' && $user_role !== 'admin') die("unauthorized_role");

        $stmt = $conn->prepare("UPDATE rescues SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $rescue_id);

    }
    // ৫. জেনারেল আপডেট (Approved, Rejected, Resident ইত্যাদি)
    else {
        if ($user_role !== 'admin') die("admin_only_action");

        $stmt = $conn->prepare("UPDATE rescues SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $rescue_id);
    }

    // এক্সিকিউশন ও রেসপন্স
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "success";
        } else {
            echo "No changes made. Case might be already updated.";
        }
    } else {
        echo "error: " . $conn->error;
    }

    $stmt->close();

    // ── AUTO-LOG RESCUE FIELD COST (after successful Under Review) ────────────
    if ($new_status === 'Under Review' && !empty($_POST['field_cost']) && floatval($_POST['field_cost']) > 0) {
        require_once 'finance_auto_hooks.php';
        autoLogRescueExpense(
            $conn,
            $rescue_id,
            floatval($_POST['field_cost']),
            !empty($_POST['cost_note']) ? $_POST['cost_note'] : 'Field rescue operation costs',
            $user_id
        );
    }
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * HEARTBEAT HEAVEN - Notification Trigger
     */
    require_once 'notifications.php';

    if ($new_status === 'Approved' || $new_status === 'Rejected') {
        notify_sos($rescue_id, $new_status);
    }

    if (isset($_POST['type']) && $_POST['type'] === 'adoption') {
        notify_adoption($rescue_id, $new_status);
    }

    $conn->close();
} else {
    echo "invalid_request";
}