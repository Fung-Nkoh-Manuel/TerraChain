<?php
// api/router.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Parcel.php';
require_once __DIR__ . '/../models/KYC.php';
require_once __DIR__ . '/../models/Transfer.php';
require_once __DIR__ . '/../models/Dispute.php';
require_once __DIR__ . '/../services/BlockchainService.php';
require_once __DIR__ . '/../services/DocumentService.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ParcelController.php';
require_once __DIR__ . '/../controllers/KYCController.php';
require_once __DIR__ . '/../controllers/TransferController.php';
require_once __DIR__ . '/../controllers/DisputeController.php';

// Parse route
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Remove /terrachain-v2 prefix if present
$uri = preg_replace('#^/terrachain-v2#', '', $uri);

$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // Auth
    'POST /api/auth/login'    => ['AuthController', 'login'],
    'POST /api/auth/register' => ['AuthController', 'register'],
    'POST /api/auth/logout'   => ['AuthController', 'logout'],
    'GET /api/auth/me'        => ['AuthController', 'me'],
    'POST /api/auth/wallet'   => ['AuthController', 'linkWallet'],
    
    // Parcels
    'POST /api/parcels/submit'   => ['ParcelController', 'submit'],
    'POST /api/parcels/approve'  => ['ParcelController', 'approve'],
    'POST /api/parcels/reject'   => ['ParcelController', 'reject'],
    'GET /api/parcels/my'        => ['ParcelController', 'myParcels'],
    'GET /api/parcels/all'       => ['ParcelController', 'allParcels'],
    'GET /api/parcels/search'    => ['ParcelController', 'search'],
    'GET /api/parcels/pending'   => ['ParcelController', 'pending'],
    
    // KYC
    'POST /api/kyc/submit'  => ['KYCController', 'submit'],
    'POST /api/kyc/verify'  => ['KYCController', 'verify'],
    'GET /api/kyc/status'   => ['KYCController', 'status'],
    'GET /api/kyc/pending'  => ['KYCController', 'pending'],
    
    // Transfers
    'POST /api/transfers/request'  => ['TransferController', 'request'],
    'POST /api/transfers/approve'  => ['TransferController', 'approve'],
    'POST /api/transfers/reject'   => ['TransferController', 'reject'],
    'GET /api/transfers/my'        => ['TransferController', 'myTransfers'],
    'GET /api/transfers/all'       => ['TransferController', 'allTransfers'],
    
    // Disputes
    'POST /api/disputes/file'     => ['DisputeController', 'file'],
    'POST /api/disputes/vote'     => ['DisputeController', 'vote'],
    'POST /api/disputes/resolve'  => ['DisputeController', 'resolve'],
    'GET /api/disputes/all'       => ['DisputeController', 'all'],
];

$routeKey = $method . ' ' . $uri;

if (isset($routes[$routeKey])) {
    try {
        [$controllerClass, $action] = $routes[$routeKey];
        $controller = new $controllerClass();
        $controller->$action();
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Route not found',
        'method' => $method,
        'uri' => $uri,
        'routeKey' => $routeKey
    ]);
}