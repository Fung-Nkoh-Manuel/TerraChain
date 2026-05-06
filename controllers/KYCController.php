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
            return;
        }
        
        if (empty($_FILES['documents'])) {
            $this->respond(false, 'KYC documents required', 400);
            return;
        }
        
        $files = $this->normalizeFilesArray($_FILES['documents']);
        $db = Database::getConnection();
        
        // ═══════════════════════════════════════════════
        // STEP 1: Compute hashes FIRST (before IPFS upload)
        // ═══════════════════════════════════════════════
        
        $allHashes = [];
        
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            
            // Compute SHA-256 hash from the temp file (no IPFS yet)
            $hash = hash_file('sha256', $file['tmp_name']);
            $allHashes[] = $hash;
        }
        
        if (empty($allHashes)) {
            $this->respond(false, 'No valid documents uploaded', 400);
            return;
        }
        
        $documentHash = $this->docService->combineHashes($allHashes);
        
        // ═══════════════════════════════════════════════
        // STEP 2: Check for duplicates (BEFORE IPFS)
        // ═══════════════════════════════════════════════
        
        // Check each individual hash
        foreach ($allHashes as $hash) {
            $stmt = $db->prepare("
                SELECT k.id, u.full_name, u.email 
                FROM kyc_records k 
                JOIN users u ON k.user_id = u.id 
                WHERE k.document_hash LIKE ? 
                AND k.user_id != ?
            ");
            $stmt->execute(['%' . $hash . '%', $user['id']]);
            $dup = $stmt->fetch();
            
            if ($dup) {
                $this->respond(false, 
                    "❌ DUPLICATE DOCUMENT: This document was already submitted by \"{$dup['full_name']}\" ({$dup['email']}).\n\n" .
                    "The same identity document cannot be used by multiple people. Please upload a different document.",
                    409
                );
                return;
            }
        }
        
        // Also check the combined hash
        $stmt = $db->prepare("
            SELECT k.id, u.full_name, u.email 
            FROM kyc_records k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.document_hash = ? 
            AND k.user_id != ?
        ");
        $stmt->execute([$documentHash, $user['id']]);
        $dupCombined = $stmt->fetch();
        
        if ($dupCombined) {
            $this->respond(false,
                "❌ DUPLICATE DOCUMENTS: These exact documents were already submitted by \"{$dupCombined['full_name']}\" ({$dupCombined['email']}).\n\n" .
                "The same documents cannot be used for multiple KYC verifications.",
                409
            );
            return;
        }
        
        // ═══════════════════════════════════════════════
        // STEP 3: No duplicates found — NOW upload to IPFS
        // ═══════════════════════════════════════════════
        
        $ipfsHash = null;
        
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            
            $fileInfo = [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
            ];
            
            try {
                $result = $this->docService->processUpload($fileInfo, true);
                if (!$ipfsHash && !empty($result['ipfs_hash'])) {
                    $ipfsHash = $result['ipfs_hash'];
                }
            } catch (Exception $e) {
                error_log('IPFS upload error: ' . $e->getMessage());
            }
        }
        
        $ipfsHash = $ipfsHash ?? '';
        
        // ═══════════════════════════════════════════════
        // STEP 4: Save KYC record
        // ═══════════════════════════════════════════════
        
        $kycId = $this->kycModel->submit($user['id'], $documentHash, $ipfsHash);
        
        // Update profile if provided
        $inputData = $_POST;
        if (!empty($inputData['full_name']) || !empty($inputData['national_id'])) {
            $this->userModel->updateProfile($user['id'], [
                'full_name' => $inputData['full_name'] ?? $user['full_name'],
                'phone' => $inputData['phone'] ?? ($user['phone'] ?? ''),
                'national_id' => $inputData['national_id'] ?? ($user['national_id'] ?? ''),
                'email' => $user['email']
            ]);
        }
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'kyc_submitted',
            'New KYC Submission',
            "User {$user['full_name']} ({$user['email']}) submitted KYC documents.",
            $kycId,
            'kyc'
        );
        
        $this->respond(true, [
            'kyc_id' => $kycId,
            'message' => '✅ KYC submitted successfully. Awaiting admin verification.',
            'status' => 'pending',
            'ipfs_uploaded' => !empty($ipfsHash)
        ]);
    }
    
    /**
     * POST /api/kyc/verify (Admin/Validator)
     * Verify or reject KYC - entirely off-chain
     */
    public function verify(): void {
        $reviewer = $this->auth->requireAdmin();
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
                    '✅ KYC Verified',
                    'Your identity has been verified. You can now register land parcels.',
                    $kyc['id'],
                    'kyc'
                );
            } else {
                $this->notifications->send(
                    $kyc['user_id'],
                    'kyc_rejected',
                    '❌ KYC Rejected',
                    "Your KYC was rejected. Reason: {$reason}. Please resubmit with correct documents.",
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
        if ($user['role'] === 'admin') {
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
        $this->auth->requireAdmin();
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