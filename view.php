<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

$filename = $_GET['id'];

// Check if Discord's bot is making the request
$isDiscordBot = isset($_SERVER['HTTP_USER_AGENT']) && 
                (strpos($_SERVER['HTTP_USER_AGENT'], 'Discordbot') !== false || 
                 strpos($_SERVER['HTTP_USER_AGENT'], 'Twitterbot') !== false);

try {
    // Get file information
    $stmt = $pdo->prepare("SELECT * FROM files WHERE filename = ?");
    $stmt->execute([$filename]);
    $file = $stmt->fetch();

    if (!$file) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }

    // Increment view count (only for real views, not Discord bot)
    if (!$isDiscordBot) {
        $stmt = $pdo->prepare("UPDATE files SET views = views + 1 WHERE id = ?");
        $stmt->execute([$file['id']]);
    }

    // Get file path
    $filePath = __DIR__ . '/upload/' . $filename;
    if (!file_exists($filePath)) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }

    // If Discord bot is requesting, return embed information
    if ($isDiscordBot) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $file_url = $protocol . $domain . '/view.php?id=' . $filename;
        
        // Check if file is an image
        $isImage = strpos($file['file_type'], 'image/') === 0;
        
        if ($isImage) {
            // For images, we need to serve the actual image to Discord
            header('Content-Type: ' . $file['file_type']);
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit();
        } else {
            // For non-image files, return JSON with embed information
            header('Content-Type: application/json');
            echo json_encode([
                'color' => '#e9ed0c',
                'title' => 'KEEP SMILING',
                'description' => 'Piyush Uploads',
                'discord_hide_url' => false,
                'file_info' => [
                    'filename' => $file['original_filename'],
                    'type' => $file['file_type'],
                    'size' => $file['file_size']
                ]
            ]);
            exit();
        }
    }

    // Normal file serving for regular requests
    header('Content-Type: ' . $file['file_type']);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="' . htmlspecialchars($file['original_filename']) . '"');
    
    // Output the file data
    readfile($filePath);

} catch (PDOException $e) {
    error_log('Database error in view.php: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error retrieving file. Please try again later.');
}
