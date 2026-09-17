<?php
// public_html/api/admin/login.php
require_once __DIR__ . '/../../../config/db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token");

// --- CHECK / LOGOUT ---
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'check') {
        $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['admin_token'] ?? '';
        if (empty($token)) { echo json_encode(["success" => false]); exit; }
        $stmt = $pdo->prepare("SELECT id FROM admin_sessions WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        echo json_encode(["success" => !!$stmt->fetch()]);
        exit;
    }
    if ($_GET['action'] === 'logout') {
        $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['admin_token'] ?? '';
        if ($token) {
            $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE token = ?");
            $stmt->execute([$token]);
        }
        echo json_encode(["success" => true]);
        exit;
    }
}

// --- LOGIN ---
$username = trim($_GET['username'] ?? '');
$password = $_GET['password'] ?? '';

if (empty($username) || empty($password)) {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
}

if (empty($username) || empty($password)) {
    echo json_encode(["success" => false, "error" => "MISSING_DATA"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash']) && $admin['status'] === 'ACTIVE') {
    // Generate token, simpan ke DB
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO admin_sessions (admin_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR))");
    $stmt->execute([$admin['id'], $token]);

    $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);

    echo json_encode(["success" => true, "token" => $token]);
} else {
    echo json_encode(["success" => false, "error" => "INVALID_CREDENTIALS"]);
}
