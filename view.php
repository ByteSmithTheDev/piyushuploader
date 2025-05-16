<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

$filename = $_GET['id'];

try {
    // Get file info
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

    // Check if Discord or Twitter bot is requesting
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isBot = preg_match('/Discordbot|Twitterbot|facebookexternalhit|Slackbot|WhatsApp/', $userAgent);

    if ($isBot) {
        // Show HTML with Open Graph meta tags for embedding
        $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; // Full image URL

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta property="og:title" content="KEEP SMILING">
            <meta property="og:description" content="Piyush Uploads">
            <meta property="og:image" content="<?= htmlspecialchars($imageUrl) ?>">
            <meta property="og:type" content="image">
            <meta name="theme-color" content="#e9ed0c">
            <title>KEEP SMILING</title>
        </head>
        <body>
            <p>Preview loading...</p>
        </body>
        </html>
        <?php
        exit;
    }

    // For regular users: serve the image
    $stmt = $pdo->prepare("UPDATE files SET views = views + 1 WHERE id = ?");
    $stmt->execute([$file['id']]);

    header('Content-Type: ' . $file['file_type']);
    header('Content-Length: ' . strlen($file['file_data']));
    header('Content-Disposition: inline; filename="' . htmlspecialchars($file['original_filename']) . '"');

    echo $file['file_data'];

} catch (PDOException $e) {
    error_log('Database error in view.php: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error retrieving file. Please try again later.');
}
