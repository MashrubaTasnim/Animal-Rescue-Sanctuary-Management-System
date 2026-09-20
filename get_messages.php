<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "moonlight_db");

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

// আমরা সব মেসেজ নিয়ে আসছি, ফ্রন্টএন্ডের displayedIds সেটটি ডুপ্লিকেট ফিল্টার করবে
$sql = "SELECT id, sender_id, receiver_id, message, created_at FROM messages ORDER BY id ASC";
$result = $conn->query($sql);

$messages = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

echo json_encode($messages);
$conn->close();
?>