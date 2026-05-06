<?php
// middleware/AuthMiddleware.php

class AuthMiddleware {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function authenticate(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $userModel = new User();
        $user = $userModel->findById($_SESSION['user_id']);
        
        if (!$user) {
            session_destroy();
            return null;
        }
        
        return $user;
    }
    
    public function requireAuth(): array {
        $user = $this->authenticate();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }
        return $user;
    }
    
    public function requireAdmin(): array {
        $user = $this->requireAuth();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        return $user;
    }
    
    public function login(string $username, string $password): ?array {
        $userModel = new User();
        $user = $userModel->findByUsername($username);
        
        if (!$user) {
            $user = $userModel->findByEmail($username);
        }
        
        if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
            return null;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        
        return $user;
    }
    
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }
}