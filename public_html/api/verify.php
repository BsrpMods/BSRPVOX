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

$data = json_decode(file_get_contents("php://input"), true);
$session_hash = $data['session'] ?? '';
$device_id = $data['device_id'] ?? '';

if (empty($session_hash) || empty($device_id)) {
    echo json_encode(["success" => false, "error" => ["code" => "MISSING_DATA", "message" => "Session or device id missing"]]);
    exit;
}

try {
    // 1. Check Session
    $stmt = $pdo->prepare("SELECT s.*, u.status as user_status, d.status as device_status, d.device_id as db_device_id 
                           FROM sessions s 
                           JOIN users u ON s.user_id = u.id 
                           JOIN devices d ON s.device_id = d.id 
                           WHERE s.session_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()");
    $stmt->execute([$session_hash]);
    $session = $stmt->fetch();

    if (!$session) {
        echo json_encode(["success" => false, "error" => ["code" => "SESSION_REVOKED", "message" => "Session is invalid or has expired"]]);
        exit;
    }

    if ($session['db_device_id'] !== $device_id) {
        echo json_encode(["success" => false, "error" => ["code" => "INVALID_DEVICE", "message" => "Session does not match device"]]);
        exit;
    }

    if ($session['user_status'] !== 'ACTIVE') {
        echo json_encode(["success" => false, "error" => ["code" => "ACCOUNT_SUSPENDED", "message" => "This account is suspended"]]);
        exit;
    }

    if ($session['device_status'] !== 'ACTIVE') {
        echo json_encode(["success" => false, "error" => ["code" => "DEVICE_REMOVED", "message" => "This device has been removed or blocked"]]);
        exit;
    }

    // Update last seen
    $stmt = $pdo->prepare("UPDATE sessions SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$session['id']]);

    $stmt = $pdo->prepare("UPDATE devices SET last_seen = NOW(), last_ip = ? WHERE id = ?");
    $stmt->execute([getClientIp(), $session['device_id']]);

    echo json_encode(["success" => true, "status" => "VERIFY_OK"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => ["code" => "SERVER_ERROR", "message" => $e->getMessage()]]);
}
