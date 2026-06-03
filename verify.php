<?php
// verify.php
header('Content-Type: application/json');

// Enable CORS for frontend requests
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

$telegramId = isset($input['telegram_id']) ? trim($input['telegram_id']) : null;
$bot = isset($input['bot']) ? trim($input['bot']) : null;
$fingerprint = isset($input['fingerprint']) ? trim($input['fingerprint']) : null;
$cfToken = isset($input['cf-turnstile-response']) ? trim($input['cf-turnstile-response']) : null;

if (!$telegramId || !$bot || !$fingerprint || !$cfToken) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: telegram_id, bot, fingerprint, and cf-turnstile-response'
    ]);
    exit;
}

// 1. Verify Cloudflare Turnstile token
$cfSecret = getenv('CF_SECRET_KEY');
if (!$cfSecret) {
    echo json_encode([
        'success' => false,
        'error' => 'Server configuration error: Cloudflare Turnstile secret key not set'
    ]);
    exit;
}

$verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
$ip = $_SERVER['REMOTE_ADDR'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret' => $cfSecret,
    'response' => $cfToken,
    'remoteip' => $ip
]));

$response = curl_exec($ch);
$curlErr = curl_error($ch);

if ($curlErr) {
    echo json_encode([
        'success' => false,
        'error' => 'Captcha verification connection failed: ' . $curlErr
    ]);
    exit;
}

$result = json_decode($response, true);
if (!$result || !isset($result['success']) || !$result['success']) {
    echo json_encode([
        'success' => false,
        'error' => 'Captcha verification failed'
    ]);
    exit;
}

// 2. Fetch or create user record
try {
    $pdo->beginTransaction();

    // Look for user by telegram_id
    $stmt = $pdo->prepare("SELECT id, devices FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute(['telegram_id' => $telegramId]);
    $user = $stmt->fetch();

    $userId = null;
    $userDevices = [];

    if ($user) {
        $userId = $user['id'];
        if (!empty($user['devices'])) {
            $userDevices = array_filter(array_map('trim', explode(',', $user['devices'])));
        }

        // If the current fingerprint is not registered for this user, append it
        if (!in_array($fingerprint, $userDevices)) {
            $userDevices[] = $fingerprint;
            $updatedDevicesStr = implode(',', $userDevices);
            
            $updateStmt = $pdo->prepare("UPDATE users SET devices = :devices WHERE id = :id");
            $updateStmt->execute([
                'devices' => $updatedDevicesStr,
                'id' => $userId
            ]);
        }
    } else {
        // Create new user record
        $insertStmt = $pdo->prepare("INSERT INTO users (telegram_id, devices) VALUES (:telegram_id, :devices)");
        $insertStmt->execute([
            'telegram_id' => $telegramId,
            'devices' => $fingerprint
        ]);
        $userId = $pdo->lastInsertId();
        $userDevices = [$fingerprint];
    }

    // 3. Multi-account detection: check if ANY other user with the SAME fingerprint is verified on THIS bot
    // Fetch all other verifications on this bot and check their devices
    $checkStmt = $pdo->prepare("
        SELECT u.id, u.telegram_id, u.devices 
        FROM verifications v
        JOIN users u ON v.user_id = u.id
        WHERE v.bot = :bot AND u.id != :current_user_id
    ");
    $checkStmt->execute([
        'bot' => $bot,
        'current_user_id' => $userId
    ]);
    $existingVerifications = $checkStmt->fetchAll();

    $isDuplicate = false;
    $duplicateTelegramId = null;

    foreach ($existingVerifications as $row) {
        if (!empty($row['devices'])) {
            $devicesList = array_filter(array_map('trim', explode(',', $row['devices'])));
            if (in_array($fingerprint, $devicesList)) {
                $isDuplicate = true;
                $duplicateTelegramId = $row['telegram_id'];
                break;
            }
        }
    }

    if ($isDuplicate) {
        // Verification rejects (but user's telegram_id is saved & devices connected, which we already did!)
        $pdo->commit();
        echo json_encode([
            'success' => false,
            'error' => 'Verification rejected: This device is already associated with another account (' . $duplicateTelegramId . ') verified on this bot.'
        ]);
        exit;
    }

    // 4. Record/check verification
    $verifyCheck = $pdo->prepare("SELECT id FROM verifications WHERE user_id = :user_id AND bot = :bot");
    $verifyCheck->execute(['user_id' => $userId, 'bot' => $bot]);
    
    if (!$verifyCheck->fetch()) {
        $insertVerify = $pdo->prepare("INSERT INTO verifications (user_id, bot) VALUES (:user_id, :bot)");
        $insertVerify->execute(['user_id' => $userId, 'bot' => $bot]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Verification successful!',
        'user_id' => $userId,
        'telegram_id' => $telegramId,
        'bot' => $bot,
        'devices' => $userDevices
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during verification: ' . $e->getMessage()
    ]);
}
