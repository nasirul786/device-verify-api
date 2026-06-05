<?php
// webhook_register.php
header('Content-Type: application/json');

// Enable CORS for frontend/API requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';

// Retrieve post data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$telegramId = isset($input['user']) ? trim($input['user']) : (isset($input['telegram_id']) ? trim($input['telegram_id']) : null);
$bot = isset($input['bot']) ? trim($input['bot']) : null;
$webhookUrl = isset($input['webhook']) ? trim($input['webhook']) : null;

if (!$telegramId || !$bot || !$webhookUrl) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: user (telegram_id), bot, and webhook (URL)'
    ]);
    exit;
}

if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid webhook URL format'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO webhooks (telegram_id, bot, url) 
        VALUES (:telegram_id, :bot, :url)
        ON DUPLICATE KEY UPDATE url = :url_update
    ");
    $stmt->execute([
        'telegram_id' => $telegramId,
        'bot' => $bot,
        'url' => $webhookUrl,
        'url_update' => $webhookUrl
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Webhook URL successfully registered/updated for user on bot.',
        'data' => [
            'user' => $telegramId,
            'bot' => $bot,
            'webhook' => $webhookUrl
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Could not save webhook URL: ' . $e->getMessage()
    ]);
}
