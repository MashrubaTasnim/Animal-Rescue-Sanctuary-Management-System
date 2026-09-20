<?php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// Team chat is private: each visitor only ever sees their own conversation.
// Logged-in users are identified by their account, guests by their browser session.
$loggedIn = isset($_SESSION['user_id']);
$me       = $loggedIn ? (string)$_SESSION['user_id'] : 'guest_' . substr(hash('sha256', session_id()), 0, 16);
$widgetId = $loggedIn ? (string)$_SESSION['user_id'] : 'guest';   // what chat_widget.php compares against

$stmt = $conn->prepare("SELECT id, sender_id, receiver_id, message, created_at
                        FROM messages
                        WHERE sender_id = ? OR receiver_id = ?
                        ORDER BY id ASC LIMIT 500");
$stmt->bind_param("ss", $me, $me);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    if ($row['sender_id'] === $me) {
        $row['sender_id'] = $widgetId;
    }
    // The widget inserts messages as HTML, so escape them here
    $row['message'] = htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8');
    $messages[] = $row;
}

echo json_encode($messages);
$stmt->close();
$conn->close();
