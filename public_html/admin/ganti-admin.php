<?php
require_once __DIR__ . '/../../config/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';

    if (!empty($new_username) && !empty($new_password)) {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            // Mengubah username dan password admin
            // Asumsi hanya ada 1 admin atau mengubah admin pertama di database
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, password_hash = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$new_username, $hash]);
            $message = "<div style='color: green; margin-bottom: 15px;'>Username dan Password berhasil diubah!<br><br><b>SANGAT PENTING:</b> Silakan HAPUS file ini (ganti-admin.php) setelah selesai agar tidak disalahgunakan orang lain!</div>";
        } catch (Exception $e) {
            $message = "<div style='color: red; margin-bottom: 15px;'>Gagal mengubah: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div style='color: red; margin-bottom: 15px;'>Username dan Password tidak boleh kosong!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Akun Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 320px; }
        input { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #1d4ed8; }
        label { font-weight: bold; font-size: 14px; color: #333; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top: 0; text-align: center;">Ganti Admin</h2>
        <?php if ($message) echo $message; ?>
        <form method="POST">
            <label>Username Baru:</label>
            <input type="text" name="username" placeholder="Masukkan username baru..." required>
            
            <label>Password Baru:</label>
            <input type="password" name="password" placeholder="Masukkan password baru..." required>
            
            <button type="submit">Update Data Admin</button>
        </form>
    </div>
</body>
</html>
