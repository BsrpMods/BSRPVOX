CREATE TABLE licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) NOT NULL UNIQUE,
    device_id VARCHAR(128) NULL UNIQUE,
    status ENUM('UNUSED','ACTIVE','REVOKED') NOT NULL DEFAULT 'UNUSED',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    last_ip VARCHAR(45) NULL
);

CREATE TABLE admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user. Password is: password
INSERT INTO admin_users (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

CREATE TABLE activation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_key_partial VARCHAR(10) NOT NULL,
    device_id VARCHAR(128) NULL,
    action ENUM('ACTIVATE','VERIFY','REVOKE','RESET','CREATE') NOT NULL,
    result VARCHAR(32) NOT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
