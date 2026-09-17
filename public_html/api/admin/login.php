<?php
require_once __DIR__ . '/../../server/config/db.php';

header("Content-Type: application/json");

session_start();

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'check') {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false]);
        }
        exit;
    }
    if ($_GET['action'] === 'logout') {
        session_destroy();
        echo json_encode(["success" => true]);
        exit;
    }
}

$username = trim($_GET['username'] ?? '');
$password = $_GET['password'] ?? '';

if (empty($username) || empty($password)) {
    // Fallback to JSON payload just in case
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
    // Return a simple session token
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    
    $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);

    echo json_encode(["success" => true, "token" => session_id()]);
} else {
    echo json_encode(["success" => false, "error" => "INVALID_CREDENTIALS"]);
}
