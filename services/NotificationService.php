<?php
// services/NotificationService.php

class NotificationService {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function send(int $userId, string $type, string $title, string $message, ?int $refId = null, ?string $refType = null): void {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $type, $title, $message, $refId, $refType]);
    }
    
    public function sendToAdmins(string $type, string $title, string $message, ?int $refId = null, ?string $refType = null): void {
        $userModel = new User();
        $admins = $userModel->getAdmins();
        foreach ($admins as $admin) {
            $this->send($admin['id'], $type, $title, $message, $refId, $refType);
        }
    }
    
    public function getUserNotifications(int $userId): array {
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getUnreadCount(int $userId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function markRead(int $notificationId, int $userId): void {
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
    }
    
    public function markAllRead(int $userId): void {
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}