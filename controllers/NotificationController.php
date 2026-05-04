<?php
// controllers/NotificationController.php

class NotificationController {
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
    }
    
    public function list(): void {
        $user = $this->auth->requireAuth();
        
        $notifications = $this->notifications->getUserNotifications($user['id']);
        $unreadCount = $this->notifications->getUnreadCount($user['id']);
        
        $this->respond(true, [
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }
    
    public function markAllRead(): void {
        $user = $this->auth->requireAuth();
        $this->notifications->markAllRead($user['id']);
        $this->respond(true, ['message' => 'All notifications marked as read']);
    }
    
    public function markReadOne(): void {
        $user = $this->auth->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            $this->respond(false, 'Notification ID required', 400);
            return;
        }
        
        $this->notifications->markRead($data['id'], $user['id']);
        $this->respond(true, ['message' => 'Notification marked as read']);
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
