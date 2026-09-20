<?php
// auth_helper.php
// Call require_login() on pages or actions that need authentication

function require_login($message = 'Please login or register to continue.') {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['auth_redirect_message'] = $message;
        $_SESSION['auth_redirect_back'] = $_SERVER['REQUEST_URI']; // remembers where they came from
        header('Location: login.php');
        exit;
    }
}