<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get all files for the user
    $stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll();

    if (empty($files)) {
        throw new Exception('No files found to delete');
    }

    // Delete all physical files
    foreach ($files as $file) {
        $file_path = 'uploads/' . $file['filename'];
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                throw new Exception('Failed to delete some files from storage');
            }
        }
    }

    // Delete all files from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'All files have been deleted successfully';
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to files page
header('Location: files.php');
exit(); 