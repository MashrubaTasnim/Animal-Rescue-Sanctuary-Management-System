<?php
// chat_handler.php
session_start();
include 'db_config.php'; // Loads setting() helper

header('Content-Type: application/json');

// Check if chatbot is enabled from Admin Settings
if (setting('chatbot_enabled', '1') !== '1') {
    echo json_encode(['error' => setting('chatbot_fallback_message', 'The chatbot is currently unavailable.')]);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
$userMsg   = trim($inputData['message'] ?? '');

if (empty($userMsg)) {
    echo json_encode(['error' => 'No message received']);
    exit;
}

// Pull API key from settings, fallback to hardcoded for safety
$apiKey = setting('groq_api_key', '');
if (empty($apiKey)) {
    echo json_encode(['error' => setting('chatbot_fallback_message', 'AI service is not configured yet.')]);
    exit;
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
    "model"    => "llama-3.1-8b-instant",
    "messages" => [
        [
            "role"    => "system",
            "content" => $systemPrompt
        ],
        [
            "role"    => "user",
            "content" => $userMsg
        ]
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
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Connection error: ' . $err]);
} else {
    $decoded = json_decode($response, true);
    if (isset($decoded['choices'][0]['message']['content'])) {
        echo json_encode(['reply' => $decoded['choices'][0]['message']['content']]);
    } else {
        // Return fallback message if unexpected response
        echo json_encode(['error' => setting('chatbot_fallback_message', 'Sorry, I could not process your request.')]);
    }
}
?>