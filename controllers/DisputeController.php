<?php
// controllers/DisputeController.php

class DisputeController {
    private Dispute $disputeModel;
    private Parcel $parcelModel;
    private User $userModel;
    private DocumentService $docService;
    private BlockchainService $blockchain;
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->disputeModel = new Dispute();
        $this->parcelModel = new Parcel();
        $this->userModel = new User();
        $this->docService = new DocumentService();
        $this->blockchain = new BlockchainService();
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * POST /api/disputes/file
     */
    public function file(): void {
        $user = $this->auth->requireAuth();
        
        // Read from JSON or FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $inputData = $_POST;
        }
        
        $parcelNumber = trim($inputData['parcel_number'] ?? '');
        $disputeType = $inputData['dispute_type'] ?? 'ownership';
        $description = trim($inputData['description'] ?? '');
        $respondentEmail = trim($inputData['respondent_email'] ?? '');
        
        // Validate
        if (empty($parcelNumber)) {
            $this->respond(false, 'Parcel number is required.', 400);
            return;
        }
        if (empty($description)) {
            $this->respond(false, 'Description is required.', 400);
            return;
        }
        
        // Find parcel
        $parcel = $this->parcelModel->findByNumber($parcelNumber);
        if (!$parcel) {
            $this->respond(false, "Parcel \"{$parcelNumber}\" not found.", 404);
            return;
        }
        
        // Find respondent if provided
        $respondentId = null;
        if (!empty($respondentEmail)) {
            $respondent = $this->userModel->findByEmail($respondentEmail);
            if ($respondent && $respondent['id'] == $user['id']) {
                $this->respond(false, 'You cannot file a dispute against yourself.', 400);
                return;
            }
            $respondentId = $respondent ? $respondent['id'] : null;
        }
        
        // Process evidence
        $evidenceHash = null;
        if (!empty($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            try {
                $fileInfo = [
                    'name' => $_FILES['evidence']['name'],
                    'tmp_name' => $_FILES['evidence']['tmp_name'],
                    'size' => $_FILES['evidence']['size'],
                ];
                $result = $this->docService->processUpload($fileInfo);
                $evidenceHash = $result['ipfs_hash'];
            } catch (Exception $e) {
                $this->respond(false, 'Failed to upload evidence: ' . $e->getMessage(), 400);
                return;
            }
        }
        
        // Create dispute
        $disputeId = $this->disputeModel->create([
            'parcel_id' => $parcel['id'],
            'complainant_id' => $user['id'],
            'respondent_id' => $respondentId,
            'dispute_type' => $disputeType,
            'description' => $description,
            'evidence_ipfs_hash' => $evidenceHash
        ]);
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'dispute_filed',
            '⚖️ New Dispute Filed',
            "Dispute filed for parcel \"{$parcel['title']}\" ({$parcelNumber}) by {$user['full_name']}.",
            $disputeId,
            'dispute'
        );
        
        // Notify respondent
        if ($respondentId) {
            $this->notifications->send(
                $respondentId,
                'dispute_filed_against',
                '⚖️ Dispute Filed Against You',
                "A dispute has been filed against you regarding parcel \"{$parcel['title']}\".",
                $disputeId,
                'dispute'
            );
        }
        
