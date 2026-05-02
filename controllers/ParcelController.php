<?php
// controllers/ParcelController.php

class ParcelController {
    private Parcel $parcelModel;
    private User $userModel;
    private DocumentService $docService;
    private BlockchainService $blockchain;
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->parcelModel = new Parcel();
        $this->userModel = new User();
        $this->docService = new DocumentService();
        $this->blockchain = new BlockchainService();
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * POST /api/parcels/submit
     * User submits land registration (off-chain)
     */
    public function submit(): void {
        $user = $this->auth->requireAuth();
        
        // Check KYC
        $kycModel = new KYC();
        $kyc = $kycModel->getUserKYC($user['id']);
        if (!$kyc || $kyc['status'] !== 'verified') {
            $this->respond(false, 'KYC verification required before registering land', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title']) || empty($data['location_address'])) {
            $this->respond(false, 'Title and location are required', 400);
        }
        
        // Process any uploaded documents first
        $documentHash = null;
        $ipfsHash = null;
        $hashes = [];
        
        if (!empty($_FILES['documents'])) {
            foreach ($_FILES['documents']['tmp_name'] as $i => $tmpName) {
                $fileInfo = [
                    'name' => $_FILES['documents']['name'][$i],
                    'tmp_name' => $tmpName,
                    'size' => $_FILES['documents']['size'][$i],
                ];
                $result = $this->docService->processUpload($fileInfo);
                $hashes[] = $result['sha256'];
            }
            $documentHash = $this->docService->combineHashes($hashes);
            $ipfsHash = $result['ipfs_hash'] ?? null;
        }
        
        // Create parcel (off-chain - pending status)
        $parcelId = $this->parcelModel->create([
            'title' => $data['title'],
            'location_address' => $data['location_address'],
            'size_sqm' => $data['size_sqm'] ?? null,
            'property_type' => $data['property_type'] ?? 'residential',
            'description' => $data['description'] ?? null,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'coordinates_json' => $data['coordinates_json'] ?? null,
            'owner_id' => $user['id'],
            'document_hash' => $documentHash,
            'ipfs_hash' => $ipfsHash
        ]);
        
        // Create pending registration record
        $db = Database::getConnection();
        $stmt = $db->prepare('INSERT INTO pending_registrations (applicant_id, parcel_id) VALUES (?, ?)');
        $stmt->execute([$user['id'], $parcelId]);
        
        // Save document records
        if (!empty($hashes)) {
            foreach ($hashes as $hash) {
                $stmt = $db->prepare('INSERT INTO parcel_documents (parcel_id, sha256_hash, ipfs_hash, uploaded_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$parcelId, $hash, $ipfsHash, $user['id']]);
            }
        }
        
        // Notify admins
        $this->notifications->sendToAdmins(
            'registration_submitted',
            'New Land Registration',
            "Parcel \"{$data['title']}\" submitted by {$user['full_name']}",
            $parcelId,
            'parcel'
        );
        
        $this->respond(true, [
            'parcel_id' => $parcelId,
            'message' => 'Registration submitted. Awaiting admin review.'
        ]);
    }
    
    /**
     * POST /api/parcels/approve (Admin only - triggers blockchain if enabled)
     */
    public function approve(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['registration_id'])) {
            $this->respond(false, 'Registration ID required', 400);
        }
        
        // Get registration details
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.*, p.document_hash, p.owner_id, p.title 
            FROM pending_registrations r 
            JOIN parcels p ON r.parcel_id = p.id 
            WHERE r.id = ?
        ');
        $stmt->execute([$data['registration_id']]);
        $reg = $stmt->fetch();
        
        if (!$reg) {
            $this->respond(false, 'Registration not found', 404);
        }
        
        // ─── BLOCKCHAIN INTERACTION (admin-only) ───
        $txHash = null;
        
        if ($this->blockchain->isEnabled() && $reg['document_hash']) {
            
            // STEP 1: Check if user has a wallet address
            $ownerWallet = $this->userModel->getWalletAddress($reg['owner_id']);
            
            // STEP 2: If no wallet, GENERATE ONE for the user
            if (!$ownerWallet) {
                $ownerWallet = $this->generateWalletForUser($reg['owner_id']);
            }
            
            // STEP 3: Now we have a wallet, record on blockchain
            $result = $this->blockchain->recordLandRegistration(
                $reg['document_hash'], 
                $ownerWallet
            );
            
            if ($result['success']) {
                $txHash = $result['tx_hash'];
            } else {
                // Log error but continue with MySQL approval
                error_log('Blockchain recording failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        // Approve in database (always do this, with or without blockchain)
        $this->parcelModel->approveRegistration(
            $data['registration_id'], 
            $admin['id'], 
            $txHash
        );
        
        // Notify applicant
        $this->notifications->send(
            $reg['applicant_id'],
            'registration_approved',
            'Registration Approved ✓',
            "Your land parcel \"{$reg['title']}\" has been approved and recorded.",
            $reg['parcel_id'],
            'parcel'
        );
        
        $this->respond(true, [
            'message' => 'Registration approved',
            'blockchain_tx' => $txHash,
            'wallet_generated' => !empty($ownerWallet)
        ]);
    }

    /**
     * Generate a deterministic wallet address for a user
     * This creates a unique wallet for each user based on their ID
     */
    private function generateWalletForUser(int $userId): string {
        // Create a deterministic private key from user ID + system secret
        $seed = "terrachain_user_{$userId}_" . ($_ENV['WALLET_SECRET'] ?? 'default_secret_change_me');
        $privateKey = '0x' . hash('sha256', $seed);
        
        // Derive the Ethereum address from the private key
        // In production, use a proper Ethereum library like web3.php or ethers
        $address = '0x' . substr(hash('sha256', $privateKey), 0, 40);
        
        // Save to database
        $this->userModel->assignWalletAddress($userId, $address);
        
        // Log the wallet generation
        $db = Database::getConnection();
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes) VALUES (?, ?, ?, ?, ?)')
           ->execute([$userId, 'wallet_generated', 'user', $userId, "System-generated wallet: {$address}"]);
        
        return $address;
    }
    
    /**
     * POST /api/parcels/reject (Admin/Validator)
     */
    public function reject(): void {
        $reviewer = $this->auth->requireValidator();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['registration_id']) || empty($data['reason'])) {
            $this->respond(false, 'Registration ID and reason required', 400);
        }
        
        $this->parcelModel->rejectRegistration($data['registration_id'], $reviewer['id'], $data['reason']);
        
        $this->respond(true, ['message' => 'Registration rejected']);
    }
    
    /**
     * GET /api/parcels/my
     */
    public function myParcels(): void {
        $user = $this->auth->requireAuth();
        $parcels = $this->parcelModel->getUserParcels($user['id']);
        $this->respond(true, $parcels);
    }
    
    /**
     * GET /api/parcels/all
     */
    public function allParcels(): void {
        $user = $this->auth->requireAuth();
        $parcels = $this->parcelModel->getAllActive();
        $this->respond(true, $parcels);
    }
    
    /**
     * GET /api/parcels/search?q=query
     */
    public function search(): void {
        $user = $this->auth->requireAuth();
        $query = $_GET['q'] ?? '';
        
        if (empty($query)) {
            $this->respond(false, 'Search query required', 400);
        }
        
        $results = $this->parcelModel->search($query);
        $this->respond(true, $results);
    }
    
    /**
     * GET /api/parcels/pending (Admin/Validator)
     */
    public function pending(): void {
        $this->auth->requireValidator();
        $pending = $this->parcelModel->getPendingRegistrations();
        $this->respond(true, $pending);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}