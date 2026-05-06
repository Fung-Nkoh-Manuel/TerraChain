<?php
// controllers/NotificationController.php

class NotificationController {
    private NotificationService $notifications;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->notifications = new NotificationService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * GET /api/notifications/list
     */
    public function list(): void {
        $user = $this->auth->requireAuth();
        
        try {
            $notifications = $this->notifications->getUserNotifications($user['id']);
            $unreadCount = $this->notifications->getUnreadCount($user['id']);
            
            $this->respond(true, [
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (Exception $e) {
            $this->respond(false, 'Failed to load notifications: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/notifications/read-all
     */
    public function markAllRead(): void {
        $user = $this->auth->requireAuth();
        
        try {
            $this->notifications->markAllRead($user['id']);
            $this->respond(true, ['message' => 'All notifications marked as read']);
        } catch (Exception $e) {
            $this->respond(false, 'Failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/notifications/mark-read-one
     */
    public function markReadOne(): void {
        $user = $this->auth->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            $this->respond(false, 'Notification ID required', 400);
            return;
        }
        
        try {
            $this->notifications->markRead($data['id'], $user['id']);
            $this->respond(true, ['message' => 'Marked as read']);
        } catch (Exception $e) {
            $this->respond(false, 'Failed: ' . $e->getMessage(), 500);
        }
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
