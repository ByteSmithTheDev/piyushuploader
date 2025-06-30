<?php
// Set PHP configuration for large file uploads
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Function to log errors
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    error_log($logMessage . "\n", 3, __DIR__ . '/upload_errors.log');
}

// Debug information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received');
    error_log('Content-Length: ' . $_SERVER['CONTENT_LENGTH']);
    error_log('Upload max filesize: ' . ini_get('upload_max_filesize'));
    error_log('Post max size: ' . ini_get('post_max_size'));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logError('User not authenticated');
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit();
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    logError('User not found', ['user_id' => $user_id]);
    echo json_encode([
        'success' => false,
        'error' => 'User not found'
    ]);
    exit();
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/upload';
$tempDir = $uploadDir . '/temp';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        logError('Failed to create upload directory', ['path' => $uploadDir]);
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit();
    }
}
if (!file_exists($tempDir)) {
    if (!mkdir($tempDir, 0777, true)) {
        logError('Failed to create temp directory', ['path' => $tempDir]);
        echo json_encode(['success' => false, 'error' => 'Failed to create temp directory']);
        exit();
    }
}

// Handle different upload actions
$action = $_GET['action'] ?? 'upload';

switch ($action) {
    case 'init':
        // Initialize upload
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['filename']) || !isset($data['size'])) {
            logError('Invalid init request', ['data' => $data]);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit();
        }

        $uploadId = generateRandomString(32);
        $tempFile = $tempDir . '/' . $uploadId;
        
        // Create empty file
        if (file_put_contents($tempFile, '') === false) {
            logError('Failed to create temp file', ['path' => $tempFile]);
            echo json_encode(['success' => false, 'error' => 'Failed to create temporary file']);
            exit();
        }
        
        // Store upload info
        $_SESSION['uploads'][$uploadId] = [
            'filename' => $data['filename'],
            'size' => $data['size'],
            'type' => $data['type'] ?? 'application/octet-stream',
            'custom_name' => $data['custom_name'] ?? null,
            'password' => $data['password'] ?? null,
            'chunks' => []
        ];

        logError('Upload initialized', ['upload_id' => $uploadId, 'data' => $data]);

        echo json_encode([
            'success' => true,
            'upload_id' => $uploadId
        ]);
        break;

    case 'chunk':
        // Handle chunk upload
        if (!isset($_POST['upload_id']) || !isset($_POST['chunk_index']) || !isset($_FILES['chunk'])) {
            logError('Invalid chunk data', ['post' => $_POST, 'files' => $_FILES]);
            echo json_encode(['success' => false, 'error' => 'Invalid chunk data']);
            exit();
        }

        $uploadId = $_POST['upload_id'];
        $chunkIndex = (int)$_POST['chunk_index'];
        $chunk = $_FILES['chunk'];

        if (!isset($_SESSION['uploads'][$uploadId])) {
            logError('Upload session not found', ['upload_id' => $uploadId]);
            echo json_encode(['success' => false, 'error' => 'Upload session not found']);
            exit();
        }

        $tempFile = $tempDir . '/' . $uploadId;
        $chunkFile = $tempDir . '/' . $uploadId . '_' . $chunkIndex;

        // Move chunk to temp directory
        if (!move_uploaded_file($chunk['tmp_name'], $chunkFile)) {
            logError('Failed to save chunk', [
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'error' => error_get_last()
            ]);
            echo json_encode(['success' => false, 'error' => 'Failed to save chunk']);
            exit();
        }

        // Store chunk info
        $_SESSION['uploads'][$uploadId]['chunks'][$chunkIndex] = $chunkFile;

        logError('Chunk uploaded', [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'chunk_size' => filesize($chunkFile)
        ]);

        echo json_encode(['success' => true]);
        break;

    case 'complete':
        // Complete upload
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['upload_id'])) {
            logError('Invalid complete request', ['data' => $data]);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit();
        }

        $uploadId = $data['upload_id'];
        if (!isset($_SESSION['uploads'][$uploadId])) {
            logError('Upload session not found for completion', ['upload_id' => $uploadId]);
            echo json_encode(['success' => false, 'error' => 'Upload session not found']);
            exit();
        }

        $uploadInfo = $_SESSION['uploads'][$uploadId];
        $tempFile = $tempDir . '/' . $uploadId;
        $finalFile = $uploadDir . '/' . $uploadId;

        try {
            logError('Starting file completion', [
                'upload_id' => $uploadId,
                'chunks' => count($uploadInfo['chunks']),
                'total_size' => $uploadInfo['size']
            ]);

            // Combine chunks
            $fp = fopen($tempFile, 'wb');
            if ($fp === false) {
                throw new Exception('Failed to open temp file for writing');
            }

            ksort($uploadInfo['chunks']);
            foreach ($uploadInfo['chunks'] as $chunkIndex => $chunkFile) {
                if (!file_exists($chunkFile)) {
                    throw new Exception("Chunk file not found: $chunkFile");
                }
                $chunk = file_get_contents($chunkFile);
                if ($chunk === false) {
                    throw new Exception("Failed to read chunk file: $chunkFile");
                }
                if (fwrite($fp, $chunk) === false) {
                    throw new Exception("Failed to write chunk to temp file");
                }
                unlink($chunkFile); // Clean up chunk
            }
            fclose($fp);

            // Generate final filename
            $extension = pathinfo($uploadInfo['filename'], PATHINFO_EXTENSION);
            $randomFilename = generateRandomString(12) . ($extension ? '.' . $extension : '');
            $finalPath = $uploadDir . '/' . $randomFilename;

            // Move file to final location
            if (!rename($tempFile, $finalPath)) {
                throw new Exception("Failed to move file to final location");
            }

            // Store in database
            $stmt = $pdo->prepare("
                INSERT INTO files 
                (user_id, filename, original_filename, file_type, file_size, upload_date, password) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $user['id'],
                $randomFilename,
                $uploadInfo['custom_name'] ?? $uploadInfo['filename'],
                $uploadInfo['type'],
                $uploadInfo['size'],
                $uploadInfo['password']
            ]);

            // Clean up session
            unset($_SESSION['uploads'][$uploadId]);

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $domain = $_SERVER['HTTP_HOST'];

            logError('Upload completed successfully', [
                'upload_id' => $uploadId,
                'final_path' => $finalPath,
                'file_size' => filesize($finalPath)
            ]);

            echo json_encode([
                'success' => true,
                'url' => $protocol . $domain . '/view.php?id=' . $randomFilename
            ]);

        } catch (Exception $e) {
            logError('Upload completion failed', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up on error
            if (file_exists($tempFile)) unlink($tempFile);
            foreach ($uploadInfo['chunks'] as $chunkFile) {
                if (file_exists($chunkFile)) unlink($chunkFile);
            }
            unset($_SESSION['uploads'][$uploadId]);

            echo json_encode([
                'success' => false,
                'error' => 'Failed to complete upload',
                'system_message' => $e->getMessage()
            ]);
        }
        break;

    default:
        logError('Invalid action', ['action' => $action]);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
}