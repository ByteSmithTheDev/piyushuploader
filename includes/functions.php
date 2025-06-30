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

function isImageFile($fileType) {
    $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
    return in_array($fileType, $imageTypes);
}

function getFileIcon($fileType) {
    if (isImageFile($fileType)) {
        return 'fa-image';
    }
    
    $iconMap = [
        'application/pdf' => 'fa-file-pdf',
        'application/msword' => 'fa-file-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
        'application/vnd.ms-excel' => 'fa-file-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
        'application/vnd.ms-powerpoint' => 'fa-file-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fa-file-powerpoint',
        'text/plain' => 'fa-file-alt',
        'text/html' => 'fa-file-code',
        'text/css' => 'fa-file-code',
        'text/javascript' => 'fa-file-code',
        'application/json' => 'fa-file-code',
        'application/zip' => 'fa-file-archive',
        'application/x-rar-compressed' => 'fa-file-archive',
        'application/x-7z-compressed' => 'fa-file-archive',
        'audio/' => 'fa-file-audio',
        'video/' => 'fa-file-video'
    ];

    foreach ($iconMap as $type => $icon) {
        if (strpos($fileType, $type) === 0) {
            return $icon;
        }
    }

    return 'fa-file';
}
?>