<?php
ob_start(); // Trap accidental whitespace or errors
session_start();
include 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['event_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$event_id = (int)$_POST['event_id'];

// Check status
$check = $conn->query("SELECT * FROM event_interests WHERE event_id = $event_id AND user_id = $user_id");

if ($check->num_rows > 0) {
    $conn->query("DELETE FROM event_interests WHERE event_id = $event_id AND user_id = $user_id");
    $status = "unregistered";
} else {
    $conn->query("INSERT INTO event_interests (event_id, user_id) VALUES ($event_id, $user_id)");
    $status = "registered";
}

// Get the fresh count for the Heartbeat Heaven community badge
$count_query = $conn->query("SELECT COUNT(*) as total FROM event_interests WHERE event_id = $event_id");
$new_count = $count_query->fetch_assoc()['total'];

$response = [
    'status' => $status,
    'new_count' => (int)$new_count
];

ob_clean(); // Wipe any accidental output before sending JSON
echo json_encode($response);
exit;
?>