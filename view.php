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
    $stmt = $pdo->prepare("
        SELECT f.*, fd.file_data 
        FROM files f 
        JOIN file_data fd ON f.id = fd.file_id 
        WHERE f.filename = ?
    ");
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

    // If Discord bot is requesting, return embed information
    if ($isDiscordBot) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $file_url = $protocol . $domain . '/view.php?id=' . $filename;
        
        // Check if file is an image
        $isImage = strpos($file['file_type'], 'image/') === 0;
        
        if ($isImage) {
            // For images, return meta tags for Discord embed
            header('Content-Type: text/html');
            echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="{$file['original_filename']}">
    <meta property="og:description" content="Shared via FileShare">
    <meta property="og:image" content="{$file_url}">
    <meta property="og:url" content="{$file_url}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#e9ed0c">
    <meta name="twitter:card" content="summary_large_image">
    <meta property="discord:embed" content="true">
    <meta property="discord:color" content="#e9ed0c">
    <meta property="discord:title" content="KEEP SMILING">
    <meta property="discord:description" content="Piyush Uploads">
</head>
<body>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "url": "{$file_url}",
        "contentUrl": "{$file_url}",
        "description": "Shared via FileShare"
    }
    </script>
</body>
</html>
HTML;
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
    header('Content-Length: ' . strlen($file['file_data']));
    header('Content-Disposition: inline; filename="' . htmlspecialchars($file['original_filename']) . '"');
    
    // Output the file data
    echo $file['file_data'];

} catch (PDOException $e) {
    error_log('Database error in view.php: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error retrieving file. Please try again later.');
}
