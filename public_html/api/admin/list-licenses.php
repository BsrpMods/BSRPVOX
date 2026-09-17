<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db.php';
require_once "auth.php";
requireAdmin();

$stmt = $pdo->query("
    SELECT u.id, u.username as license_key, u.plain_password, u.status, u.created_at, 
           (SELECT device_id FROM devices d WHERE d.user_id = u.id AND d.status='ACTIVE' LIMIT 1) as device_id
    FROM users u ORDER BY u.created_at DESC
");
$licenses = $stmt->fetchAll();

echo json_encode(["success" => true, "data" => $licenses]);
