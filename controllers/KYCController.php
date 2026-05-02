<?php
// controllers/KYCController.php

class KYCController {
    private KYC $kycModel;
    private User $userModel;
    private DocumentService $docService;
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    private BlockchainService $blockchain;
    
    public function __construct() {
        $this->kycModel = new KYC();
        $this->userModel = new User();
        $this->docService = new DocumentService();
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
        $this->blockchain = new BlockchainService();
    }
    
    /**
     * POST /api/kyc/submit
     * User submits KYC documents (off-chain entirely)
     */
    public function submit(): void {
        $user = $this->auth->requireAuth();
        
        // Check if already verified
        if ($this->kycModel->isVerified($user['id'])) {
            $this->respond(false, 'KYC already verified', 409);
        }
        
        // Process uploaded documents
        $documentHash = null;
        $ipfsHash = null;
        $allHashes = [];
        
        if (empty($_FILES['documents'])) {
            $this->respond(false, 'KYC documents required', 400);
        }
        
        // Handle multiple file uploads
        $files = $this->normalizeFilesArray($_FILES['documents']);
        
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $fileInfo = [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
            ];
            
            try {
                $result = $this->docService->processUpload($fileInfo, true);
                $allHashes[] = $result['sha256'];
                
                // Use first file's IPFS hash as primary
                if (!$ipfsHash) {
                    $ipfsHash = $result['ipfs_hash'];
                }
            } catch (Exception $e) {
                $this->respond(false, 'Upload failed: ' . $e->getMessage(), 400);
            }
        }
        
        if (empty($allHashes)) {
            $this->respond(false, 'No valid documents uploaded', 400);
        }
        
        // Combine hashes for the document hash
        $documentHash = $this->docService->combineHashes($allHashes);
        
        // Submit KYC (no blockchain interaction)
        $kycId = $this->kycModel->submit($user['id'], $documentHash, $ipfsHash);
        
        // Update user profile if provided
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($data['full_name']) || !empty($data['national_id'])) {
            $this->userModel->updateProfile($user['id'], [
                'full_name' => $data['full_name'] ?? $user['full_name'],
                'phone' => $data['phone'] ?? $user['phone'],
                'national_id' => $data['national_id'] ?? $user['national_id'],
                'email' => $user['email']
            ]);
        }
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'kyc_submitted',
            'New KYC Submission',
            "User {$user['full_name']} ({$user['email']}) submitted KYC documents for verification.",
            $kycId,
            'kyc'
        );
        
        $this->respond(true, [
            'kyc_id' => $kycId,
            'message' => 'KYC documents submitted successfully. Awaiting verification.',
            'status' => 'pending'
        ]);
    }
    
    /**
     * POST /api/kyc/verify (Admin/Validator)
     * Verify or reject KYC - entirely off-chain
     */
    public function verify(): void {
        $reviewer = $this->auth->requireValidator();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['kyc_id'])) {
            $this->respond(false, 'KYC ID required', 400);
        }
        
        $approved = (bool)($data['approved'] ?? true);
        $reason = $data['reason'] ?? ($approved ? null : 'Documents insufficient or invalid');
        
        $this->kycModel->verify($data['kyc_id'], $reviewer['id'], $approved, $reason);
        
        // Get KYC record to notify user
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT k.*, u.email, u.full_name FROM kyc_records k JOIN users u ON k.user_id = u.id WHERE k.id = ?');
        $stmt->execute([$data['kyc_id']]);
        $kyc = $stmt->fetch();
        
        if ($kyc) {
            if ($approved) {
                $this->notifications->send(
                    $kyc['user_id'],
                    'kyc_verified',
                    'KYC Verified ✓',
                    'Your identity verification has been approved. You can now register land parcels.',
                    $kyc['id'],
                    'kyc'
                );
            } else {
                $this->notifications->send(
                    $kyc['user_id'],
                    'kyc_rejected',
                    'KYC Rejected',
                    "Your KYC submission was rejected. Reason: {$reason}. Please resubmit with correct documents.",
                    $kyc['id'],
                    'kyc'
                );
            }
        }
        
        $this->respond(true, [
            'message' => $approved ? 'KYC verified successfully' : 'KYC rejected',
            'status' => $approved ? 'verified' : 'rejected'
        ]);
    }
    
    /**
     * GET /api/kyc/status
     * Check current user's KYC status
     */
    public function status(): void {
        $user = $this->auth->requireAuth();
        $kyc = $this->kycModel->getUserKYC($user['id']);
        
        if (!$kyc) {
            $this->respond(true, [
                'status' => 'not_submitted',
                'message' => 'No KYC submission found'
            ]);
            return;
        }
        
        // Don't expose full document hash to regular users
        $response = [
            'status' => $kyc['status'],
            'submitted_at' => $kyc['submitted_at'],
            'verified_at' => $kyc['verified_at'],
            'has_ipfs' => !empty($kyc['ipfs_hash']),
            'rejection_reason' => $kyc['status'] === 'rejected' ? $kyc['rejection_reason'] : null
        ];
        
        // Admins can see more details
        if (in_array($user['role'], ['admin', 'validator'])) {
            $response['document_hash'] = $kyc['document_hash'];
            $response['ipfs_hash'] = $kyc['ipfs_hash'];
        }
        
        $this->respond(true, $response);
    }
    
    /**
     * GET /api/kyc/pending (Admin/Validator)
     * List all pending KYC submissions
     */
    public function pending(): void {
        $this->auth->requireValidator();
        $pending = $this->kycModel->getPendingKYC();
        
        // Add IPFS gateway URLs for document viewing
        foreach ($pending as &$item) {
            if (!empty($item['ipfs_hash'])) {
                $item['document_url'] = PINATA_GATEWAY . $item['ipfs_hash'];
            }
        }
        
        $this->respond(true, $pending);
    }
    
    /**
     * Normalize PHP's weird file array structure
     */
    private function normalizeFilesArray(array $files): array {
        $normalized = [];
        
        if (isset($files['name']) && is_array($files['name'])) {
            // Multiple files
            foreach ($files['name'] as $key => $name) {
                $normalized[] = [
                    'name' => $name,
                    'tmp_name' => $files['tmp_name'][$key],
                    'size' => $files['size'][$key],
                    'error' => $files['error'][$key],
                    'type' => $files['type'][$key],
                ];
            }
        } elseif (isset($files['name'])) {
            // Single file
            $normalized[] = $files;
        }
        
        return $normalized;
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}