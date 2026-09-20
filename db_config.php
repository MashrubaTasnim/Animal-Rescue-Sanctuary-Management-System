<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "moonlight_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Load system settings globally
$_SETTINGS = [];
$_s = $conn->query("SELECT setting_key, setting_val FROM system_settings");
if ($_s) {
    while ($row = $_s->fetch_assoc()) {
        $_SETTINGS[$row['setting_key']] = $row['setting_val'];
    }
}

// Helper function — use this anywhere
function setting($key, $default = '') {
    global $_SETTINGS;
    return $_SETTINGS[$key] ?? $default;
}

// Maintenance Mode Check (skip for admin + login pages)
$_maintenance_exempt = [
    'login.php', 'process_login.php', 'maintenance.php',
    'register.php', 'forgot_password.php', 'verify_otp.php', 'reset_password.php'
];

if (
    setting('maintenance_mode') === '1' &&
    (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') &&
    !in_array(basename($_SERVER['PHP_SELF']), $_maintenance_exempt)
) {
    include 'maintenance.php';
    exit();
}

// Load security rules (session timeout + password policy)
require_once __DIR__ . '/security.php';
?>