<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];

// Get or generate API key
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Generate new API key if none exists
if (empty($user['api_key'])) {
    $api_key = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->execute([$api_key, $_SESSION['user_id']]);
} else {
    $api_key = $user['api_key'];
}

// ShareX config array
$config = [
    "Version" => "14.1.0",  // Updated to current ShareX version
    "Name" => "FileShare",
    "DestinationType" => "ImageUploader, FileUploader",
    "RequestMethod" => "POST",
    "RequestURL" => $protocol . $domain . "/api/upload.php",
    "Headers" => [
        "Authorization" => $api_key
    ],
    "Body" => "MultipartFormData",
    "FileFormName" => "file",
    "URL" => "{json:url}",
    "ErrorMessage" => "{json:error}",
    "SuccessResponse" => "{json:success}"
];

// Send as JSON download
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="fileshare.sxcu"');
echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);