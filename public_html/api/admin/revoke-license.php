<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db.php';
require_once "auth.php";
requireAdmin();

$input = $_REQUEST;
if (empty($input['id'])) die(json_encode(["success" => false]));

$id = (int)$input['id'];
$stmt = $pdo->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?");
$stmt->execute([$id]);

$stmt = $pdo->prepare("UPDATE sessions SET revoked_at = NOW() WHERE user_id = ?");
$stmt->execute([$id]);

echo json_encode(["success" => true]);
