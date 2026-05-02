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
            $this->json(false, 'Username and password required', 400);
            return;
        }
        
        $user = $this->auth->login($data['username'], $data['password']);
        
        if (!$user) {
            $this->json(false, 'Invalid credentials', 401);
            return;
        }
        
        $this->json(true, [
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
            $this->json(false, 'All fields required', 400);
            return;
        }
        
        if ($this->userModel->findByUsername($data['username'])) {
            $this->json(false, 'Username already taken', 409);
            return;
        }
        
        if ($this->userModel->findByEmail($data['email'])) {
            $this->json(false, 'Email already registered', 409);
            return;
        }
        
        $userId = $this->userModel->create($data);
        $this->json(true, ['user_id' => $userId, 'message' => 'Registration successful. Please login.']);
    }
    
    public function logout(): void {
        $this->auth->logout();
        $this->json(true, ['message' => 'Logged out']);
    }
    
    public function me(): void {
        $user = $this->auth->authenticate();
        if (!$user) {
            $this->json(false, 'Not authenticated', 401);
            return;
        }
        
        $this->json(true, ['user' => $user]);
    }
    
    public function linkWallet(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['wallet_address'])) {
            $this->json(false, 'Wallet address required', 400);
            return;
        }
        
        $this->userModel->assignWalletAddress($admin['id'], $data['wallet_address']);
        $this->json(true, ['message' => 'Wallet linked successfully']);
    }
    
    private function json(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}