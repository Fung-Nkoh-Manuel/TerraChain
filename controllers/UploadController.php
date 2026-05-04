<?php
// controllers/UploadController.php

class UploadController {
    private DocumentService $docService;
    private AuthMiddleware $auth;
    
    public function __construct() {
        $this->docService = new DocumentService();
        $this->auth = new AuthMiddleware();
    }
    
    /**
     * POST /api/upload?action=parse
     * Upload a document and optionally parse/extract text
     */
    public function upload(): void {
        $user = $this->auth->requireAuth();
        $action = $_GET['action'] ?? 'upload';
        
        if (empty($_FILES['file'])) {
            $this->respond(false, 'No file uploaded', 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Validate
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->respond(false, 'Upload error: ' . $this->getUploadError($file['error']), 400);
            return;
        }
        
        try {
            // Process upload (save locally + IPFS)
            $result = $this->docService->processUpload($file, true);
            
            $response = [
                'sha256' => $result['sha256'],
                'ipfs_hash' => $result['ipfs_hash'],
                'file_name' => $result['file_name'],
                'file_size' => $result['file_size'],
                'mime_type' => $result['mime_type'],
                'ipfs_url' => $result['ipfs_hash'] ? PINATA_GATEWAY . $result['ipfs_hash'] : null,
            ];
            
            // If action is parse, extract text from the document
            if ($action === 'parse') {
                $parsedText = $this->extractTextFromFile(
                    $result['local_path'] ?? (UPLOAD_DIR . $result['sha256'] . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $result['file_name'])),
                    $result['mime_type']
                );
                $response['parsed_text'] = $parsedText;
                $response['parser_available'] = $parsedText !== null;
            }
            
            $this->respond(true, $response);
            
        } catch (Exception $e) {
            $this->respond(false, 'Upload failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Extract text from uploaded file using available tools
     */
    private function extractTextFromFile(string $filePath, string $mimeType): ?string {
        $text = null;
        
        // Try to extract text based on file type
        if (stripos($mimeType, 'pdf') !== false) {
            $text = $this->extractFromPDF($filePath);
        } elseif (stripos($mimeType, 'image') !== false) {
            $text = $this->extractFromImage($filePath);
        } elseif (stripos($mimeType, 'text') !== false) {
            $text = file_get_contents($filePath);
        }
        
        return $text;
    }
    
    /**
     * Extract text from PDF
     */
    private function extractFromPDF(string $filePath): ?string {
        // Try pdftotext command
        $pdftotextPath = $this->findCommand('pdftotext');
        if ($pdftotextPath) {
            $escapedPath = escapeshellarg($filePath);
            $cmd = escapeshellcmd($pdftotextPath) . " {$escapedPath} - 2>&1";
            $output = shell_exec($cmd);
            
            if ($output && trim($output) !== '' && stripos($output, 'Error') === false) {
                return trim($output);
            }
        }
        
        // If no text, try OCR via pdftoppm + tesseract
        return $this->extractFromPDFOCR($filePath);
    }
    
    /**
     * Extract text from PDF using OCR (convert to image then OCR)
     */
    private function extractFromPDFOCR(string $filePath): ?string {
        $pdftoppmPath = $this->findCommand('pdftoppm');
        $tesseractPath = $this->findCommand('tesseract');
        
        if (!$pdftoppmPath || !$tesseractPath) {
            return null;
        }
        
        $tmpDir = sys_get_temp_dir() . '/terrachain_ocr_' . uniqid();
        mkdir($tmpDir);
        
        $escapedFile = escapeshellarg($filePath);
        $escapedOutput = escapeshellarg($tmpDir . '/page');
        
        $cmd = escapeshellcmd($pdftoppmPath) . " -png -r 150 {$escapedFile} {$escapedOutput} 2>&1";
        shell_exec($cmd);
        
        // OCR the first page
        $firstPage = $tmpDir . '/page-1.png';
        if (file_exists($firstPage)) {
            $ocrOutput = $tmpDir . '/ocr_output';
            $cmd = escapeshellcmd($tesseractPath) . " " . escapeshellarg($firstPage) . " " . escapeshellarg($ocrOutput) . " 2>&1";
            shell_exec($cmd);
            
            if (file_exists($ocrOutput . '.txt')) {
                $text = file_get_contents($ocrOutput . '.txt');
                // Cleanup
                $this->deleteDir($tmpDir);
                return trim($text);
            }
        }
        
        $this->deleteDir($tmpDir);
        return null;
    }
    
    /**
     * Extract text from image using Tesseract OCR
     */
    private function extractFromImage(string $filePath): ?string {
        $tesseractPath = $this->findCommand('tesseract');
        if (!$tesseractPath) {
            return null;
        }
        
        $tmpDir = sys_get_temp_dir() . '/terrachain_ocr_' . uniqid();
        mkdir($tmpDir);
        
        $ocrOutput = $tmpDir . '/ocr_output';
        $cmd = escapeshellcmd($tesseractPath) . " " . escapeshellarg($filePath) . " " . escapeshellarg($ocrOutput) . " 2>&1";
        shell_exec($cmd);
        
        if (file_exists($ocrOutput . '.txt')) {
            $text = file_get_contents($ocrOutput . '.txt');
            $this->deleteDir($tmpDir);
            return trim($text);
        }
        
        $this->deleteDir($tmpDir);
        return null;
    }
    
    /**
     * Find command path (works cross-platform)
     */
    private function findCommand(string $command): ?string {
        // Check common paths
        $commonPaths = [];
        
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows paths
            if ($command === 'tesseract') {
                $commonPaths = [
                    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                ];
            } elseif (in_array($command, ['pdftotext', 'pdftoppm'])) {
                $commonPaths = [
                    "C:\\Program Files\\poppler\\bin\\{$command}.exe",
                    "C:\\Program Files (x86)\\poppler\\bin\\{$command}.exe",
                    "C:\\poppler\\bin\\{$command}.exe",
                    "C:\\poppler-25.12.0\\Library\\bin\\{$command}.exe",
                    "C:\\poppler-25.12.0\\bin\\{$command}.exe",
                ];
            }
            
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        // Try shell command
        $which = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $cmd = escapeshellcmd($which) . " " . escapeshellarg($command) . " 2>NUL";
        $output = trim(shell_exec($cmd) ?? '');
        
        if ($output && file_exists($output)) {
            return $output;
        }
        
        return null;
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDir(string $dir): void {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Get human-readable upload error
     */
    private function getUploadError(int $code): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
        ];
        return $errors[$code] ?? 'Unknown upload error';
    }
    
    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
}
