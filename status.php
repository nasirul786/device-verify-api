<?php
// status.php
header('Content-Type: application/json');

// Enable CORS for frontend requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';

$telegramId = isset($_GET['user']) ? trim($_GET['user']) : (isset($_GET['telegram_id']) ? trim($_GET['telegram_id']) : null);
$bot = isset($_GET['bot']) ? trim($_GET['bot']) : null;

if (!$telegramId || !$bot) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: user (telegram_id) and bot'
    ]);
    exit;
}

try {
    // 1. Fetch user
    $userStmt = $pdo->prepare("SELECT id, telegram_id, devices FROM users WHERE telegram_id = :telegram_id");
    $userStmt->execute(['telegram_id' => $telegramId]);
    $user = $userStmt->fetch();

    if (!$user) {
        echo json_encode([
            'success' => true,
            'verified' => false,
            'telegram_id' => $telegramId,
            'bot' => $bot,
            'devices' => [],
            'message' => 'User not found in system.'
        ]);
        exit;
    }

    $userId = $user['id'];
    $devices = [];
    if (!empty($user['devices'])) {
        $devices = array_filter(array_map('trim', explode(',', $user['devices'])));
    }

    // 2. Check verification status
    $verifyStmt = $pdo->prepare("SELECT id, verified_at FROM verifications WHERE user_id = :user_id AND bot = :bot");
    $verifyStmt->execute([
        'user_id' => $userId,
        'bot' => $bot
    ]);
    $verification = $verifyStmt->fetch();

    if ($verification) {
        echo json_encode([
            'success' => true,
            'verified' => true,
            'telegram_id' => $telegramId,
            'bot' => $bot,
            'devices' => $devices,
            'verified_at' => $verification['verified_at']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'verified' => false,
            'telegram_id' => $telegramId,
            'bot' => $bot,
            'devices' => $devices
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}
