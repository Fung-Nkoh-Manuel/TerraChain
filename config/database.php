<?php
// config/database.php

// Use environment variables for Docker
define('DB_HOST', getenv('DB_HOST') ?: 'localhost:3306');
define('DB_NAME', getenv('DB_NAME') ?: 'terrachain_v2');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 86400); // 24 hours

// IPFS / Pinata
define('PINATA_JWT', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySW5mb3JtYXRpb24iOnsiaWQiOiJiM2M3NDRjZC02OTU0LTQ1NzYtYjFlNy02YTM2M2Y3MmU2NDkiLCJlbWFpbCI6Im5leW1hNzcwNEBnbWFpbC5jb20iLCJlbWFpbF92ZXJpZmllZCI6dHJ1ZSwicGluX3BvbGljeSI6eyJyZWdpb25zIjpbeyJkZXNpcmVkUmVwbGljYXRpb25Db3VudCI6MSwiaWQiOiJGUkExIn0seyJkZXNpcmVkUmVwbGljYXRpb25Db3VudCI6MSwiaWQiOiJOWUMxIn1dLCJ2ZXJzaW9uIjoxfSwibWZhX2VuYWJsZWQiOmZhbHNlLCJzdGF0dXMiOiJBQ1RJVkUifSwiYXV0aGVudGljYXRpb25UeXBlIjoic2NvcGVkS2V5Iiwic2NvcGVkS2V5S2V5IjoiZTMzOTk4NGViYzllNWIxNjhjYWUiLCJzY29wZWRLZXlTZWNyZXQiOiI1N2I5YjhkZGVhNTI1Y2M0YzY4ZGU5NGVhZDJiYzE1MjQ3NTIxNzExNmY1NGNmNTM2MmRjMGY0YTczOWZiZDI3IiwiZXhwIjoxODA0ODM1NjQzfQ.LaNIuZ9VAcbcu0MrKzBa9XxNvLiPL1M2sxJfdV9XYW8');
define('PINATA_API_URL', 'https://api.pinata.cloud/pinning/pinFileToIPFS');
define('PINATA_GATEWAY', 'https://gateway.pinata.cloud/ipfs/');

// Email (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'terrachain16@gmail.com');
define('SMTP_PASS', 'jwsc czhg ciuz gbwr');
define('SMTP_FROM', 'terrachain16@gmail.com');
define('SMTP_FROM_NAME', 'TerraChain');

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
define('LAND_REGISTRY_CONTRACT', '0x8a8937bb4cea0a6e00102ed9b9fcf8217d311d04');
define('RPC_URL', 'https://sepolia.drpc.org');
define('BLOCKCHAIN_ENABLED', true); // Toggle for testing
define('TEST_MODE', true); // SET TO FALSE IN PRODUCTION

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