        $this->respond(true, [
            'dispute_id' => $disputeId,
            'message' => '✅ Dispute filed successfully. Parcel has been flagged as disputed. An admin will review your case.'
        ]);
    }
    
    /**
     * POST /api/disputes/vote (Validator)
     */
    public function vote(): void {
        $voter = $this->auth->requireValidator();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['dispute_id']) || empty($data['vote'])) {
            $this->respond(false, 'Dispute ID and vote required', 400);
            return;
        }
        
        $this->disputeModel->addVote($data['dispute_id'], $voter['id'], $data['vote'], $data['notes'] ?? null);
        
        $this->respond(true, ['message' => 'Vote recorded']);
    }
    
    /**
     * POST /api/disputes/resolve (Admin - BLOCKCHAIN REQUIRED if ownership changes)
     */
    public function resolve(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['dispute_id']) || empty($data['status']) || empty($data['outcome'])) {
            $this->respond(false, 'Dispute ID, status, and outcome required', 400);
            return;
        }
        
        $dispute = $this->disputeModel->findById($data['dispute_id']);
        if (!$dispute) {
            $this->respond(false, 'Dispute not found', 404);
            return;
        }

        $parcel = $this->parcelModel->findById($dispute['parcel_id']);
        if (!$parcel || !$parcel['document_hash']) {
             $this->respond(false, 'Parcel or document hash not found', 404);
             return;
        }

        // ═══════════════════════════════════════════════
        // BLOCKCHAIN INTERACTION (IF OWNERSHIP CHANGES)
        // ═══════════════════════════════════════════════
        
        $txHash = $data['tx_hash'] ?? null;
        $newOwnerId = $dispute['complainant_id']; // Default to complainant

        if (!empty($data['new_owner_email'])) {
            $newOwner = $this->userModel->findByEmail($data['new_owner_email']);
            if ($newOwner) $newOwnerId = $newOwner['id'];
        }

        if ($data['outcome'] === 'ownership_changed' && $this->blockchain->isEnabled()) {
            
            // ✅ GET EXISTING WALLET
            $newOwnerWallet = $this->userModel->getWalletAddress($newOwnerId);
            
            if (!$newOwnerWallet) {
                // Fallback: generate if missing
                $newOwnerWallet = $this->generateWalletForUser($newOwnerId);
            }

            if (empty($txHash)) {
                // Return metadata for blockchain call
                $this->respond(true, [
                    'status' => 'pending_blockchain',
                    'document_hash' => $parcel['document_hash'],
                    'new_owner_wallet' => $newOwnerWallet,
                    'dispute_id' => $data['dispute_id'],
                    'message' => 'Blockchain transaction required for ownership change.'
                ]);
                return;
            }
        }
        
        // Resolve dispute
        $this->disputeModel->resolve(
            $data['dispute_id'],
            $admin['id'],
            $data['status'],
            $data['outcome'],
            $data['notes'] ?? '',
            $txHash,
            $newOwnerId
        );
        
        // NOTIFY COMPLAINANT
        $this->notifications->send(
            $dispute['complainant_id'],
            'dispute_resolved',
            '✅ Dispute Resolved',
            "Dispute #{$dispute['id']} for parcel \"{$dispute['parcel_title']}\" has been resolved. Outcome: " . str_replace('_', ' ', $data['outcome']),
            $dispute['id'],
            'dispute'
        );
        
        // NOTIFY RESPONDENT (if exists)
        if ($dispute['respondent_id']) {
            $this->notifications->send(
                $dispute['respondent_id'],
                'dispute_resolved',
                '✅ Dispute Resolved',
                "Dispute #{$dispute['id']} involving parcel \"{$dispute['parcel_title']}\" has been resolved.",
                $dispute['id'],
                'dispute'
            );
        }
        
        // NOTIFY OTHER ADMINS
        $this->notifications->sendToAdmins(
            'dispute_resolved_admin',
            'Dispute Resolved',
            "Dispute #{$dispute['id']} resolved by {$admin['full_name']}. Outcome: " . str_replace('_', ' ', $data['outcome']),
            $dispute['id'],
            'dispute'
        );
        
        // If ownership changed, notify new owner
        if ($data['outcome'] === 'ownership_changed' && $newOwnerId) {
            $this->notifications->send(
                $newOwnerId,
                'ownership_updated_dispute',
                '🏠 Ownership Updated',
                "Following dispute resolution, you are now the registered owner of \"{$parcel['title']}\" ({$parcel['parcel_number']}).",
                $dispute['id'],
                'dispute'
            );
        }
        
        // Log audit
        $db = Database::getConnection();
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes, blockchain_tx) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$admin['id'], 'dispute_resolved', 'dispute', $dispute['id'], "Outcome: {$data['outcome']}", $txHash]);
        
        $this->respond(true, [
            'message' => '✅ Dispute resolved successfully' . ($txHash ? ' and recorded on blockchain' : ''),
            'blockchain_tx' => $txHash,
            'dispute_id' => $dispute['id']
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
    
    /**
     * GET /api/disputes/all
     */
    public function all(): void {
        $user = $this->auth->requireAuth();
        try {
            $disputes = $this->disputeModel->getAll();
            $this->respond(true, $disputes);
        } catch (Exception $e) {
            $this->respond(false, 'Error: ' . $e->getMessage(), 500);
        }
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
