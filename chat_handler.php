<?php
// chat_handler.php
session_start();
include 'db_config.php'; // Loads setting() helper

header('Content-Type: application/json');

// Set to true to see the real reason the chatbot fails, then set back to false.
$DEBUG = false;

function chat_fail($debug, $why) {
    global $DEBUG;
    $fallback = setting('chatbot_fallback_message', 'Sorry, I could not process your request.');
    echo json_encode(['error' => $DEBUG ? "DEBUG: $why" : $fallback]);
    exit;
}

// Check if chatbot is enabled from Admin Settings
if (setting('chatbot_enabled', '1') !== '1') {
    chat_fail($DEBUG, 'chatbot_enabled is OFF in System Settings.');
}

$inputData = json_decode(file_get_contents('php://input'), true);
$userMsg   = trim($inputData['message'] ?? '');

if (empty($userMsg)) {
    echo json_encode(['error' => 'No message received']);
    exit;
}

// API key comes only from Admin -> System Settings (never hardcode it here)
$apiKey = trim(setting('groq_api_key', ''));

if (empty($apiKey)) {
    chat_fail($DEBUG, 'groq_api_key is empty in the system_settings table.');
}

// Pull system prompt from settings or use default
$site_name    = setting('site_name', 'Heartbeat Heaven');
$systemPrompt = setting(
    'chatbot_system_prompt',
    "You are a helpful assistant for '{$site_name}', an animal welfare foundation in Bangladesh. Answer concisely."
);

// Groq API Endpoint
$url = "https://api.groq.com/openai/v1/chat/completions";

$payload = [
    "model"            => "openai/gpt-oss-20b",
    "reasoning_effort" => "low",
    "messages"         => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user",   "content" => $userMsg]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
$err      = curl_error($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Connection error: ' . $err]);
    exit;
}

$decoded = json_decode($response, true);

if (isset($decoded['choices'][0]['message']['content'])) {
    echo json_encode(['reply' => $decoded['choices'][0]['message']['content']]);
} else {
    $why = $decoded['error']['message'] ?? 'Unexpected response from Groq.';
    chat_fail($DEBUG, "Groq replied HTTP $code: $why");
}
?>
