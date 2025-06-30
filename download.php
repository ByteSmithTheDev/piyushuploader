<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get file ID from URL
$file_id = $_GET['id'] ?? '';
if (empty($file_id)) {
    die('No file specified');
}

try {
    // Get file information from database
    $stmt = $pdo->prepare("SELECT * FROM files WHERE filename = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch();

    if (!$file) {
        die('File not found');
    }

    // Check if file is password protected
    if (!empty($file['password'])) {
        // If password is not provided or incorrect, show password form
        if (!isset($_POST['password']) || $_POST['password'] !== $file['password']) {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Password Protected File</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            </head>
            <body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center">
                <div class="bg-gray-800 p-8 rounded-lg shadow-xl max-w-md w-full">
                    <h1 class="text-2xl font-bold mb-6">Password Protected File</h1>
                    <p class="text-gray-400 mb-4">This file is password protected. Please enter the password to download.</p>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-400 mb-2">Password</label>
                            <input type="password" name="password" required
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                        </div>
                        <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                            Download File
                        </button>
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit();
        }
    }

    // Increment view count
    $stmt = $pdo->prepare("UPDATE files SET views = views + 1 WHERE filename = ?");
    $stmt->execute([$file_id]);

    // Set file path
    $file_path = __DIR__ . '/upload/' . $file['filename'];

    // Check if file exists
    if (!file_exists($file_path)) {
        die('File not found on server');
    }

    // Set headers for download
    header('Content-Type: ' . $file['file_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $file['file_size']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    readfile($file_path);
    exit();

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}