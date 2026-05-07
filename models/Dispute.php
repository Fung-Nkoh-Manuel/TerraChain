<?php
// models/Dispute.php

class Dispute {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO disputes (parcel_id, complainant_id, respondent_id, dispute_type, description, evidence_ipfs_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['parcel_id'],
            $data['complainant_id'],
            $data['respondent_id'] ?? null,
            $data['dispute_type'] ?? 'ownership',
            $data['description'],
            $data['evidence_ipfs_hash'] ?? null
        ]);
        $disputeId = (int)$this->db->lastInsertId();
        
        // Flag parcel
        $this->db->prepare('UPDATE parcels SET status = "disputed" WHERE id = ?')->execute([$data['parcel_id']]);
        
        return $disputeId;
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('
            SELECT d.*, p.title as parcel_title, p.parcel_number, p.location_address,
                   c.full_name as complainant_name, r.full_name as respondent_name
            FROM disputes d 
            JOIN parcels p ON d.parcel_id = p.id 
            JOIN users c ON d.complainant_id = c.id 
            LEFT JOIN users r ON d.respondent_id = r.id 
            WHERE d.id = ?
        ');
        $stmt->execute([$id]);
        $dispute = $stmt->fetch();
        
        if ($dispute) {
            $votes = $this->db->prepare('SELECT dv.*, u.full_name FROM dispute_votes dv JOIN users u ON dv.voter_id = u.id WHERE dv.dispute_id = ?');
            $votes->execute([$id]);
            $dispute['votes'] = $votes->fetchAll();
        }
        
        return $dispute ?: null;
    }
    
    public function getAll(): array {
        $stmt = $this->db->query('
            SELECT d.*, p.title as parcel_title, p.parcel_number,
                   c.full_name as complainant_name
            FROM disputes d 
            JOIN parcels p ON d.parcel_id = p.id 
            JOIN users c ON d.complainant_id = c.id 
            ORDER BY d.created_at DESC
        ');
        return $stmt->fetchAll();
    }
    
    public function addVote(int $disputeId, int $voterId, string $vote, ?string $notes = null): void {
        $stmt = $this->db->prepare('INSERT INTO dispute_votes (dispute_id, voter_id, vote, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$disputeId, $voterId, $vote, $notes]);
    }
    
    public function resolve(int $disputeId, int $resolverId, string $status, string $outcome, string $notes, ?string $txHash = null, ?int $newOwnerId = null): void {
        $this->db->beginTransaction();
        try {
            // Update dispute record
            $this->db->prepare('UPDATE disputes SET status = ?, outcome = ?, resolution_notes = ?, resolved_by = ?, resolved_at = NOW(), blockchain_tx_hash = ? WHERE id = ?')
                ->execute([$status, $outcome, $notes, $resolverId, $txHash, $disputeId]);
            
            // Get the dispute to find the parcel
            $stmt = $this->db->prepare('SELECT parcel_id FROM disputes WHERE id = ?');
            $stmt->execute([$disputeId]);
            $dispute = $stmt->fetch();
            
            if (!$dispute) {
                throw new Exception('Dispute not found');
            }
            
            $parcelId = $dispute['parcel_id'];
            
            // Handle ownership change
            if ($outcome === 'ownership_changed' && $newOwnerId) {
                // ✅ Transfer ownership to the new owner
                $this->db->prepare('UPDATE parcels SET owner_id = ?, status = "owned", blockchain_tx_hash = COALESCE(?, blockchain_tx_hash), updated_at = NOW() WHERE id = ?')
                    ->execute([$newOwnerId, $txHash, $parcelId]);
            } else {
                // ✅ No ownership change — just unlock the parcel (remove disputed status)
                $this->db->prepare('UPDATE parcels SET status = "owned", updated_at = NOW() WHERE id = ? AND status = "disputed"')
                    ->execute([$parcelId]);
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}