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
        
        // 1. Validate credentials without starting session yet
        $user = $this->userModel->findByUsername($data['username']);
        if (!$user) {
            $user = $this->userModel->findByEmail($data['username']);
        }
        
        if (!$user || !$this->userModel->verifyPassword($data['password'], $user['password_hash'])) {
            $this->respond(false, 'Invalid credentials', 401);
            return;
        }
        
        // 2. Generate OTP and store in temporary session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp_code'] = $otp;
        $_SESSION['otp_user_id'] = $user['id'];
        $_SESSION['otp_expires'] = time() + 600; // 10 minutes
        
        // 3. Send Email
        $mailService = new MailService();
        $emailSent = $mailService->sendOTP($user['email'], $otp);
        
        if (!$emailSent) {
            error_log("FAILED to send OTP to {$user['email']}. OTP is: {$otp}");
        }
        
        $this->respond(true, [
            'status' => 'otp_required',
            'user_id' => $user['id'],
            'email' => substr($user['email'], 0, 3) . '****' . substr($user['email'], strpos($user['email'], '@')),
            'message' => 'A verification code has been sent to your email.'
        ]);
    }

    public function verifyOTP(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['user_id']) || empty($data['otp'])) {
            $this->respond(false, 'User ID and OTP required', 400);
            return;
        }
        
        // Verify from SESSION
        if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_user_id'])) {
            $this->respond(false, 'No verification code requested. Please login again.', 401);
            return;
        }
        
        if (time() > $_SESSION['otp_expires']) {
            unset($_SESSION['otp_code'], $_SESSION['otp_user_id'], $_SESSION['otp_expires']);
            $this->respond(false, 'Verification code has expired. Please login again.', 401);
            return;
        }
        
        if ($data['otp'] !== $_SESSION['otp_code'] || $data['user_id'] != $_SESSION['otp_user_id']) {
            $this->respond(false, 'Invalid verification code. Please try again.', 401);
            return;
        }
        
        // Valid OTP — Clear session and set final auth session
        unset($_SESSION['otp_code'], $_SESSION['otp_user_id'], $_SESSION['otp_expires']);
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $data['user_id'];
        
        $user = $this->userModel->findById($data['user_id']);
        
        $this->respond(true, [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'message' => 'Verification successful!'
        ]);
    }
    
    public function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $this->respond(false, 'All fields required', 400);
            return;
        }

        // ✅ Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Please enter a valid email address', 400);
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


    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email'])) {
            $this->respond(false, 'Email is required', 400);
            return;
        }
        
        $user = $this->userModel->findByEmail($data['email']);
        
        // Always return success to prevent email enumeration
        if (!$user) {
            $this->respond(true, ['message' => 'If that email exists, a reset link has been sent.']);
            return;
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        
        // Store in session for simplicity (in a real app, use a dedicated table)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_expires'] = time() + 1800; // 30 minutes
        
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/terrachain-v2/public/reset-password.php?token={$token}";
        
        $mailService = new MailService();
        $subject = "🔒 TerraChain - Password Reset Request";
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0e14; color: #ffffff; padding: 30px; border-radius: 12px;'>
            <h2 style='color: #00e5a0;'>Password Reset</h2>
            <p>You requested a password reset for your TerraChain account.</p>
            <p>Click the button below to set a new password. This link will expire in 30 minutes.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' style='background: #00e5a0; color: #0a0e14; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Reset Password</a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>If you didn't request this, you can safely ignore this email.</p>
        </div>
        ";
        
        $mailService->sendGenericEmail($user['email'], $subject, $message);
        
        $this->respond(true, ['message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['token']) || empty($data['password'])) {
            $this->respond(false, 'Token and password required', 400);
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Validate token
        if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $data['token']) {
            $this->respond(false, 'Invalid or expired token', 401);
            return;
        }
        
        if (time() > $_SESSION['reset_expires']) {
            unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);
            $this->respond(false, 'Reset link has expired', 401);
            return;
        }
        
        $userId = $_SESSION['reset_user_id'];
        
        // Update password
        $this->userModel->updatePassword($userId, $data['password']);
        
        // Clear reset session
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);
        
        $this->respond(true, ['message' => 'Password updated successfully']);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}