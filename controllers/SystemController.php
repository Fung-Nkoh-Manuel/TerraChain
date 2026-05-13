<?php
// controllers/SystemController.php

class SystemController {
    private AuthMiddleware $auth;
    private MailService $mail;

    public function __construct() {
        $this->auth = new AuthMiddleware();
        $this->mail = new MailService();
    }

    /**
     * GET /api/public/stats
     */
    public function getStats(): void {
        try {
            $db = Database::getConnection();
            
            // 1. Count Registered Parcels
            $stmtParcels = $db->query("SELECT COUNT(*) as count FROM parcels WHERE status IN ('owned', 'transferred', 'pending')");
            $parcelsCount = $stmtParcels->fetch()['count'] ?? 0;
            
            // 2. Count Verified Users
            $stmtUsers = $db->query("SELECT COUNT(*) as count FROM kyc_records WHERE status = 'verified'");
            $usersCount = $stmtUsers->fetch()['count'] ?? 0;
            
            $this->respond(true, [
                'parcels' => (int)$parcelsCount,
                'users' => (int)$usersCount
            ]);
        } catch (Exception $e) {
            $this->respond(false, 'Failed to fetch stats', 500);
        }
    }

    /**
     * POST /api/public/contact
     */
    public function contact(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
            $this->respond(false, 'Please fill in all required fields', 400);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Invalid email address', 400);
            return;
        }

        $subject = "📩 New TerraChain Inquiry: " . ($data['subject'] ?? 'General');
        $htmlBody = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; background: #0a0e14; color: #ffffff; padding: 30px; border-radius: 16px; border: 1px solid #1a1f26;'>
            <h2 style='color: #00e5a0; margin-top: 0;'>New Contact Message</h2>
            <div style='background: #1a1f26; padding: 20px; border-radius: 12px; margin: 20px 0;'>
                <p><strong>From:</strong> {$data['name']}</p>
                <p><strong>Email:</strong> {$data['email']}</p>
                <p><strong>Topic:</strong> " . ($data['subject'] ?? 'General') . "</p>
                <hr style='border: 0; border-top: 1px solid #2d3748; margin: 20px 0;'>
                <p style='white-space: pre-wrap; line-height: 1.6;'>{$data['message']}</p>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Received via TerraChain Contact Form on " . date('Y-m-d H:i:s') . "</p>
        </div>
        ";

        // Send to yourself
        $success = $this->mail->sendGenericEmail(SMTP_FROM, $subject, $htmlBody);

        if ($success) {
            $this->respond(true, ['message' => 'Message sent successfully!']);
        } else {
            $this->respond(false, 'Failed to send message. Please try again later.', 500);
        }
    }

    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
