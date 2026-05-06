<?php
// controllers/AuthController.php

class AuthController {
    private User $userModel;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->userModel = new User();
        $this->auth = new AuthMiddleware();
    }
    
    public function login(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['username']) || empty($data['password'])) {
            $this->respond(false, 'Username and password required', 400);
            return;
        }
        
        $user = $this->auth->login($data['username'], $data['password']);
        
        if (!$user) {
            $this->respond(false, 'Invalid credentials', 401);
            return;
        }
        
        $this->respond(true, [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'message' => 'Login successful'
        ]);
    }
    
    public function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $this->respond(false, 'All fields required', 400);
            return;
        }
        
        if ($this->userModel->findByUsername($data['username'])) {
            $this->respond(false, 'Username already taken', 409);
            return;
        }
        
        if ($this->userModel->findByEmail($data['email'])) {
            $this->respond(false, 'Email already registered', 409);
            return;
        }
        
        // Create user in database
        $userId = $this->userModel->create($data);
        
        // ✅ GENERATE WALLET IMMEDIATELY AFTER REGISTRATION
        $walletAddress = $this->generateWalletForUser($userId);
        
        error_log("User #{$userId} registered. Wallet: {$walletAddress}");
        
        $this->respond(true, [
            'user_id' => $userId,
            'message' => 'Registration successful. Please login.'
        ]);
    }

    /**
     * Generate a deterministic wallet address for a user
     */
    private function generateWalletForUser(int $userId): string {
        $secret = defined('WALLET_SECRET') ? WALLET_SECRET : 'terrachain_default_secret_change_in_production';
        $seed = "terrachain_user_{$userId}_{$secret}";
        
        $hash = hash('sha256', $seed);
        $address = '0x' . substr($hash, 0, 40);
        $address = strtolower($address);
        
        // Save to database
        $this->userModel->assignWalletAddress($userId, $address);
        
        // Log
        $db = Database::getConnection();
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes) VALUES (?, ?, ?, ?, ?)')
           ->execute([$userId, 'wallet_generated', 'user', $userId, "Auto-generated at registration: {$address}"]);
        
        return $address;
    }
    
    public function logout(): void {
        $this->auth->logout();
        $this->respond(true, ['message' => 'Logged out']);
    }
    
    public function me(): void {
        $user = $this->auth->authenticate();
        if (!$user) {
            $this->respond(false, 'Not authenticated', 401);
            return;
        }
        
        $this->respond(true, ['user' => $user]);
    }
    
    public function linkWallet(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['wallet_address'])) {
            $this->respond(false, 'Wallet address required', 400);
            return;
        }
        
        $this->userModel->assignWalletAddress($admin['id'], $data['wallet_address']);
        $this->respond(true, ['message' => 'Wallet linked successfully']);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}