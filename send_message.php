<?php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';

// The sender comes from the session, never from the browser, so it cannot be spoofed.
$loggedIn = isset($_SESSION['user_id']);
$sender   = $loggedIn ? (string)$_SESSION['user_id'] : 'guest_' . substr(hash('sha256', session_id()), 0, 16);
$receiver = 'team_rescuer';

if ($message === '' || mb_strlen($message) > 1000) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or too long']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $sender, $receiver, $message);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
}
$stmt->close();
$conn->close();
