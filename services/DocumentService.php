<?php
// services/DocumentService.php

class DocumentService {
    
    /**
     * Upload file, compute SHA-256, optionally pin to IPFS
     */
    public function processUpload(array $file, bool $pinToIPFS = true): array {
        // Validate
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File too large. Maximum 10MB allowed.');
        }
        
        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            throw new Exception('File type not allowed.');
        }
        
        // Compute hash
        $hash = hash_file('sha256', $file['tmp_name']);
        
        // Save locally
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $safeName = $hash . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $safeName);
        
        // Pin to IPFS (optional)
        $ipfsHash = null;
        if ($pinToIPFS) {
            $ipfsHash = $this->pinToIPFS(UPLOAD_DIR . $safeName, $file['name']);
        }
        
        return [
            'sha256' => $hash,
            'ipfs_hash' => $ipfsHash,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Pin file to Pinata IPFS
     */
    private function pinToIPFS(string $filePath, string $fileName): ?string {
        $boundary = '----FormBoundary' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= file_get_contents($filePath) . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $ch = curl_init(PINATA_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . PINATA_JWT,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['IpfsHash'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Generate a combined document hash for blockchain recording
     */
    public function combineHashes(array $hashes): string {
        sort($hashes);
        return hash('sha256', implode('', $hashes));
    }
}