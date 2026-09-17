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

if (empty($session_hash)) {
    echo json_encode(["success" => false, "error" => ["code" => "MISSING_DATA", "message" => "Session missing"]]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE sessions SET revoked_at = NOW() WHERE session_hash = ?");
    $stmt->execute([$session_hash]);

    echo json_encode(["success" => true, "status" => "LOGOUT_OK"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => ["code" => "SERVER_ERROR", "message" => $e->getMessage()]]);
}
