<?php
// models/Transfer.php

class Transfer {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO transfers (parcel_id, sender_id, recipient_id, transfer_type, supporting_doc_hash, supporting_ipfs) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['parcel_id'],
            $data['sender_id'],
            $data['recipient_id'],
            $data['transfer_type'] ?? 'sale',
            $data['doc_hash'] ?? null,
            $data['ipfs_hash'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('
            SELECT t.*, p.title as parcel_title, p.parcel_number, p.location_address,
                   s.full_name as sender_name, s.email as sender_email,
                   r.full_name as recipient_name, r.email as recipient_email
            FROM transfers t 
            JOIN parcels p ON t.parcel_id = p.id 
            JOIN users s ON t.sender_id = s.id 
            JOIN users r ON t.recipient_id = r.id 
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function getUserTransfers(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT t.*, p.title as parcel_title, p.parcel_number,
                   s.full_name as sender_name, r.full_name as recipient_name
            FROM transfers t 
            JOIN parcels p ON t.parcel_id = p.id 
            JOIN users s ON t.sender_id = s.id 
            JOIN users r ON t.recipient_id = r.id 
            WHERE t.sender_id = ? OR t.recipient_id = ? 
            ORDER BY t.created_at DESC
        ');
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }
    
    public function getAll(): array {
        $stmt = $this->db->query('
            SELECT t.*, p.title as parcel_title, p.parcel_number,
                   s.full_name as sender_name, r.full_name as recipient_name
            FROM transfers t 
            JOIN parcels p ON t.parcel_id = p.id 
            JOIN users s ON t.sender_id = s.id 
            JOIN users r ON t.recipient_id = r.id 
            ORDER BY t.created_at DESC
        ');
        return $stmt->fetchAll();
    }
    
    public function approve(int $transferId, int $reviewerId, ?string $txHash = null): void {
        $this->db->beginTransaction();
        try {
            $transfer = $this->findById($transferId);
            
            // Update transfer
            $this->db->prepare('UPDATE transfers SET status = "approved", reviewed_by = ?, reviewed_at = NOW(), blockchain_tx_hash = ? WHERE id = ?')
                ->execute([$reviewerId, $txHash, $transferId]);
            
            // Update parcel ownership
            $this->db->prepare('UPDATE parcels SET owner_id = ?, status = "owned", blockchain_tx_hash = COALESCE(?, blockchain_tx_hash), updated_at = NOW() WHERE id = ?')
                ->execute([$transfer['recipient_id'], $txHash, $transfer['parcel_id']]);
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function reject(int $transferId, int $reviewerId, string $reason): void {
        $this->db->prepare('UPDATE transfers SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE id = ?')
            ->execute([$reviewerId, $reason, $transferId]);
    }
}