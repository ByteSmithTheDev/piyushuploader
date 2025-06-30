<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

// Function to log errors
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    error_log($logMessage . "\n", 3, __DIR__ . '/../logs/api_errors.log');
}

// Verify API key
$headers = getallheaders();
$api_key = $headers['Authorization'] ?? null;

if (!$api_key) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'API key not provided'
    ]);
    exit;
}

// Get user by API key
$stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ?");
$stmt->execute([$api_key]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid API key'
    ]);
    exit;
}

$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify file was uploaded properly
    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No file uploaded or invalid upload',
            'error_code' => 'no_file'
        ]);
        exit;
    }

    $file = $_FILES['file'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_message = $error_messages[$file['error']] ?? 'Unknown upload error';

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $error_message,
            'error_code' => 'upload_error'
        ]);
        exit;
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../upload';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            logError('Failed to create upload directory', ['path' => $uploadDir]);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create upload directory'
            ]);
            exit;
        }
    }

    // Process file data
    try {
        $originalFilename = basename($file['name']);
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $randomFilename = generateRandomString(12) . ($extension ? '.' . $extension : '');
        $uploadPath = $uploadDir . '/' . $randomFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Insert file record into database
        $stmt = $pdo->prepare("INSERT INTO files (user_id, filename, original_filename, file_size, file_type, upload_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $randomFilename, $originalFilename, $fileSize, $fileType]);

        // Generate file URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $fileUrl = $protocol . $domain . '/view.php?id=' . $randomFilename;

        echo json_encode([
            'success' => true,
            'url' => $fileUrl
        ]);

    } catch (Exception $e) {
        logError('File upload failed', [
            'error' => $e->getMessage(),
            'file' => $file
        ]);

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to process upload: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}