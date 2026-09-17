<?php
header("Content-Type: application/json");
require_once "../../server/config/db.php";
require_once "auth.php";
requireAdmin();

$bytes = random_bytes(4); 
$username = "user_" . bin2hex($bytes);
$password = "pass_" . bin2hex(random_bytes(3));
$hash = password_hash($password, PASSWORD_DEFAULT);

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 1;
// Ensure plan_id is valid (1 or 2)
if ($plan_id !== 1 && $plan_id !== 2) $plan_id = 1;

$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, plain_password, plan_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, "$username@example.com", $hash, $password, $plan_id]);

echo json_encode(["success" => true, "token" => "$username / $password"]);
