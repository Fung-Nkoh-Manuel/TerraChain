<?php
// config/database.php

define('DB_HOST', 'localhost:5333');
define('DB_NAME', 'terrachain_v2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 86400); // 24 hours

// IPFS / Pinata
define('PINATA_JWT', 'your_pinata_jwt_token');
define('PINATA_API_URL', 'https://api.pinata.cloud/pinning/pinFileToIPFS');
define('PINATA_GATEWAY', 'https://gateway.pinata.cloud/ipfs/');

// Upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// Blockchain (Admin-only interactions)
define('LAND_REGISTRY_CONTRACT', '0xf733274e8946f220c940e9047E2717ba8892C7c2');
define('RPC_URL', 'https://sepolia.drpc.org');
define('BLOCKCHAIN_ENABLED', true); // Toggle for testing

class Database {
    private static $instance = null;
    
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return self::$instance;
    }
}