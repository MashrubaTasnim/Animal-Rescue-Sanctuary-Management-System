<?php
// Note: display_errors disabled for production
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_start();
include 'db_config.php';

/**
 * AI TRIAGE ENGINE — Groq powered
 * Falls back to keyword scoring if Groq is unavailable
 */
function getAISeverityScore($description) {

    // ============================================================
    // LAYER 1 — Keyword Safety Net (always runs as baseline)
    // ============================================================
    $scores = [1];

    $crit_5 = ['accident', 'hit by car', 'hit by truck', 'bleeding heavily', 'dying', 'unconscious', 'not breathing', 'seizure', 'paralyzed', 'crushed'];
    $crit_4 = ['maggot', 'fracture', 'deep wound', 'severe infection', 'attacked by dog', 'broken leg', 'broken bone', 'open wound', 'heavy bleeding', 'cannot walk'];
    $crit_3 = ['rash', 'skin disease', 'itching', 'hair loss', 'mange', 'fungal', 'scabies', 'limping', 'vomiting', 'diarrhea', 'swollen', 'breathing problem', 'breathing'];

    foreach ($crit_5 as $w) { if (stripos($description, $w) !== false) $scores[] = 5; }
    foreach ($crit_4 as $w) { if (stripos($description, $w) !== false) $scores[] = 4; }
    foreach ($crit_3 as $w) { if (stripos($description, $w) !== false) $scores[] = 3; }

    $keyword_score = max($scores);

    // ============================================================
    // LAYER 2 — Groq AI Brain
    // ============================================================
    if (setting('triage_enabled', '1') !== '1') {
        return $keyword_score;
    }

    $apiKey = setting('groq_api_key');
    if (empty($apiKey)) {
        return $keyword_score;
    }

    $prompt = "You are an emergency animal rescue triage assistant for Heartbeat Heaven, an animal rescue foundation in Bangladesh.

Analyze this rescue report and respond with ONLY a single integer from 1 to 5.

Scoring guide:
5 = Life-threatening emergency (hit by vehicle, heavy bleeding, unconscious, not breathing, seizure)
4 = Urgent (deep wounds, fractures, maggot infestation, severe infection, cannot move)
3 = Moderate (breathing problems, skin disease, limping, vomiting, mild wounds, visible pain)
2 = Non-urgent (stray with no visible injuries, mild discomfort)
1 = Minimal (healthy stray, no injuries)

Important: The report may be written in Bengali, English, or a mix of both. Understand it accordingly.

Rescue Report: \"$description\"

Reply with ONLY the number (1, 2, 3, 4, or 5). No explanation.";

    $payload = [
        "model"       => "llama-3.1-8b-instant",
        "messages"    => [
            ["role" => "system", "content" => "You are an animal emergency triage AI. You only respond with a single integer from 1 to 5."],
            ["role" => "user",   "content" => $prompt]
        ],
        "max_tokens"  => 5,
        "temperature" => 0
    ];

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If curl failed or non-200 response — fall back to keywords
    if ($err || $httpCode !== 200) {
        return $keyword_score;
    }

    $decoded  = json_decode($response, true);
    $ai_reply = trim($decoded['choices'][0]['message']['content'] ?? '');

    // Validate: must be a number between 1-5
    if (is_numeric($ai_reply) && (int)$ai_reply >= 1 && (int)$ai_reply <= 5) {
        // Take the higher of AI score and keyword score for safety
        return max((int)$ai_reply, $keyword_score);
    }

    return $keyword_score;
}

// ============================================================
// Form Processing
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_id         = (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ? $_SESSION['user_id'] : NULL;
    $raw_description = $_POST['description'];
    $species         = mysqli_real_escape_string($conn, $_POST['species']);
    $description     = mysqli_real_escape_string($conn, $_POST['description']);
    $location        = mysqli_real_escape_string($conn, $_POST['location']);
$contact_phone   = mysqli_real_escape_string($conn, $_POST['contact_phone']);
$contact_email   = mysqli_real_escape_string($conn, $_POST['contact_email'] ?? '');
    $status          = "Pending";
    $media_path      = "";

    // File upload
    if (isset($_FILES['rescue_media']) && $_FILES['rescue_media']['error'] == 0) {
        $target_dir = "uploads/rescues/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

        $file_ext    = strtolower(pathinfo($_FILES["rescue_media"]["name"], PATHINFO_EXTENSION));
        $file_name   = "SOS_" . time() . "_" . ($user_id ?? 'Guest') . "." . $file_ext;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["rescue_media"]["tmp_name"], $target_file)) {
            $media_path = $target_file;
        } else {
            die("<script>alert('Failed to upload file. Please check folder permissions.'); window.history.back();</script>");
        }
    } else {
        die("<script>alert('Error: Please upload a photo or video of the animal in need.'); window.history.back();</script>");
    }

    // Run AI Triage
    $severity_score = getAISeverityScore($raw_description);

    // Severity label for user feedback
    $severity_labels = [
        1 => 'Minimal',
        2 => 'Low',
        3 => 'Moderate',
        4 => 'High',
        5 => 'Critical'
    ];
    $severity_label = $severity_labels[$severity_score] ?? 'Unknown';

    // DB Insert
   $stmt = $conn->prepare("INSERT INTO rescues (user_id, species, location, description, media_path, status, severity_score, contact_phone, contact_email, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("isssssiss", $user_id, $species, $location, $description, $media_path, $status, $severity_score, $contact_phone, $contact_email);
    if ($stmt->execute()) {
        $redirect = ($user_id) ? "user.php" : "index.php";
        echo "<script>
            alert('SOS sent to Heartbeat Heaven HQ!\\nSeverity: $severity_label ($severity_score/5)\\nOur team will verify and respond shortly.');
            window.location.href='$redirect';
        </script>";
    } else {
        echo "Database Error: " . $stmt->error;
    }

    $stmt->close();
}
?>