<?php
// models/User.php

class User {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }
    
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT id, username, email, full_name, phone, national_id, role, wallet_address, created_at FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash, full_name, phone, national_id, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['full_name'] ?? null,
            $data['phone'] ?? null,
            $data['national_id'] ?? null,
            $data['role'] ?? 'user'
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function assignWalletAddress(int $userId, string $walletAddress): void {
        $stmt = $this->db->prepare('UPDATE users SET wallet_address = ? WHERE id = ?');
        $stmt->execute([strtolower($walletAddress), $userId]);
    }
    
    public function getWalletAddress(int $userId): ?string {
        $stmt = $this->db->prepare('SELECT wallet_address FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row['wallet_address'] ?? null;
    }
    
    public function updateProfile(int $userId, array $data): void {
        $stmt = $this->db->prepare('UPDATE users SET full_name = ?, phone = ?, national_id = ?, email = ? WHERE id = ?');
        $stmt->execute([$data['full_name'], $data['phone'], $data['national_id'], $data['email'], $userId]);
    }
    
    public function getAdmins(): array {
        $stmt = $this->db->query('SELECT id, username, email, wallet_address FROM users WHERE role = "admin" AND is_active = 1');
        return $stmt->fetchAll();
    }
    
    public function getValidators(): array {
        $stmt = $this->db->query('SELECT id, username, email, wallet_address FROM users WHERE role IN ("admin", "validator") AND is_active = 1');
        return $stmt->fetchAll();
    }
    
    public function isAdmin(int $userId): bool {
        $user = $this->findById($userId);
        return $user && $user['role'] === 'admin';
    }
}