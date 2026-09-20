<?php
session_start();
include 'db_config.php';
require_once 'auth_helper.php'; // Ensure this file has your $conn connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 1. Prepare the SQL statement - Added 'status' to the SELECT query
    $stmt = $conn->prepare("SELECT id, full_name, password, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 2. Verify the hashed password
        if (password_verify($password, $user['password'])) {
            
            // --- NEW LOGIC: Check if user is restricted ---
            if (isset($user['status']) && $user['status'] === 'restricted') {
                header("Location: login.php?error=restricted");
                exit();
            }
            // ----------------------------------------------

            // 3. Regenerate session ID for security
            session_regenerate_id(true);

            // 4. Store user data in Session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // 5. Updated Role-Based Redirection
            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } 
            elseif ($user['role'] === 'vet') {
                header("Location: vet_dashboard.php");
            } 
            elseif ($user['role'] === 'rescuer') {
                header("Location: rescuer.php");
            } 
            else {
    $redirect = $_SESSION['auth_redirect_back'] ?? 'user.php';
    unset($_SESSION['auth_redirect_back']);
    header("Location: " . $redirect);
}
            exit();

        } else {
            // Password incorrect - Updated to match your login.php check
            header("Location: login.php?error=invalid");
            exit();
        }
    } else {
        // User not found - Redirecting to invalid for security consistency
        header("Location: login.php?error=invalid");
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: login.php");
    exit();
}
?>