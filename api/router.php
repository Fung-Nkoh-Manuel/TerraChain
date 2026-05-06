<?php
// api/router.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Wallet-Address');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload
$basePath = __DIR__;

try {
    require_once $basePath . '/../config/database.php';
    require_once $basePath . '/../models/User.php';
    require_once $basePath . '/../models/Parcel.php';
    require_once $basePath . '/../models/KYC.php';
    require_once $basePath . '/../models/Transfer.php';
    require_once $basePath . '/../models/Dispute.php';
    require_once $basePath . '/../services/BlockchainService.php';
    require_once $basePath . '/../services/DocumentService.php';
    require_once $basePath . '/../services/NotificationService.php';
    require_once $basePath . '/../middleware/AuthMiddleware.php';
    require_once $basePath . '/../controllers/AuthController.php';
    require_once $basePath . '/../controllers/ParcelController.php';
    require_once $basePath . '/../controllers/KYCController.php';
    require_once $basePath . '/../controllers/TransferController.php';
    require_once $basePath . '/../controllers/DisputeController.php';
    require_once $basePath . '/../controllers/NotificationController.php';
    require_once $basePath . '/../controllers/UploadController.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dependencies: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

// Parse route
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = preg_replace('#^/terrachain-v2#', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // Auth
    'POST /api/auth/login'       => ['AuthController', 'login'],
    'POST /api/auth/register'    => ['AuthController', 'register'],
    'POST /api/auth/logout'      => ['AuthController', 'logout'],
    'GET /api/auth/me'           => ['AuthController', 'me'],
    'POST /api/auth/wallet'      => ['AuthController', 'linkWallet'],
    
    // Parcels
    'POST /api/parcels/submit'   => ['ParcelController', 'submit'],
    'POST /api/parcels/approve'  => ['ParcelController', 'approve'],
    'POST /api/parcels/reject'   => ['ParcelController', 'reject'],
    'GET /api/parcels/my'        => ['ParcelController', 'myParcels'],
    'GET /api/parcels/all'       => ['ParcelController', 'allParcels'],
    'GET /api/parcels/search'    => ['ParcelController', 'search'],
    'GET /api/parcels/pending'   => ['ParcelController', 'pending'],
    'POST /api/parcels/update-blockchain' => ['ParcelController', 'updateBlockchain'],
    
    // KYC
    'POST /api/kyc/submit'       => ['KYCController', 'submit'],
    'POST /api/kyc/verify'       => ['KYCController', 'verify'],
    'GET /api/kyc/status'        => ['KYCController', 'status'],
    'GET /api/kyc/pending'       => ['KYCController', 'pending'],
    
    // Transfers
    'POST /api/transfers/request'  => ['TransferController', 'request'],
    'POST /api/transfers/approve'  => ['TransferController', 'approve'],
    'POST /api/transfers/reject'   => ['TransferController', 'reject'],
    'GET /api/transfers/my'        => ['TransferController', 'myTransfers'],
    'GET /api/transfers/all'       => ['TransferController', 'allTransfers'],
    
    // Disputes
    'POST /api/disputes/file'    => ['DisputeController', 'file'],
    'POST /api/disputes/get'     => ['DisputeController', 'get'],
    // 'POST /api/disputes/vote'    => ['DisputeController', 'vote'],
    'POST /api/disputes/resolve' => ['DisputeController', 'resolve'],
    'GET /api/disputes/all'      => ['DisputeController', 'all'],
    
    // Notifications
    'GET /api/notifications/list'          => ['NotificationController', 'list'],
    'POST /api/notifications/read-all'     => ['NotificationController', 'markAllRead'],
    'POST /api/notifications/mark-read-one' => ['NotificationController', 'markReadOne'],
    
    // Upload
    'POST /api/upload' => ['UploadController', 'upload'],
];

$routeKey = $method . ' ' . $uri;

// Debug log
error_log("API Request: {$routeKey}");

try {
    if (isset($routes[$routeKey])) {
        [$controllerClass, $action] = $routes[$routeKey];
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class '{$controllerClass}' not found");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $action)) {
            throw new Exception("Method '{$action}' not found in '{$controllerClass}'");
        }
        
        $controller->$action();
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Route not found',
            'route' => $routeKey
        ]);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
