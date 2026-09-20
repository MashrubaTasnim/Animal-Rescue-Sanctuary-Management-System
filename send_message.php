<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "moonlight_db");

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// ফ্রন্টএন্ড থেকে আসা ডাইনামিক আইডি ধরবে
$sender   = $input['sender_id'] ?? null;
$receiver = $input['receiver_id'] ?? 'team_rescuer';
$message  = isset($input['message']) ? trim($input['message']) : '';

if ($sender && !empty($message)) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $sender, $receiver, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
}

$conn->close();
?>