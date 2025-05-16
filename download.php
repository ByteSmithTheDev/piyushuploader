<?php
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

$filename = $_GET['id'];

try {
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

    header('Content-Type: ' . $file['file_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $file['file_size']);
    echo $file['file_data'];
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error downloading file');
}