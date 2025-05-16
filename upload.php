<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
    echo json_encode([
        'success' => false,
        'error' => 'User not found'
    ]);
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify file was uploaded properly
    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        echo json_encode([
            'success' => false,
            'error' => 'No file uploaded or invalid upload'
        ]);
        exit();
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
        
        echo json_encode([
            'success' => false,
            'error' => $error_messages[$file['error']] ?? 'Unknown upload error'
        ]);
        exit();
    }

    // Validate file
    if (!isValidFile($file)) {
        echo json_encode([
            'success' => false,
            'error' => 'File is too large or invalid'
        ]);
        exit();
    }

    // Process file data
    try {
        $fileData = file_get_contents($file['tmp_name']);
        if ($fileData === false) {
            throw new Exception('Could not read uploaded file');
        }

        $originalFilename = basename($file['name']);
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $randomFilename = generateRandomString(12) . ($extension ? '.' . $extension : '');

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];

        $pdo->beginTransaction();

        // Insert file metadata
        $stmt = $pdo->prepare("
            INSERT INTO files 
            (user_id, filename, original_filename, file_type, file_size, upload_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['id'],
            $randomFilename,
            $originalFilename,
            $fileType,
            $fileSize
        ]);

        $fileId = $pdo->lastInsertId();

        // Store file content
        $stmt = $pdo->prepare("
            INSERT INTO file_data 
            (file_id, file_data) 
            VALUES (?, ?)
        ");
        $stmt->execute([$fileId, $fileData]);

        $pdo->commit();

        // Successful response
        echo json_encode([
            'success' => true,
            'url' => $protocol . $domain . '/view.php?id=' . $randomFilename,
            'filename' => $originalFilename,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'file_id' => $fileId
        ]);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Upload processing failed',
            'system_message' => $e->getMessage()
        ]);
        exit();
    }
}

// If not POST request
echo json_encode([
    'success' => false,
    'error' => 'Invalid request method'
]);
exit();