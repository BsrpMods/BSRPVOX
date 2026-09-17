<?php
// public_html/api/activate.php
if (!isset($_GET['webview2'])) header("Content-Type: application/json");
else header("Content-Type: text/html");
require_once "../server/config/db.php";

$private_key_path = "../server/keys/private_key.pem";
if (!file_exists($private_key_path)) {
    die(json_encode(["success" => false, "error" => "SERVER_KEY_ERROR"]));
}
$private_key = file_get_contents($private_key_path);

$input = $_REQUEST;
if (empty($input['token']) || empty($input['device_id'])) {
    $out = json_encode(["success" => false, "error" => "INVALID_REQUEST"]);
    if (isset($_GET['webview2'])) die("<script>window.chrome.webview.postMessage('$out');</script>"); else die($out);
}

$token = trim($input['token']);
$device_id = trim($input['device_id']);

$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$token]);
$license = $stmt->fetch();

if (!$license) {
    logAction($pdo, $token, $device_id, 'ACTIVATE', 'INVALID_TOKEN');
    $out = json_encode(["success" => false, "error" => "INVALID_TOKEN"]);
    if (isset($_GET['webview2'])) die("<script>window.chrome.webview.postMessage('$out');</script>"); else die($out);
}

if ($license['status'] === 'REVOKED') {
    logAction($pdo, $token, $device_id, 'ACTIVATE', 'LICENSE_REVOKED');
    $out = json_encode(["success" => false, "error" => "LICENSE_REVOKED"]);
    if (isset($_GET['webview2'])) die("<script>window.chrome.webview.postMessage('$out');</script>"); else die($out);
}

if ($license['status'] === 'UNUSED') {
    $update = $pdo->prepare("UPDATE licenses SET status = 'ACTIVE', device_id = ?, activated_at = NOW(), last_verified_at = NOW(), last_ip = ? WHERE id = ?");
    $update->execute([$device_id, getClientIp(), $license['id']]);
    $license['status'] = 'ACTIVE';
    $license['device_id'] = $device_id;
    logAction($pdo, $token, $device_id, 'ACTIVATE', 'SUCCESS_BIND');
} else {
    if ($license['device_id'] !== $device_id) {
        logAction($pdo, $token, $device_id, 'ACTIVATE', 'DEVICE_MISMATCH');
        $out = json_encode(["success" => false, "error" => "DEVICE_MISMATCH"]);
        if (isset($_GET['webview2'])) die("<script>window.chrome.webview.postMessage('$out');</script>"); else die($out);
    }
    
    $update = $pdo->prepare("UPDATE licenses SET last_verified_at = NOW(), last_ip = ? WHERE id = ?");
    $update->execute([getClientIp(), $license['id']]);
    logAction($pdo, $token, $device_id, 'ACTIVATE', 'SUCCESS_VERIFY_BIND');
}

$payload = $license['id'] . "|" . $license['device_id'] . "|" . $license['status'];
openssl_sign($payload, $signature, $private_key, OPENSSL_ALGO_SHA256);
$signature_base64 = base64_encode($signature);

$out = json_encode([
    "success" => true,
    "license_id" => $license['id'],
    "device_id" => $license['device_id'],
    "status" => $license['status'],
    "license_signature" => $signature_base64
]);
if (isset($_GET['webview2'])) echo "<script>window.chrome.webview.postMessage('$out');</script>";
else echo $out;
