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

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submissions
$success_message = '';
$error_message = '';

// Update Profile
if (isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Validate input
    if (empty($username) || empty($email)) {
        $error_message = "Username and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $user_id]);
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error_message = "Error updating profile. Please try again.";
        }
    }
}

// Change Password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error_message = "Current password is incorrect.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success_message = "Password changed successfully!";
        } catch (PDOException $e) {
            $error_message = "Error changing password. Please try again.";
        }
    }
}

// Regenerate API Key
if (isset($_POST['regenerate_api_key'])) {
    try {
        $new_api_key = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
        $stmt->execute([$new_api_key, $user_id]);
        $success_message = "API key regenerated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error regenerating API key. Please try again.";
    }
}

// Update Upload Preferences
if (isset($_POST['update_preferences'])) {
    $max_file_size = (int)$_POST['max_file_size'];
    $allowed_extensions = trim($_POST['allowed_extensions']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET max_file_size = ?, allowed_extensions = ? WHERE id = ?");
        $stmt->execute([$max_file_size, $allowed_extensions, $user_id]);
        $success_message = "Upload preferences updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error updating preferences. Please try again.";
    }
}

// Get recent activity
$stmt = $pdo->prepare("
    SELECT f.*, COUNT(d.id) as downloads 
    FROM files f 
    LEFT JOIN downloads d ON f.id = d.file_id 
    WHERE f.user_id = ? 
    GROUP BY f.id 
    ORDER BY f.upload_date DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();

// Get storage stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_files,
        SUM(file_size) as total_size,
        MAX(upload_date) as last_upload
    FROM files 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Calculate storage percentage
$storage_limit = 2 * 1024 * 1024 * 1024; // 2GB
$storage_percent = min(($stats['total_size'] / $storage_limit) * 100, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FileShare</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-primary { background-color: #1a365d; }
        .bg-secondary { background-color: #2c5282; }
        .hover\:bg-secondary:hover { background-color: #2c5282; }
        .border-primary { border-color: #1a365d; }
        .text-primary { color: #4299e1; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-primary p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">FileShare</h1>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                <a href="files.php" class="hover:text-gray-300">Files</a>
                <a href="logout.php" class="hover:text-gray-300">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <?php if ($success_message): ?>
            <div class="bg-green-500 text-white p-4 rounded-lg mb-6">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Profile Information</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <button type="submit" name="update_profile" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Change Password</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">New Password</label>
                            <input type="password" name="new_password" required
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <button type="submit" name="change_password" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Change Password
                        </button>
                    </form>
                </div>

                <!-- Upload Preferences -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Upload Preferences</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Max File Size (MB)</label>
                            <input type="number" name="max_file_size" 
                                value="<?= htmlspecialchars($user['max_file_size'] ?? 100) ?>" 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Allowed Extensions (comma-separated)</label>
                            <input type="text" name="allowed_extensions" 
                                value="<?= htmlspecialchars($user['allowed_extensions'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx') ?>" 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2">
                            <p class="text-sm text-gray-400 mt-1">Leave empty to allow all extensions</p>
                        </div>
                        <button type="submit" name="update_preferences" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Update Preferences
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- API Key -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">API Key</h2>
                    <div class="space-y-4">
                        <div class="bg-gray-700 p-3 rounded-lg break-all">
                            <code class="text-sm"><?= htmlspecialchars($user['api_key']) ?></code>
                        </div>
                        <form method="POST">
                            <button type="submit" name="regenerate_api_key" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Regenerate API Key
                            </button>
                        </form>
                        <p class="text-sm text-gray-400">
                            Use this key to authenticate your ShareX uploads. Keep it private!
                        </p>
                    </div>
                </div>

                <!-- Storage Stats -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Storage</h2>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span><?= formatFileSize($stats['total_size']) ?> used</span>
                                <span><?= formatFileSize($storage_limit - $stats['total_size']) ?> free</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $storage_percent ?>%"></div>
                            </div>
                            <p class="text-sm text-gray-400 mt-1">
                                <?= round($storage_percent, 2) ?>% of <?= formatFileSize($storage_limit) ?>
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold"><?= $stats['total_files'] ?></p>
                                <p class="text-sm text-gray-400">Total Files</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold">
                                    <?= $stats['last_upload'] ? formatDate($stats['last_upload']) : 'Never' ?>
                                </p>
                                <p class="text-sm text-gray-400">Last Upload</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-secondary rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Recent Activity</h2>
                    <?php if (count($recent_activity) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_activity as $file): ?>
                                <div class="bg-gray-700 p-3 rounded-lg">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium truncate"><?= htmlspecialchars($file['original_filename']) ?></p>
                                            <p class="text-sm text-gray-400">
                                                <?= formatFileSize($file['file_size']) ?> • 
                                                <?= formatDate($file['upload_date']) ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-400">
                                                <?= $file['views'] ?> views
                                            </p>
                                            <p class="text-sm text-gray-400">
                                                <?= $file['downloads'] ?> downloads
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400 text-center py-4">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Copy API key to clipboard
        function copyApiKey() {
            const apiKey = document.querySelector('code').textContent;
            navigator.clipboard.writeText(apiKey).then(() => {
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg';
                notification.textContent = 'API key copied to clipboard!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            });
        }
    </script>
</body>
</html> 