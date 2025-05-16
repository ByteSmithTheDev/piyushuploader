<?php
function generateRandomString($length = 5) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function getFileUrl($filename) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    return $protocol . $domain . '/view.php?id=' . $filename;
}

function isValidFile($file) {
    $maxFileSize = 2147483648; // 2GB limit (adjust as needed)
    if ($file['size'] > $maxFileSize) {
        return false;
    }
    return true;
}

function incrementFileViews($pdo, $file_id) {
    $stmt = $pdo->prepare("UPDATE files SET views = views + 1 WHERE id = ?");
    $stmt->execute([$file_id]);
}

function getFileById($pdo, $file_id) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$file_id]);
    return $stmt->fetch();
}
?>