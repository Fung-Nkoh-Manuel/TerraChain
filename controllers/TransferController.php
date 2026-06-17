<?php
// controllers/TransferController.php

class TransferController {
    private Transfer $transferModel;
    private Parcel $parcelModel;
    private User $userModel;
    private BlockchainService $blockchain;
    private NotificationService $notifications;
    private DocumentService $docService;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->transferModel = new Transfer();
        $this->parcelModel = new Parcel();
        $this->userModel = new User();
        $this->blockchain = new BlockchainService();
        $this->notifications = new NotificationService();
        $this->docService = new DocumentService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * POST /api/transfers/request
     */
    public function request(): void {
        $user = $this->auth->requireAuth();
        
        // Read from JSON or FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $data = $_POST;
        }
        
        $parcelNumber = trim($data['parcel_number'] ?? '');
        $recipientEmail = trim($data['recipient_email'] ?? '');
        $transferType = $data['transfer_type'] ?? 'sale';
        
        // ═══════════════════════════════════════════════
        // VALIDATION CHECKS (BEFORE ANYTHING IS SAVED)
        // ═══════════════════════════════════════════════
        
        // Check 1: Parcel number required
        if (empty($parcelNumber)) {
            $this->respond(false, 'Parcel number is required.', 400);
            return;
        }
        
        // Check 2: Recipient email required
        if (empty($recipientEmail)) {
            $this->respond(false, 'Recipient email is required.', 400);
            return;
        }
        
        // Check 3: Supporting document required
        if (empty($_FILES['supporting_doc']) || $_FILES['supporting_doc']['error'] !== UPLOAD_ERR_OK) {
            $this->respond(false, '❌ Supporting document is required for transfer. Please upload a document that proves the transfer agreement.', 400);
            return;
        }
        
        // Check 4: Find parcel
        $parcel = $this->parcelModel->findByNumber($parcelNumber);
        if (!$parcel) {
            $this->respond(false, "Parcel \"{$parcelNumber}\" not found. Please check the parcel number.", 404);
            return;
        }
        
        // Check 5: User must be the owner
        if ($parcel['owner_id'] != $user['id']) {
            $this->respond(false, 'You are not the current owner of this parcel. Only the owner can initiate a transfer.', 403);
            return;
        }
        
        // Check 6: Parcel status must be "owned"
        if ($parcel['status'] !== 'owned') {
            $this->respond(false, "This parcel has status \"{$parcel['status']}\" and cannot be transferred. Only owned parcels can be transferred.", 400);
            return;
        }
        
        // Check 7: Parcel must not be disputed
        if ($parcel['status'] === 'disputed') {
            $this->respond(false, 'This parcel is currently under dispute and cannot be transferred.', 409);
            return;
        }
        
        // Check 8: Find recipient
        $recipient = $this->userModel->findByEmail($recipientEmail);
        if (!$recipient) {
            $this->respond(false, "No user found with email \"{$recipientEmail}\". The recipient must have a TerraChain account.", 404);
            return;
        }
        
        // Check 9: Cannot transfer to yourself
        if ($recipient['id'] == $user['id']) {
            $this->respond(false, 'You cannot transfer a parcel to yourself.', 400);
            return;
        }
        
        // Check 10: Recipient must be KYC verified
        $kycModel = new KYC();
        $recipientKYC = $kycModel->getUserKYC($recipient['id']);
        if (!$recipientKYC || $recipientKYC['status'] !== 'verified') {
            $this->respond(false, "❌ Recipient \"{$recipient['full_name']}\" ({$recipientEmail}) has NOT completed KYC verification.\n\nThe recipient must verify their identity before they can receive land.", 400);
            return;
        }
        
        // Check 11: Sender must be KYC verified
        $senderKYC = $kycModel->getUserKYC($user['id']);
        if (!$senderKYC || $senderKYC['status'] !== 'verified') {
            $this->respond(false, '❌ You must complete KYC verification before initiating a transfer.', 400);
            return;
        }
        
        // ═══════════════════════════════════════════════
        // PROCESS SUPPORTING DOCUMENT
        // ═══════════════════════════════════════════════
        
        $docHash = null;
        $ipfsHash = null;
        
