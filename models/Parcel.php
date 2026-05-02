<?php
// models/Parcel.php

class Parcel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function create(array $data): int {
        $parcelNumber = $this->generateParcelNumber();
        $stmt = $this->db->prepare('INSERT INTO parcels (parcel_number, title, location_address, size_sqm, property_type, description, gps_lat, gps_lng, coordinates_json, status, owner_id, document_hash, ipfs_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $parcelNumber,
            $data['title'],
            $data['location_address'],
            $data['size_sqm'] ?? null,
            $data['property_type'] ?? 'residential',
            $data['description'] ?? null,
            $data['gps_lat'] ?? null,
            $data['gps_lng'] ?? null,
            $data['coordinates_json'] ?? null,
            'pending',
            $data['owner_id'],
            $data['document_hash'] ?? null,
            $data['ipfs_hash'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT p.*, u.full_name as owner_name, u.email as owner_email FROM parcels p LEFT JOIN users u ON p.owner_id = u.id WHERE p.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function findByNumber(string $parcelNumber): ?array {
        $stmt = $this->db->prepare('SELECT p.*, u.full_name as owner_name FROM parcels p LEFT JOIN users u ON p.owner_id = u.id WHERE p.parcel_number = ?');
        $stmt->execute([$parcelNumber]);
        return $stmt->fetch() ?: null;
    }
    
    public function getUserParcels(int $userId): array {
        $stmt = $this->db->prepare('SELECT * FROM parcels WHERE owner_id = ? AND status NOT IN ("rejected") ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAllActive(): array {
        $stmt = $this->db->query('SELECT p.*, u.full_name as owner_name FROM parcels p LEFT JOIN users u ON p.owner_id = u.id WHERE p.status IN ("owned", "transferred") ORDER BY p.created_at DESC LIMIT 100');
        return $stmt->fetchAll();
    }
    
    public function search(string $query): array {
        $stmt = $this->db->prepare('SELECT p.*, u.full_name as owner_name FROM parcels p LEFT JOIN users u ON p.owner_id = u.id WHERE (p.title LIKE ? OR p.location_address LIKE ? OR p.parcel_number LIKE ?) AND p.status NOT IN ("rejected") ORDER BY p.created_at DESC');
        $like = "%{$query}%";
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }
    
    public function updateStatus(int $id, string $status, ?string $txHash = null): void {
        $stmt = $this->db->prepare('UPDATE parcels SET status = ?, blockchain_tx_hash = COALESCE(?, blockchain_tx_hash), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $txHash, $id]);
    }
    
    public function transferOwnership(int $id, int $newOwnerId, ?string $txHash = null): void {
        $stmt = $this->db->prepare('UPDATE parcels SET owner_id = ?, status = "owned", blockchain_tx_hash = COALESCE(?, blockchain_tx_hash), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newOwnerId, $txHash, $id]);
    }
    
    public function updateDocumentHash(int $id, string $docHash, string $ipfsHash): void {
        $stmt = $this->db->prepare('UPDATE parcels SET document_hash = ?, ipfs_hash = ? WHERE id = ?');
        $stmt->execute([$docHash, $ipfsHash, $id]);
    }
    
    public function getPendingRegistrations(): array {
        $stmt = $this->db->prepare('SELECT r.*, p.title, p.location_address, p.parcel_number, p.document_hash, p.ipfs_hash, u.full_name as applicant_name, u.email as applicant_email FROM pending_registrations r JOIN parcels p ON r.parcel_id = p.id JOIN users u ON r.applicant_id = u.id WHERE r.status IN ("submitted", "under_review") ORDER BY r.submitted_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function approveRegistration(int $regId, int $reviewerId, ?string $txHash = null): void {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM pending_registrations WHERE id = ?');
            $stmt->execute([$regId]);
            $reg = $stmt->fetch();
            
            $this->db->prepare('UPDATE pending_registrations SET status = "approved", reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$reviewerId, $regId]);
            $this->db->prepare('UPDATE parcels SET status = "owned", blockchain_tx_hash = COALESCE(?, blockchain_tx_hash), updated_at = NOW() WHERE id = ?')->execute([$txHash, $reg['parcel_id']]);
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function rejectRegistration(int $regId, int $reviewerId, string $reason): void {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM pending_registrations WHERE id = ?');
            $stmt->execute([$regId]);
            $reg = $stmt->fetch();
            
            $this->db->prepare('UPDATE pending_registrations SET status = "rejected", admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$reason, $reviewerId, $regId]);
            $this->db->prepare('UPDATE parcels SET status = "rejected" WHERE id = ?')->execute([$reg['parcel_id']]);
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function generateParcelNumber(): string {
        return 'TC-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Y');
    }
}