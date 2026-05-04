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
     * User files a dispute (off-chain)
     */
    public function file(): void {
        $user = $this->auth->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['parcel_number']) || empty($data['description'])) {
            $this->respond(false, 'Parcel number and description required', 400);
        }
        
        $parcel = $this->parcelModel->findByNumber($data['parcel_number']);
        if (!$parcel) {
            $this->respond(false, 'Parcel not found', 404);
        }
        
        // Process evidence
        $evidenceHash = null;
        if (!empty($_FILES['evidence'])) {
            $fileInfo = [
                'name' => $_FILES['evidence']['name'],
                'tmp_name' => $_FILES['evidence']['tmp_name'],
                'size' => $_FILES['evidence']['size'],
            ];
            $result = $this->docService->processUpload($fileInfo);
            $evidenceHash = $result['ipfs_hash'];
        }
        
        // Find respondent if specified
        $respondentId = null;
        if (!empty($data['respondent_email'])) {
            $respondent = $this->userModel->findByEmail($data['respondent_email']);
            $respondentId = $respondent ? $respondent['id'] : null;
        }
        
        $disputeId = $this->disputeModel->create([
            'parcel_id' => $parcel['id'],
            'complainant_id' => $user['id'],
            'respondent_id' => $respondentId,
            'dispute_type' => $data['dispute_type'] ?? 'ownership',
            'description' => $data['description'],
            'evidence_ipfs_hash' => $evidenceHash
        ]);
        
        // ✅ NOTIFY ADMINS
        $this->notifications->sendToAdmins(
            'dispute_filed',
            '⚖️ New Dispute Filed',
            "Dispute filed for parcel \"{$parcel['title']}\" ({$parcel['parcel_number']}) by {$user['full_name']}.",
            $disputeId,
            'dispute'
        );
        
        // ✅ NOTIFY RESPONDENT (if specified)
        if ($respondentId) {
            $this->notifications->send(
                $respondentId,
                'dispute_filed_against',
                '⚖️ Dispute Filed Against You',
                "A dispute has been filed against you regarding parcel \"{$parcel['title']}\" ({$parcel['parcel_number']}).",
                $disputeId,
                'dispute'
            );
        }
        
        // ✅ NOTIFY COMPLAINANT (confirmation)
        $this->notifications->send(
            $user['id'],
            'dispute_filed_confirmation',
            '⚖️ Dispute Filed',
            "Your dispute regarding parcel \"{$parcel['title']}\" ({$parcel['parcel_number']}) has been filed. An admin will review it.",
            $disputeId,
            'dispute'
        );
        
        $this->respond(true, [
            'dispute_id' => $disputeId,
            'message' => 'Dispute filed successfully. Parcel has been flagged as disputed.'
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
        }
        
        $this->disputeModel->addVote($data['dispute_id'], $voter['id'], $data['vote'], $data['notes'] ?? null);
        
        $this->respond(true, ['message' => 'Vote recorded']);
    }
    
    /**
     * POST /api/disputes/resolve (Admin - may trigger blockchain)
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
        
        // Blockchain interaction if ownership changes
        $txHash = null;
        $newOwnerId = null;
        
        if ($data['outcome'] === 'ownership_changed' && $this->blockchain->isEnabled()) {
            $parcel = $this->parcelModel->findById($dispute['parcel_id']);
            $newOwnerId = $dispute['complainant_id'];
            
            if (!empty($data['new_owner_email'])) {
                $newOwner = $this->userModel->findByEmail($data['new_owner_email']);
                if ($newOwner) $newOwnerId = $newOwner['id'];
            }
            
            if ($parcel && $parcel['document_hash']) {
                $newOwnerWallet = $this->userModel->getWalletAddress($newOwnerId);
                if (!$newOwnerWallet) {
                    $newOwnerWallet = $this->generateWalletForUser($newOwnerId);
                }
                
                $result = $this->blockchain->updateOwnershipDueToDispute(
                    $parcel['document_hash'],
                    $newOwnerWallet,
                    $data['notes'] ?? 'Dispute resolution'
                );
                if ($result['success']) {
                    $txHash = $result['tx_hash'];
                }
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
        
        // ✅ NOTIFY COMPLAINANT
        $this->notifications->send(
            $dispute['complainant_id'],
            'dispute_resolved',
            '✅ Dispute Resolved',
            "Dispute #{$dispute['id']} for parcel \"{$dispute['parcel_title']}\" has been resolved. Outcome: " . str_replace('_', ' ', $data['outcome']),
            $dispute['id'],
            'dispute'
        );
        
        // ✅ NOTIFY RESPONDENT (if exists)
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
        
        // ✅ NOTIFY OTHER ADMINS
        $this->notifications->sendToAdmins(
            'dispute_resolved_admin',
            'Dispute Resolved',
            "Dispute #{$dispute['id']} resolved by {$admin['full_name']}. Outcome: " . str_replace('_', ' ', $data['outcome']),
            $dispute['id'],
            'dispute'
        );
        
        // ✅ If ownership changed, notify new owner
        if ($data['outcome'] === 'ownership_changed' && $newOwnerId) {
            $parcel = $this->parcelModel->findById($dispute['parcel_id']);
            $this->notifications->send(
                $newOwnerId,
                'ownership_updated_dispute',
                '🏠 Ownership Updated',
                "Following dispute resolution, you are now the registered owner of \"{$parcel['title']}\" ({$parcel['parcel_number']}).",
                $dispute['id'],
                'dispute'
            );
        }
        
        $this->respond(true, [
            'message' => 'Dispute resolved successfully',
            'blockchain_tx' => $txHash
        ]);
    }
    
    /**
     * GET /api/disputes/all
     */
    public function all(): void {
        $user = $this->auth->requireAuth();
        $disputes = $this->disputeModel->getAll();
        $this->respond(true, $disputes);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}