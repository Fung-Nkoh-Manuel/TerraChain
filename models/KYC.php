<?php
// models/KYC.php

class KYC {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function submit(int $userId, string $documentHash, string $ipfsHash, ?string $blockchainTxHash = null): int {
        // Check existing
        $existing = $this->getUserKYC($userId);
        
        if ($existing) {
            $stmt = $this->db->prepare('UPDATE kyc_records SET document_hash = ?, ipfs_hash = ?, status = "pending", submitted_at = NOW(), blockchain_tx_hash = COALESCE(?, blockchain_tx_hash) WHERE user_id = ?');
            $stmt->execute([$documentHash, $ipfsHash, $blockchainTxHash, $userId]);
            return $existing['id'];
        }
        
        $stmt = $this->db->prepare('INSERT INTO kyc_records (user_id, document_hash, ipfs_hash) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $documentHash, $ipfsHash]);
        return (int)$this->db->lastInsertId();
    }
    
    public function getUserKYC(int $userId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM kyc_records WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
    
    public function getPendingKYC(): array {
        $stmt = $this->db->query('
            SELECT k.*, u.username, u.email, u.full_name, u.national_id 
            FROM kyc_records k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.status = "pending" 
            ORDER BY k.submitted_at ASC
        ');
        return $stmt->fetchAll();
    }
    
    public function verify(int $kycId, int $reviewerId, bool $approved, ?string $reason = null): void {
        $status = $approved ? 'verified' : 'rejected';
        $stmt = $this->db->prepare('UPDATE kyc_records SET status = ?, verified_at = NOW(), verified_by = ?, rejection_reason = ? WHERE id = ?');
        $stmt->execute([$status, $reviewerId, $reason, $kycId]);
    }
    
    public function isVerified(int $userId): bool {
        $kyc = $this->getUserKYC($userId);
        return $kyc && $kyc['status'] === 'verified';
    }
}