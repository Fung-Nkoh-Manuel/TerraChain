<?php
// services/MailService.php

class MailService {
    public function sendOTP($email, $otp) {
        $subject = "🔒 TerraChain Verification Code: $otp";
        
        // Professional HTML Email Template
        $message = "
        <div style='font-family: \"DM Sans\", Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0e14; color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #1a1f26;'>
            <div style='background: #00e5a0; padding: 40px 20px; text-align: center;'>
                <h1 style='margin: 0; color: #0a0e14; font-size: 28px; font-weight: 800; letter-spacing: -0.5px;'>TerraChain</h1>
            </div>
            <div style='padding: 40px 30px; line-height: 1.6;'>
                <h2 style='color: #00e5a0; margin-top: 0;'>Security Verification</h2>
                <p style='font-size: 16px; color: #94a3b8;'>Hello,</p>
                <p style='font-size: 16px; color: #94a3b8;'>To complete your sign-in, please use the following one-time verification code:</p>
                
                <div style='background: rgba(0, 229, 160, 0.1); border: 2px dashed #00e5a0; border-radius: 12px; padding: 30px; text-align: center; margin: 30px 0;'>
                    <span style='font-family: \"DM Mono\", monospace; font-size: 42px; font-weight: 700; letter-spacing: 12px; color: #00e5a0; display: block;'>$otp</span>
                </div>
                
                <p style='font-size: 14px; color: #64748b; margin-bottom: 0;'>This code is valid for <b>10 minutes</b>. If you did not request this code, please secure your account immediately.</p>
            </div>
            <div style='background: #1a1f26; padding: 20px; text-align: center; font-size: 12px; color: #64748b;'>
                © " . date('Y') . " TerraChain Land Registry System. All rights reserved.
            </div>
        </div>
        ";
        
        // Try custom SMTP first
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            $success = $this->sendViaSMTP($email, $subject, $message);
            if ($success) return true;
        }

        // Fallback to native mail()
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($email, $subject, $message, $headers);
    }

    public function sendGenericEmail($email, $subject, $htmlBody) {
        // Try custom SMTP first
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            $success = $this->sendViaSMTP($email, $subject, $htmlBody);
            if ($success) return true;
        }

        // Fallback to native mail()
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($email, $subject, $htmlBody, $headers);
    }

    private function sendViaSMTP($to, $subject, $body) {
        try {
            $timeout = 10;
            // Connect to SMTP server
            $socket = stream_socket_client("tcp://" . SMTP_HOST . ":" . SMTP_PORT, $errno, $errstr, $timeout);
            if (!$socket) {
                error_log("SMTP Connection Failed: $errstr ($errno)");
                return false;
            }

            $this->readResponse($socket); // 220
            
            $this->sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $this->readResponse($socket); // 250

            $this->sendCommand($socket, "STARTTLS");
            $this->readResponse($socket); // 220
            
            // Enable encryption
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP Error: Failed to enable crypto (TLS)");
                return false;
            }
            
            $this->sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $this->readResponse($socket); // 250

            $this->sendCommand($socket, "AUTH LOGIN");
            $this->readResponse($socket); // 334
            
            $this->sendCommand($socket, base64_encode(SMTP_USER));
            $this->readResponse($socket); // 334
            
            $this->sendCommand($socket, base64_encode(SMTP_PASS));
            $this->readResponse($socket); // 235

            $this->sendCommand($socket, "MAIL FROM: <" . SMTP_FROM . ">");
            $this->readResponse($socket); // 250

            $this->sendCommand($socket, "RCPT TO: <$to>");
            $this->readResponse($socket); // 250

            $this->sendCommand($socket, "DATA");
            $this->readResponse($socket); // 354

            $data = "To: $to\r\n";
            $data .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
            $data .= "Subject: $subject\r\n";
            $data .= "Content-Type: text/html; charset=UTF-8\r\n";
            $data .= "MIME-Version: 1.0\r\n\r\n";
            $data .= $body . "\r\n.\r\n";
            
            $this->sendCommand($socket, $data);
            $this->readResponse($socket); // 250

            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("SMTP Socket Error: " . $e->getMessage());
            return false;
        }
    }

    private function sendCommand($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket) {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        return $response;
    }
}
