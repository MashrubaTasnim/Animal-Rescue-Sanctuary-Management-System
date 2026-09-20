<?php
session_start();
include 'db_config.php';

// Auth check — guests cannot initiate a payment
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['amount'])) {
    $user_id = $_SESSION['user_id'];
    $amount  = floatval($_POST['amount']);

    // Server-side amount validation
    if (!is_numeric($_POST['amount']) || $amount < 10) {
        die("<script>alert('Minimum donation amount is ৳10.'); window.history.back();</script>");
    }
    $tran_id = "PT_" . uniqid(); 

    // 1. Initial record (Pending)
    $stmt = $conn->prepare("INSERT INTO donations (user_id, method, amount, trx_id, status, created_at) VALUES (?, 'SSLCommerz', ?, ?, 'Pending', NOW())");
    $stmt->bind_param("ids", $user_id, $amount, $tran_id);
    $stmt->execute();

    // 2. SSLCommerz API Data
    $post_data = array();
    $post_data['store_id'] = "testbox"; 
    $post_data['store_passwd'] = "qwerty";
    $post_data['total_amount'] = $amount;
    $post_data['currency'] = "BDT";
    $post_data['tran_id'] = $tran_id;

    // 3. Absolute URLs - uid passed so session can be restored after redirect
    $base_url = "http://localhost/Moonlight_of_Heaven/";
    $post_data['success_url'] = $base_url . "payment_success.php?uid=" . $user_id;
    $post_data['fail_url']    = $base_url . "payment_fail.php?uid="    . $user_id;
    $post_data['cancel_url']  = $base_url . "payment_cancel.php?uid="  . $user_id;

    // 4. Customer Info
    $post_data['cus_name']  = $_SESSION['name']  ?? "Guardian";
    $post_data['cus_email'] = $_SESSION['email'] ?? "info@pranertan.com";
    $post_data['cus_phone'] = "01XXXXXXXXX";

    // 5. Connection
    $direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v4/api.php";

    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $direct_api_url);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    curl_setopt($handle, CURLOPT_POST, 1);
    curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

    $content = curl_exec($handle);
    $sslResponse = json_decode($content, true);

    if (isset($sslResponse['GatewayPageURL']) && $sslResponse['GatewayPageURL'] != "") {
        header("Location: " . $sslResponse['GatewayPageURL']);
        exit;
    } else {
        die("Gateway Error: " . ($sslResponse['failedreason'] ?? 'Connection Failed'));
    }
}