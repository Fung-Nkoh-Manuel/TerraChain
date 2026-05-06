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
     * User submits land registration - VALIDATION HAPPENS HERE
     */
    public function submit(): void {
        $user = $this->auth->requireAuth();
        
        // Check KYC
        $kycModel = new KYC();
        $kyc = $kycModel->getUserKYC($user['id']);
        if (!$kyc || $kyc['status'] !== 'verified') {
            $this->respond(false, 'KYC verification required before registering land', 403);
            return;
        }
        
        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location_address'] ?? '');
        $sizeSqm = $_POST['size_sqm'] ?? null;
        $propertyType = $_POST['property_type'] ?? 'residential';
        $description = trim($_POST['description'] ?? '');
        $gpsCoordinates = trim($_POST['gps_coordinates'] ?? '');
        
        if (empty($title) || empty($location)) {
            $this->respond(false, 'Title and location are required', 400);
            return;
        }
        
        // Parse GPS
        $gpsLat = null;
        $gpsLng = null;
        if (!empty($gpsCoordinates)) {
            $parts = preg_split('/[,\s]+/', $gpsCoordinates);
            if (count($parts) >= 2) {
                $gpsLat = floatval($parts[0]);
                $gpsLng = floatval($parts[1]);
            }
        }
        
        // ═══════════════════════════════════════════════
        // STEP 1: Compute hashes from temp files (FAST)
        // ═══════════════════════════════════════════════
        
        $hashes = [];
        if (!empty($_FILES['documents'])) {
            $files = $this->normalizeFilesArray($_FILES['documents']);
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) continue;
                $hash = hash_file('sha256', $file['tmp_name']);
                $hashes[] = $hash;
            }
        }
        
        $documentHash = !empty($hashes) ? $this->docService->combineHashes($hashes) : null;
        
        // ═══════════════════════════════════════════════
        // STEP 2: Check duplicates BEFORE IPFS upload
        // ═══════════════════════════════════════════════
        
        $db = Database::getConnection();
        
        if (!empty($documentHash)) {
            $dupDoc = $db->prepare("SELECT id, parcel_number, title FROM parcels WHERE document_hash = ? AND status IN ('owned', 'pending', 'transferred')");
            $dupDoc->execute([$documentHash]);
            $dup = $dupDoc->fetch();
            if ($dup) {
                $this->respond(false, "❌ DUPLICATE DOCUMENTS: These exact documents are already registered under parcel {$dup['parcel_number']} - \"{$dup['title']}\".\n\nYou cannot register the same land twice with the same documents.", 409);
                return;
            }
        }
        
        // Check duplicate location address
        if (!empty($location)) {
            $dupLoc = $db->prepare('SELECT id, parcel_number, title FROM parcels WHERE location_address = ? AND status IN ("owned", "pending", "transferred")');
            $dupLoc->execute([$location]);
            $dupLocation = $dupLoc->fetch();
            if ($dupLocation) {
                $this->respond(false, "❌ DUPLICATE LOCATION: \"{$location}\" is already registered under parcel {$dupLocation['parcel_number']} - \"{$dupLocation['title']}\".\n\nThis address is already in the system.", 409);
                return;
            }
        }
        
        // ═══════════════════════════════════════════════
        // STEP 3: No duplicates — NOW upload to IPFS
        // ═══════════════════════════════════════════════
        
        $ipfsHash = null;
        if (!empty($_FILES['documents'])) {
            $files = $this->normalizeFilesArray($_FILES['documents']);
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) continue;
                try {
                    $result = $this->docService->processUpload($file, true);
                    if (!$ipfsHash && !empty($result['ipfs_hash'])) {
                        $ipfsHash = $result['ipfs_hash'];
                    }
                } catch (Exception $e) {
                    error_log('Upload error: ' . $e->getMessage());
                }
            }
        }
        
        // Check 3: GPS coordinate overlap
        if ($gpsLat && $gpsLng) {
            $existingParcels = $db->prepare('SELECT id, parcel_number, title, coordinates_json FROM parcels WHERE coordinates_json IS NOT NULL AND status IN ("owned", "pending", "transferred")');
            $existingParcels->execute();
            
            while ($existing = $existingParcels->fetch()) {
                $exCoords = json_decode($existing['coordinates_json'], true);
                if ($exCoords && isset($exCoords['coordinates'])) {
                    $exLat = $exCoords['coordinates'][1] ?? null;
                    $exLng = $exCoords['coordinates'][0] ?? null;
                    
                    if ($exLat && $exLng) {
                        $latDiff = abs($gpsLat - $exLat);
                        $lngDiff = abs($gpsLng - $exLng);
                        
                        // Within ~50 meters
                        if ($latDiff < 0.0005 && $lngDiff < 0.0005) {
                            $this->respond(false, "❌ LOCATION OVERLAP: The GPS coordinates ({$gpsLat}, {$gpsLng}) overlap with existing parcel {$existing['parcel_number']} - \"{$existing['title']}\".\n\nThis location is too close to an already registered parcel.", 409);
                            return;
                        }
                    }
                }
            }
        }
        
        // ═══════════════════════════════════════════════════
        // ALL CHECKS PASSED - SAVE
        // ═══════════════════════════════════════════════════
        
        $coordinatesJson = null;
        if ($gpsLat && $gpsLng) {
            $coordinatesJson = json_encode(['type' => 'Point', 'coordinates' => [$gpsLng, $gpsLat]]);
        }
        
        $parcelId = $this->parcelModel->create([
            'title' => $title,
            'location_address' => $location,
            'size_sqm' => $sizeSqm ? floatval($sizeSqm) : null,
            'property_type' => $propertyType,
            'description' => $description,
            'gps_lat' => $gpsLat,
            'gps_lng' => $gpsLng,
            'coordinates_json' => $coordinatesJson,
            'owner_id' => $user['id'],
            'document_hash' => $documentHash,
            'ipfs_hash' => $ipfsHash ?? ''
        ]);
        
        $db->prepare('INSERT INTO pending_registrations (applicant_id, parcel_id) VALUES (?, ?)')->execute([$user['id'], $parcelId]);
        $regId = $db->lastInsertId();
        
        if (!empty($hashes)) {
            foreach ($hashes as $hash) {
                $db->prepare('INSERT INTO parcel_documents (parcel_id, sha256_hash, ipfs_hash, uploaded_by) VALUES (?, ?, ?, ?)')->execute([$parcelId, $hash, $ipfsHash, $user['id']]);
            }
        }
        
        $this->notifications->sendToAdmins('registration_submitted', 'New Land Registration', "Parcel \"{$title}\" submitted by {$user['full_name']}", $parcelId, 'parcel');
        
        $db->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, notes) VALUES (?, ?, ?, ?, ?)')->execute([$user['id'], 'registration_submitted', 'parcel', $parcelId, "Title: {$title}"]);
        
        $this->respond(true, [
            'parcel_id' => $parcelId,
            'registration_id' => $regId,
            'parcel_number' => $this->parcelModel->findById($parcelId)['parcel_number'] ?? null,
            'message' => '✅ Registration submitted successfully! Awaiting admin review.',
            'documents_uploaded' => count($hashes),
            'ipfs_uploaded' => !empty($ipfsHash)
        ]);
    }

    /**
     * Normalize PHP's weird $_FILES array structure
     */
    private function normalizeFilesArray(array $files): array {
        $normalized = [];
        
        if (isset($files['name']) && is_array($files['name'])) {
            // Multiple files
            foreach ($files['name'] as $key => $name) {
                $normalized[] = [
                    'name' => $name,
                    'tmp_name' => $files['tmp_name'][$key] ?? '',
                    'size' => $files['size'][$key] ?? 0,
                    'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                    'type' => $files['type'][$key] ?? '',
                ];
            }
        } elseif (isset($files['name'])) {
            // Single file
            $normalized[] = $files;
        }
        
        return $normalized;
    }
    
    /**
     * POST /api/parcels/approve (Admin only - BLOCKCHAIN REQUIRED)
     */
    public function approve(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['registration_id'])) {
            $this->respond(false, 'Registration ID required', 400);
            return;
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT r.*, p.document_hash, p.owner_id, p.title, p.id as parcel_id, p.parcel_number FROM pending_registrations r JOIN parcels p ON r.parcel_id = p.id WHERE r.id = ?');
        $stmt->execute([$data['registration_id']]);
        $reg = $stmt->fetch();
        
        if (!$reg) {
            $this->respond(false, 'Registration not found', 404);
            return;
        }
        
    // ✅ GET EXISTING WALLET (already created at registration)
    $ownerWallet = $this->userModel->getWalletAddress($reg['owner_id']);
    
    if (!$ownerWallet) {
        // Fallback: generate if somehow missing (shouldn't happen)
        $ownerWallet = $this->generateWalletForUser($reg['owner_id']);
    }
        
        // Check for tx_hash from frontend
        $txHash = $data['tx_hash'] ?? null;
        
        if (empty($txHash)) {
            // Return data needed for blockchain call
            $this->respond(true, [
                'status' => 'pending_blockchain',
                'document_hash' => $reg['document_hash'],
                'wallet_used' => $ownerWallet,
                'registration_id' => (int)$data['registration_id']
            ]);
            return;
        }
        
        // TX hash provided - save to database
        $this->parcelModel->approveRegistration($data['registration_id'], $admin['id'], $txHash);
        
        $this->notifications->send($reg['applicant_id'], 'registration_approved', '✅ Registration Approved', "Your parcel \"{$reg['title']}\" has been approved and recorded on blockchain.", $reg['parcel_id'], 'parcel');
        
        $this->respond(true, ['message' => 'Approved and recorded on blockchain', 'blockchain_tx' => $txHash]);
    }

    /**
     * POST /api/parcels/update-blockchain
     * Update blockchain tx hash after successful contract call
     */
    public function updateBlockchain(): void {
        $admin = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['registration_id']) || empty($data['tx_hash'])) {
            $this->respond(false, 'Registration ID and tx_hash required', 400);
            return;
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE parcels SET blockchain_tx_hash = ? WHERE id = (SELECT parcel_id FROM pending_registrations WHERE id = ?)');
        $stmt->execute([$data['tx_hash'], $data['registration_id']]);
        
        $this->respond(true, ['message' => 'Blockchain tx updated']);
    }

    /**
     * Verify a transaction on-chain
     */
    private function verifyTransactionOnChain(string $txHash): bool {
        // Use ethers.js or a blockchain explorer API to verify
        // For now, check if the hash format is valid
        if (preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            return true; // Format is valid
        }
        return false;
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
     * POST /api/parcels/reject (Admin/Validator)
     */
    public function reject(): void {
        $reviewer = $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['registration_id']) || empty($data['reason'])) {
            $this->respond(false, 'Registration ID and reason required', 400);
            return;
        }
        
        // Get registration details BEFORE rejecting
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.*, p.title, p.parcel_number, p.id as parcel_id
            FROM pending_registrations r 
            JOIN parcels p ON r.parcel_id = p.id 
            WHERE r.id = ?
        ');
        $stmt->execute([$data['registration_id']]);
        $reg = $stmt->fetch();
        
        if (!$reg) {
            $this->respond(false, 'Registration not found', 404);
            return;
        }
        
        // Reject in database
        $this->parcelModel->rejectRegistration(
            $data['registration_id'], 
            $reviewer['id'], 
            $data['reason']
        );
        
        // ✅ NOTIFY THE APPLICANT
        $this->notifications->send(
            $reg['applicant_id'],
            'registration_rejected',
            '❌ Registration Rejected',
            "Your land registration for \"{$reg['title']}\" ({$reg['parcel_number']}) was rejected. Reason: {$data['reason']}",
            $reg['parcel_id'],
            'parcel'
        );
        
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
        $this->auth->requireAdmin();
        $pending = $this->parcelModel->getPendingRegistrations();
        $this->respond(true, $pending);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}