<?php
$excluded = ['login.php','register.php','forgot_password.php','verify_otp.php','reset_password.php'];
$current  = basename($_SERVER['PHP_SELF']);

if (isset($_SESSION['user_id']) && !in_array($current, $excluded)) {
    $timeout = (int) setting('session_timeout_minutes', '30') * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function validate_password(string $password): array {
    $errors = [];
    $min = (int) setting('password_min_length', '8');
    if (strlen($password) < $min)
        $errors[] = "Password must be at least $min characters.";
    if (setting('pw_require_upper', '1') === '1' && !preg_match('/[A-Z]/', $password))
        $errors[] = "Must contain an uppercase letter.";
    if (setting('pw_require_number', '1') === '1' && !preg_match('/[0-9]/', $password))
        $errors[] = "Must contain a number.";
    if (setting('pw_require_special', '0') === '1' && !preg_match('/[\W_]/', $password))
        $errors[] = "Must contain a special character.";
    return ['valid' => empty($errors), 'errors' => $errors];
}