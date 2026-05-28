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

    /**
     * GET /api/public/metrics
     * Exports application statistics in Prometheus exposition text format.
     */
    public function getMetrics(): void {
        header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
        try {
            $db = Database::getConnection();

            // 1. Total parcels by status
            $stmtParcels = $db->query("SELECT status, COUNT(*) as count FROM parcels GROUP BY status");
            $parcels = $stmtParcels->fetchAll();
            
            $statuses = ['pending', 'owned', 'transferred', 'disputed', 'restricted', 'public_use', 'rejected'];
            $parcelCounts = array_fill_keys($statuses, 0);
            foreach ($parcels as $row) {
                if (isset($parcelCounts[$row['status']])) {
                    $parcelCounts[$row['status']] = (int)$row['count'];
                }
            }

            // 2. Pending KYC
            $stmtKyc = $db->query("SELECT COUNT(*) as count FROM kyc_records WHERE status = 'pending'");
            $kycPending = $stmtKyc->fetch()['count'] ?? 0;

            // 3. Pending Transfers
            $stmtTransfers = $db->query("SELECT COUNT(*) as count FROM transfers WHERE status = 'pending'");
            $transfersPending = $stmtTransfers->fetch()['count'] ?? 0;

            // 4. Active Disputes
            $stmtDisputes = $db->query("SELECT COUNT(*) as count FROM disputes WHERE status IN ('open', 'under_review')");
            $disputesActive = $stmtDisputes->fetch()['count'] ?? 0;

            // 5. Total Users
            $stmtUsers = $db->query("SELECT COUNT(*) as count FROM users");
            $usersTotal = $stmtUsers->fetch()['count'] ?? 0;

            // Output metrics
            echo "# HELP terrachain_parcels_total Total land parcels registered, grouped by status\n";
            echo "# TYPE terrachain_parcels_total gauge\n";
            foreach ($parcelCounts as $status => $count) {
                echo "terrachain_parcels_total{status=\"$status\"} $count\n";
            }

            echo "\n# HELP terrachain_kyc_pending_total Total pending KYC verification requests\n";
            echo "# TYPE terrachain_kyc_pending_total gauge\n";
            echo "terrachain_kyc_pending_total $kycPending\n";

            echo "\n# HELP terrachain_transfers_pending_total Total pending land transfer requests\n";
            echo "# TYPE terrachain_transfers_pending_total gauge\n";
            echo "terrachain_transfers_pending_total $transfersPending\n";

            echo "\n# HELP terrachain_disputes_active_total Total active property disputes\n";
            echo "# TYPE terrachain_disputes_active_total gauge\n";
            echo "terrachain_disputes_active_total $disputesActive\n";

            echo "\n# HELP terrachain_users_total Total registered users\n";
            echo "# TYPE terrachain_users_total gauge\n";
            echo "terrachain_users_total $usersTotal\n";

            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo "# ERROR: Failed to gather metrics: " . $e->getMessage() . "\n";
            exit;
        }
    }

    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        $response = ['success' => $success];
        if (!$success && is_string($data)) {
            $response['error'] = $data;
        } else {
            $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }
}
