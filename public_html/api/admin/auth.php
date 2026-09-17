<?php
// public_html/api/admin/auth.php
// Middleware autentikasi admin — menggunakan token DB (kompatibel Vercel serverless)
// Token dikirim via header: X-Admin-Token: <token>

function requireAdmin() {
    global $pdo;
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['admin_token'] ?? '';
    if (empty($token)) {
        header('Content-Type: application/json');
        die(json_encode(["success" => false, "error" => "UNAUTHORIZED"]));
    }
    $stmt = $pdo->prepare("
        SELECT a.id FROM admin_sessions s 
        JOIN admin_users a ON s.admin_id = a.id 
        WHERE s.token = ? AND s.expires_at > NOW() AND a.status = 'ACTIVE'
    ");
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        die(json_encode(["success" => false, "error" => "UNAUTHORIZED"]));
    }
}
