<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];

// Get API key from headers
$api_key = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $api_key = isset($headers['Authorization']) ? trim(str_replace('Bearer ', '', $headers['Authorization'])) : '';
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $api_key = trim(str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']));
}

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No API key provided',
        'error_code' => 'missing_api_key'
    ]);
    exit;
}

// Validate API key and get user ID
$user_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ? LIMIT 1");
    $stmt->execute([$api_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['id'])) {
        $user_id = (int)$result['id'];
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API key',
            'error_code' => 'invalid_api_key'
        ]);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error during authentication',
        'error_code' => 'auth_db_error'
    ]);
    exit;
}

// Handle file upload
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

        $pdo->beginTransaction();

        // Insert file metadata
        $stmt = $pdo->prepare("
            INSERT INTO files 
            (user_id, filename, original_filename, file_type, file_size, upload_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
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
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'url' => $protocol . $domain . '/view.php?id=' . $randomFilename,
            'filename' => $originalFilename,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'file_id' => $fileId
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Upload processing failed',
            'error_code' => 'upload_processing_error',
            'system_message' => $e->getMessage()
        ]);
        exit;
    }
}

// If request method isn't POST
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Invalid request method',
    'error_code' => 'invalid_method'
]);