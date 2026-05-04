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
     * POST /api/transfers/approve (Admin only - triggers blockchain)
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
        
        // Blockchain interaction
        $txHash = null;
        if ($this->blockchain->isEnabled()) {
            $parcel = $this->parcelModel->findById($transfer['parcel_id']);
            
            if ($parcel && $parcel['document_hash']) {
                $fromWallet = $this->userModel->getWalletAddress($transfer['sender_id']);
                if (!$fromWallet) {
                    $fromWallet = $this->generateWalletForUser($transfer['sender_id']);
                }
                
                $toWallet = $this->userModel->getWalletAddress($transfer['recipient_id']);
                if (!$toWallet) {
                    $toWallet = $this->generateWalletForUser($transfer['recipient_id']);
                }
                
                $result = $this->blockchain->recordTransfer(
                    $parcel['document_hash'],
                    $fromWallet,
                    $toWallet
                );
                
                if ($result['success']) {
                    $txHash = $result['tx_hash'];
                }
            }
        }
        
        // Approve transfer
        $this->transferModel->approve($data['transfer_id'], $admin['id'], $txHash);
        
        // ✅ NOTIFY THE SELLER (previous owner)
        $this->notifications->send(
            $transfer['sender_id'],
            'transfer_approved',
            '✅ Transfer Completed',
            "Your transfer of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) has been approved. The property has been transferred to {$transfer['recipient_name']}.",
            $transfer['id'],
            'transfer'
        );
        
        // ✅ NOTIFY THE BUYER (new owner)
        $this->notifications->send(
            $transfer['recipient_id'],
            'transfer_received',
            '🎉 You Are Now the Owner!',
            "Congratulations! Ownership of \"{$transfer['parcel_title']}\" ({$transfer['parcel_number']}) has been transferred to you.",
            $transfer['id'],
            'transfer'
        );
        
        $this->respond(true, [
            'message' => 'Transfer approved and ownership updated',
            'blockchain_tx' => $txHash
        ]);
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
        $transfers = $this->transferModel->getUserTransfers($user['id']);
        $this->respond(true, $transfers);
    }
    
    /**
     * GET /api/transfers/all (Admin only)
     */
    public function allTransfers(): void {
        $this->auth->requireAdmin();
        $transfers = $this->transferModel->getAll();
        $this->respond(true, $transfers);
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
