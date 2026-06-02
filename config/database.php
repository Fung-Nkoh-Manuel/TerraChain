<?php
// config/database.php

// ── Load .env File Dynamically ──────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Strip quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // putenv sets system environment variable; respects existing ones
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Use environment variables for Docker / Kubernetes / Local
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '5333');
define('DB_NAME', getenv('DB_NAME') ?: 'terrachain_v2');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 86400); // 24 hours

// IPFS / Pinata
define('PINATA_JWT', getenv('PINATA_JWT') ?: '');
define('PINATA_API_URL', 'https://api.pinata.cloud/pinning/pinFileToIPFS');
define('PINATA_GATEWAY', 'https://gateway.pinata.cloud/ipfs/');

// Email (SMTP)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'TerraChain');

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
define('LAND_REGISTRY_CONTRACT', '0x7589B43cea9A5061095d8e3a2C4413768A081A79');
define('RPC_URL', 'https://sepolia.drpc.org');
define('BLOCKCHAIN_ENABLED', true); // Toggle for testing
define('TEST_MODE', true); // SET TO FALSE IN PRODUCTION

class Database {
    private static $instance = null;
    
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $port = getenv('DB_PORT') ?: '';
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
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