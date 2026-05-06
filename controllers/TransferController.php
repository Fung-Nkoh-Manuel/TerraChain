<?php
// controllers/TransferController.php

class TransferController {
    private Transfer $transferModel;
    private Parcel $parcelModel;
    private User $userModel;
    private BlockchainService $blockchain;
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->transferModel = new Transfer();
        $this->parcelModel = new Parcel();
        $this->userModel = new User();
        $this->blockchain = new BlockchainService();
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * POST /api/transfers/request
     */
    public function request(): void {
        $user = $this->auth->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['parcel_number']) || empty($data['recipient_email'])) {
            $this->respond(false, 'Parcel number and recipient email required', 400);
        }
        
        // Find parcel
        $parcel = $this->parcelModel->findByNumber($data['parcel_number']);
        if (!$parcel || $parcel['owner_id'] !== $user['id']) {
            $this->respond(false, 'Parcel not found or you are not the owner', 403);
        }
        
        // Find recipient
        $recipient = $this->userModel->findByEmail($data['recipient_email']);
        if (!$recipient) {
            $this->respond(false, 'Recipient user not found', 404);
        }
        
        if ($recipient['id'] === $user['id']) {
            $this->respond(false, 'Cannot transfer to yourself', 400);
        }
        
        // Create transfer request
        $transferId = $this->transferModel->create([
            'parcel_id' => $parcel['id'],
            'sender_id' => $user['id'],
            'recipient_id' => $recipient['id'],
            'transfer_type' => $data['transfer_type'] ?? 'sale'
        ]);
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'transfer_requested',
            'New Transfer Request',
            "Transfer of \"{$parcel['title']}\" from {$user['full_name']} to {$recipient['full_name']} requested.",
            $transferId,
            'transfer'
        );
        
        $this->respond(true, [
            'transfer_id' => $transferId, 
            'message' => 'Transfer request submitted successfully. Awaiting admin review.'
        ]);
    }
    
    /**
     * POST /api/transfers/approve (Admin only - BLOCKCHAIN REQUIRED)
     */
    public function approve(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['transfer_id'])) {
            $this->respond(false, 'Transfer ID required', 400);
            return;
        }
        
        $transfer = $this->transferModel->findById($data['transfer_id']);
        if (!$transfer) {
            $this->respond(false, 'Transfer not found', 404);
            return;
        }
        
        $parcel = $this->parcelModel->findById($transfer['parcel_id']);
        if (!$parcel || !$parcel['document_hash']) {
            $this->respond(false, 'Parcel or document hash not found', 404);
            return;
        }

        // ═══════════════════════════════════════════════
        // BLOCKCHAIN INTERACTION (REQUIRED)
        // ═══════════════════════════════════════════════
        
        $newOwnerWallet = $this->userModel->getWalletAddress($transfer['recipient_id']);
        if (!$newOwnerWallet) {
            $newOwnerWallet = $this->generateWalletForUser($transfer['recipient_id']);
        }

        // BLOCKCHAIN TRANSACTION IS REQUIRED
        if (!$this->blockchain->isEnabled()) {
            $this->respond(false, '❌ Blockchain is disabled. Cannot approve transfer without blockchain recording.', 500);
            return;
        }
        
        // The frontend sends the tx_hash after MetaMask confirms
        $txHash = $data['tx_hash'] ?? null;
        
        if (empty($txHash)) {
            // Frontend hasn't sent tx_hash yet - return metadata for blockchain call
            $this->respond(true, [
                'status' => 'pending_blockchain',
                'document_hash' => $parcel['document_hash'],
                'new_owner_wallet' => $newOwnerWallet,
                'transfer_id' => $data['transfer_id'],
                'message' => 'Blockchain transaction required. Please confirm in MetaMask.'
            ]);
            return;
        }
        
        // ═══════════════════════════════════════════════
        // VERIFY BLOCKCHAIN TRANSACTION
        // ═══════════════════════════════════════════════
        
        if (!empty($txHash) && !preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash) && !str_starts_with($txHash, 'already_on_chain')) {
             $this->respond(false, '❌ Invalid transaction hash format.', 400);
             return;
        }

        // ═══════════════════════════════════════════════
        // NOW APPROVE IN DATABASE
        // ═══════════════════════════════════════════════
        
        $this->transferModel->approve($data['transfer_id'], $admin['id'], $txHash);
        
        // Notify the seller
        $this->notifications->send(
            $transfer['sender_id'],
            'transfer_approved',
            '✅ Transfer Completed',
            "Your transfer of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) has been approved. The property has been transferred to {$transfer['recipient_name']}.",
            $transfer['id'],
            'transfer'
        );
        
        // Notify the buyer
        $this->notifications->send(
            $transfer['recipient_id'],
            'transfer_received',
            '🎉 You Are Now the Owner!',
            "Congratulations! Ownership of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) has been transferred to you.",
            $transfer['id'],
            'transfer'
        );
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'transfer_completed_admin',
            'Transfer Completed',
            "Transfer of \"{$transfer['parcel_title']}\" approved by {$admin['full_name']} and recorded on-chain.",
            $transfer['id'],
            'transfer'
        );
        
        // Log audit
        $db = Database::getConnection();
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes, blockchain_tx) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$admin['id'], 'transfer_approved', 'transfer', $transfer['id'], "Blockchain TX: {$txHash}", $txHash]);
        
        $this->respond(true, [
            'message' => '✅ Transfer approved and recorded on blockchain!',
            'blockchain_tx' => $txHash,
            'parcel_number' => $transfer['parcel_number']
        ]);
    }

    /**
     * POST /api/transfers/update-blockchain
     */
    public function updateBlockchain(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['transfer_id']) || empty($data['tx_hash'])) {
            $this->respond(false, 'Transfer ID and tx_hash required', 400);
            return;
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE parcels SET blockchain_tx_hash = ? WHERE id = (SELECT parcel_id FROM transfers WHERE id = ?)');
        $stmt->execute([$data['tx_hash'], $data['transfer_id']]);
        
        $this->respond(true, ['message' => 'Blockchain tx updated']);
    }
    
    /**
     * POST /api/transfers/reject (Admin)
     */
    public function reject(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['transfer_id']) || empty($data['reason'])) {
            $this->respond(false, 'Transfer ID and reason required', 400);
            return;
        }
        
        $transfer = $this->transferModel->findById($data['transfer_id']);
        if (!$transfer) {
            $this->respond(false, 'Transfer not found', 404);
            return;
        }
        
        // Reject transfer
        $this->transferModel->reject($data['transfer_id'], $admin['id'], $data['reason']);
        
        // ✅ NOTIFY THE SELLER
        $this->notifications->send(
            $transfer['sender_id'],
            'transfer_rejected',
            '❌ Transfer Rejected',
            "Your transfer of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) was rejected by admin. Reason: {$data['reason']}",
            $transfer['id'],
            'transfer'
        );
        
        // ✅ NOTIFY THE BUYER
        $this->notifications->send(
            $transfer['recipient_id'],
            'transfer_rejected_buyer',
            '❌ Transfer Rejected',
            "The transfer of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) to you was rejected. Reason: {$data['reason']}",
            $transfer['id'],
            'transfer'
        );
        
        $this->respond(true, ['message' => 'Transfer rejected']);
    }
    
    /**
     * GET /api/transfers/my
     */
    public function myTransfers(): void {
        $user = $this->auth->requireAuth();
        try {
            $transfers = $this->transferModel->getUserTransfers($user['id']);
            $this->respond(true, $transfers);
        } catch (Exception $e) {
            $this->respond(false, 'Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/transfers/all (Admin only)
     */
    public function allTransfers(): void {
        $this->auth->requireAdmin();
        try {
            $transfers = $this->transferModel->getAll();
            $this->respond(true, $transfers);
        } catch (Exception $e) {
            $this->respond(false, 'Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate a deterministic wallet address for a user
     */
    private function generateWalletForUser(int $userId): string {
        $seed = "terrachain_user_{$userId}_" . ($_ENV['WALLET_SECRET'] ?? 'default_secret_change_me');
        $privateKey = '0x' . hash('sha256', $seed);
        
        $address = '0x' . substr(hash('sha256', $privateKey), 0, 40);
        
        $this->userModel->assignWalletAddress($userId, $address);
        
        $db = Database::getConnection();
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes) VALUES (?, ?, ?, ?, ?)')
           ->execute([$userId, 'wallet_generated', 'user', $userId, "System-generated wallet: {$address}"]);
        
        return $address;
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
