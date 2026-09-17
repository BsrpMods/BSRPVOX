<?php
require_once __DIR__ . '/../server/config/db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_GET['webview2'])) {
    header("Content-Type: application/json");
} else {
    header("Content-Type: text/html");
}

$input = $_REQUEST;
if (empty($input) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
}

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$device_id = $input['device_id'] ?? '';
$device_name = $input['device_name'] ?? 'Unknown Device';

function sendResponse($data) {
    $out = json_encode($data);
    $out_js = json_encode($out);
    if (isset($_GET['webview2'])) {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>BSRP Vox - Processing</title>
    <style>
        body { margin: 0; padding: 0; background-color: #fafaf3; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #3f4a30; }
        .spinner { border: 4px solid rgba(63, 74, 48, 0.2); width: 40px; height: 40px; border-radius: 50%; border-left-color: #3f4a30; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .container { display: flex; flex-direction: column; align-items: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div style="font-weight: 600; font-size: 14px;">Memproses Login...</div>
    </div>
    <script>
        window.chrome.webview.postMessage($out_js);
    </script>
</body>
</html>
HTML;
        die($html);
    } else {
        die($out);
    }
}

if (empty($username) || empty($password) || empty($device_id)) {
    sendResponse(["success" => false, "error" => ["code" => "MISSING_DATA", "message" => "Please fill in all fields"]]);
}

try {
    // 1. Check User
    $stmt = $pdo->prepare("SELECT u.*, p.max_devices, p.name as plan_name FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        logAction($pdo, $username, $device_id, 'LOGIN_FAILED', 'INVALID_CREDENTIALS');
        sendResponse(["success" => false, "error" => ["code" => "INVALID_CREDENTIALS", "message" => "Invalid username or password"]]);
    }

    if ($user['status'] !== 'ACTIVE') {
        logAction($pdo, $username, $device_id, 'LOGIN_FAILED', 'ACCOUNT_SUSPENDED');
        sendResponse(["success" => false, "error" => ["code" => "ACCOUNT_SUSPENDED", "message" => "This account is suspended"]]);
    }

    // 2. Check Device
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? AND device_id = ?");
    $stmt->execute([$user['id'], $device_id]);
    $device = $stmt->fetch();

    if (!$device) {
        // Register new device if slot available
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ? AND status != 'REMOVED'");
        $stmt->execute([$user['id']]);
        $active_devices = $stmt->fetchColumn();

        if ($active_devices >= $user['max_devices']) {
            logAction($pdo, $username, $device_id, 'LOGIN_FAILED', 'DEVICE_LIMIT_REACHED');
            sendResponse(["success" => false, "error" => ["code" => "DEVICE_LIMIT_REACHED", "message" => "Device limit reached for this account (Max: {$user['max_devices']})"]]);
        }

        // Register
        $stmt = $pdo->prepare("INSERT INTO devices (user_id, device_id, device_name, status) VALUES (?, ?, ?, 'ACTIVE')");
        $stmt->execute([$user['id'], $device_id, $device_name]);
        $device_db_id = $pdo->lastInsertId();
        
        logAction($pdo, $username, $device_id, 'DEVICE_REGISTERED', 'SUCCESS');

        $device = [
            'id' => $device_db_id,
            'device_id' => $device_id,
            'status' => 'ACTIVE'
        ];
    } else {
        if ($device['status'] === 'BLOCKED' || $device['status'] === 'REMOVED') {
            logAction($pdo, $username, $device_id, 'LOGIN_FAILED', 'DEVICE_BLOCKED');
            sendResponse(["success" => false, "error" => ["code" => "DEVICE_BLOCKED", "message" => "This device has been removed or blocked"]]);
        }
        
        // Update last seen
        $stmt = $pdo->prepare("UPDATE devices SET last_seen = NOW(), last_ip = ? WHERE id = ?");
        $stmt->execute([getClientIp(), $device['id']]);
    }

    // 3. Create Session
    $session_hash = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, device_id, session_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
    $stmt->execute([$user['id'], $device['id'], $session_hash]);

    // Update User Last Login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // 4. Generate RSA Signature (Offline Verification)
    $payload = json_encode([
        "account_id" => $user['id'],
        "username" => $user['username'],
        "device_id" => $device_id,
        "plan_name" => $user['plan_name'],
        "status" => "ACTIVE",
        "timestamp" => time()
    ]);

    $private_key_path = __DIR__ . '/../server/keys/private_key.pem';
    if (!file_exists($private_key_path)) {
        // Fallback to empty if not setup, though ideally should throw error
        $signature = base64_encode(hash_hmac('sha256', $payload, 'BSRP_SECRET'));
    } else {
        $private_key = openssl_pkey_get_private(file_get_contents($private_key_path));
        openssl_sign($payload, $signature_raw, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature_raw);
    }

    logAction($pdo, $username, $device_id, 'LOGIN_SUCCESS', 'SUCCESS');

    sendResponse([
        "success" => true,
        "account" => [
            "id" => $user['id'],
            "username" => $user['username'],
            "plan" => $user['plan_name'],
            "max_devices" => $user['max_devices']
        ],
        "device" => [
            "id" => $device['id'],
            "status" => $device['status']
        ],
        "license" => [
            "status" => "ACTIVE",
            "payload" => $payload,
            "signature" => $signature
        ],
        "session" => [
            "credential" => $session_hash
        ]
    ]);

} catch (Exception $e) {
    sendResponse(["success" => false, "error" => ["code" => "SERVER_ERROR", "message" => $e->getMessage()]]);
}
