<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if file ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid file ID';
    header('Location: files.php');
    exit();
}

$file_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get file information and verify ownership
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch();

    if (!$file) {
        throw new Exception('File not found or you do not have permission to delete it');
    }

    // Delete the physical file
    $file_path = 'uploads/' . $file['filename'];
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            throw new Exception('Failed to delete file from storage');
        }
    }

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'File deleted successfully';
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to files page
header('Location: files.php');
exit(); 