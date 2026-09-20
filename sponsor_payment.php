<?php
// sponsor_payment.php — Initiates SSLCommerz payment for sponsorship
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['animal_id']) || empty($_POST['amount'])) {
    header('Location: animals.php'); exit();
}

$user_id    = intval($_SESSION['user_id']);
$animal_id  = intval($_POST['animal_id']);
$amount     = floatval($_POST['amount']);
$animal_name= htmlspecialchars($_POST['animal_name'] ?? 'Animal');

// Validate amount
if ($amount < 100) {
    $_SESSION['error'] = 'Minimum sponsorship amount is ৳100.';
    header('Location: animals.php'); exit();
}

// Check animal is still a resident
$check = $conn->query("SELECT id, name FROM animals WHERE id=$animal_id AND status='Resident of Sanctuary'");
if (!$check || $check->num_rows === 0) {
    $_SESSION['error'] = 'This animal is no longer available for sponsorship.';
    header('Location: animals.php'); exit();
}

// Check user doesn't already have active/pending sponsorship
$dup = $conn->query("SELECT id FROM resident_sponsorships
                     WHERE user_id=$user_id AND animal_id=$animal_id
                     AND status IN ('Pending','Active') LIMIT 1");
if ($dup && $dup->num_rows > 0) {
    $_SESSION['error'] = 'You already have an active or pending sponsorship for this animal.';
    header('Location: animals.php'); exit();
}

// Generate transaction ID
$tran_id = "SP_" . uniqid();

// Store pending sponsorship
$conn->query("INSERT INTO resident_sponsorships
                (user_id, animal_id, monthly_amount, start_date, status, payment_trx_id, notes)
              VALUES
                ($user_id, $animal_id, $amount, CURDATE(), 'Pending', '$tran_id',
                 'Awaiting payment confirmation via SSLCommerz.')");

// Get user info
$u    = $conn->query("SELECT full_name, email, phone FROM users WHERE id=$user_id")->fetch_assoc();
$name = $u['full_name'] ?? 'Sponsor';
$email= $u['email']     ?? 'info@pranertan.com';
$phone= $u['phone']     ?? '01XXXXXXXXX';

// SSLCommerz API
$base_url  = "http://localhost/Moonlight_of_Heaven/";
$post_data = [
    'store_id'     => 'testbox',
    'store_passwd' => 'qwerty',
    'total_amount' => $amount,
    'currency'     => 'BDT',
    'tran_id'      => $tran_id,
    'success_url'  => $base_url . 'sponsor_success.php?uid=' . $user_id,
    'fail_url'     => $base_url . 'sponsor_fail.php?uid='    . $user_id . '&trx=' . $tran_id,
    'cancel_url'   => $base_url . 'sponsor_cancel.php?uid='  . $user_id . '&trx=' . $tran_id,
    'cus_name'     => $name,
    'cus_email'    => $email,
    'cus_phone'    => $phone,
    'product_name' => 'Monthly Sponsorship: ' . $animal_name,
    'product_category' => 'Animal Sponsorship',
    'product_profile'  => 'general',
    'ship_name'    => $name,
    'ship_add1'    => 'Dhaka',
    'ship_city'    => 'Dhaka',
    'ship_country' => 'Bangladesh',
    'ship_postcode'=> '1230',
];

$handle  = curl_init();
curl_setopt($handle, CURLOPT_URL, 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php');
curl_setopt($handle, CURLOPT_TIMEOUT, 30);
curl_setopt($handle, CURLOPT_POST, 1);
curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

$content     = curl_exec($handle);
$sslResponse = json_decode($content, true);
curl_close($handle);

if (isset($sslResponse['GatewayPageURL']) && $sslResponse['GatewayPageURL'] !== '') {
    header('Location: ' . $sslResponse['GatewayPageURL']);
    exit();
} else {
    // Payment gateway failed — remove pending record
    $conn->query("DELETE FROM resident_sponsorships WHERE payment_trx_id='$tran_id'");
    $_SESSION['error'] = 'Payment gateway error. Please try again.';
    header('Location: animals.php'); exit();
}