        if (!empty($_FILES['supporting_doc'])) {
            try {
                $fileInfo = [
                    'name' => $_FILES['supporting_doc']['name'],
                    'tmp_name' => $_FILES['supporting_doc']['tmp_name'],
                    'size' => $_FILES['supporting_doc']['size'],
                ];
                $result = $this->docService->processUpload($fileInfo);
                $docHash = $result['sha256'];
                $ipfsHash = $result['ipfs_hash'];
                
                // ═══════════════════════════════════════════════
                // NEW: DUPLICATE DOCUMENT VALIDATION
                // ═══════════════════════════════════════════════
                $db = Database::getConnection();
                
                // 1. Check if this document was already used in another transfer
                $stmtDup = $db->prepare("SELECT id FROM transfers WHERE supporting_doc_hash = ? AND status != 'rejected'");
                $stmtDup->execute([$docHash]);
                if ($stmtDup->fetch()) {
                    $this->respond(false, "❌ DUPLICATE DOCUMENT: This transfer document has already been used in another transfer request. Please upload the unique agreement for this transaction.", 409);
                    return;
                }
                
                // 2. Check if the user is trying to use the original title deed as a transfer agreement
                if ($docHash === $parcel['document_hash']) {
                    $this->respond(false, "❌ INVALID DOCUMENT: You cannot use the original Land Title as the Transfer Agreement. Please upload the Sale Agreement or Transfer Deed signed by both parties.", 400);
                    return;
                }
                
            } catch (Exception $e) {
                $this->respond(false, 'Failed to upload supporting document: ' . $e->getMessage(), 400);
                return;
            }
        }
        
        // ═══════════════════════════════════════════════
        // ALL CHECKS PASSED - CREATE TRANSFER
        // ═══════════════════════════════════════════════
        
        $transferId = $this->transferModel->create([
            'parcel_id' => $parcel['id'],
            'sender_id' => $user['id'],
            'recipient_id' => $recipient['id'],
            'transfer_type' => $transferType,
            'doc_hash' => $docHash,
            'ipfs_hash' => $ipfsHash
        ]);
        
        // Notify recipient
        $this->notifications->send(
            $recipient['id'],
            'transfer_received',
            '📋 Transfer Request Received',
            "{$user['full_name']} wants to transfer parcel \"{$parcel['title']}\" ({$parcel['parcel_number']}) to you.\n\nAwaiting admin approval.",
            $transferId,
            'transfer'
        );
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'transfer_requested',
            'New Transfer Request',
            "Transfer of \"{$parcel['title']}\" ({$parcel['parcel_number']}) from {$user['full_name']} to {$recipient['full_name']}.",
            $transferId,
            'transfer'
        );
        
        $this->respond(true, [
            'transfer_id' => $transferId,
            'message' => "✅ Transfer request submitted! \"{$parcel['title']}\" will be transferred to {$recipient['full_name']} after admin approval."
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
        
        if ($transfer['status'] !== 'pending') {
            $this->respond(false, "Transfer is already {$transfer['status']}. Cannot approve.", 400);
            return;
        }
        
        $parcel = $this->parcelModel->findById($transfer['parcel_id']);
        if (!$parcel || !$parcel['document_hash']) {
            $this->respond(false, 'Parcel or document hash not found', 404);
            return;
        }
        
        // Get wallets
        $fromWallet = $this->userModel->getWalletAddress($transfer['sender_id']);
        $toWallet = $this->userModel->getWalletAddress($transfer['recipient_id']);
        
        if (!$fromWallet || !$toWallet) {
            $this->respond(false, 'One or both users do not have wallet addresses.', 500);
            return;
        }
        
        // Check for tx_hash from frontend (MetaMask confirmation)
        $txHash = $data['tx_hash'] ?? null;
        
        if (empty($txHash)) {
            // Return data for blockchain call
            $this->respond(true, [
                'status' => 'pending_blockchain',
                'document_hash' => $parcel['document_hash'],
                'new_owner_wallet' => $toWallet,
                'transfer_id' => (int)$data['transfer_id'],
                'message' => 'Blockchain transaction required. Please confirm in MetaMask.'
            ]);
            return;
        }
        
        // ✅ BLOCKCHAIN TX PROVIDED - NOW SAVE TO DATABASE
        error_log("Finalizing transfer #{$data['transfer_id']} with TX: {$txHash}");
        $this->transferModel->approve($data['transfer_id'], $admin['id'], $txHash);
        
        // Notify both parties
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
     * GET /api/admin/get-transfer-details.php
     */
    public function getTransferDetails(): void {
        $admin = $this->auth->requireAdmin();
        
        $transferId = $_GET['id'] ?? 0;
        if (!$transferId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transfer ID required']);
            exit;
        }
        
        $transfer = $this->transferModel->getTransferWithDetails((int)$transferId);
        if ($transfer) {
            $documents = $this->transferModel->getTransferDocuments((int)$transferId);
            $transfer['documents'] = $documents;
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'transfer' => $transfer
            ]);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Transfer not found']);
            exit;
        }
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
