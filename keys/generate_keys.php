<?php
// keys/generate_keys.php
// -------------------------------------------------------
// Script helper untuk men-generate pasangan kunci RSA baru.
// Jalankan SEKALI di server Anda:
//   php keys/generate_keys.php
//
// PERINGATAN: Jika Anda men-generate ulang kunci, semua
// signature lisensi yang sudah ada akan TIDAK VALID.
// Lakukan hanya saat setup awal atau saat rotasi kunci.
// -------------------------------------------------------

// Keamanan: Hanya izinkan dijalankan via CLI atau localhost
if (PHP_SAPI !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        die("Akses ditolak. Jalankan script ini via CLI: php keys/generate_keys.php\n");
    }
}

$private_key_path = __DIR__ . '/private_key.pem';
$public_key_path  = __DIR__ . '/private_key.pem.pub';

// Cegah overwrite kunci yang sudah ada
if (file_exists($private_key_path)) {
    echo "[ERROR] File private_key.pem sudah ada!\n";
    echo "Hapus file tersebut terlebih dahulu jika Anda ingin men-generate ulang.\n";
    echo "PERINGATAN: Menghapus kunci lama akan membuat semua lisensi yang ada tidak valid!\n";
    exit(1);
}

echo "Sedang men-generate pasangan kunci RSA 2048-bit...\n";

$config = [
    "digest_alg"       => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

$res = openssl_pkey_new($config);

if (!$res) {
    echo "[ERROR] Gagal generate kunci. Pastikan ekstensi OpenSSL aktif di PHP.\n";
    echo "Error: " . openssl_error_string() . "\n";
    exit(1);
}

// Ekspor private key
openssl_pkey_export($res, $private_key_pem);

// Ekspor public key
$key_details = openssl_pkey_get_details($res);
$public_key_pem = $key_details['key'];

// Simpan ke file
file_put_contents($private_key_path, $private_key_pem);
file_put_contents($public_key_path, $public_key_pem);

// Set permission ketat untuk private key (Linux/Mac)
if (PHP_OS_FAMILY !== 'Windows') {
    chmod($private_key_path, 0600);
}

echo "[OK] Kunci RSA berhasil di-generate!\n";
echo "  Private key : keys/private_key.pem\n";
echo "  Public key  : keys/private_key.pem.pub\n";
echo "\n";
echo "PENTING: Jangan pernah meng-commit private_key.pem ke Git!\n";
echo "         File ini sudah terdaftar di .gitignore.\n";
