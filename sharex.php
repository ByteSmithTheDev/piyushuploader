<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's API key
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Generate API key if not exists
if (!$user['api_key']) {
    $api_key = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->execute([$api_key, $_SESSION['user_id']]);
} else {
    $api_key = $user['api_key'];
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];
$upload_url = $protocol . $domain . "/api/upload.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareX Configuration - FileShare</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <nav class="bg-gray-800 p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">FileShare</h1>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                <a href="profile.php" class="hover:text-gray-300">Profile</a>
                <a href="logout.php" class="hover:text-gray-300">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6">ShareX Configuration</h2>
                
                <div class="mb-8">
                    <h3 class="text-xl mb-4">What is ShareX?</h3>
                    <p class="text-gray-300 mb-4">
                        ShareX is a free and open-source screenshot and file sharing tool. With ShareX, you can capture screenshots and upload them directly to our server with a single hotkey.
                    </p>
                </div>

                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Step 1: Download ShareX</h3>
                        <p class="text-gray-300 mb-2">If you don't have ShareX installed, download it from the official website:</p>
                        <a href="https://getsharex.com/" target="_blank" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Download ShareX
                        </a>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-2">Step 2: Download Our Configuration</h3>
                        <p class="text-gray-300 mb-2">Download our custom ShareX configuration file to enable quick uploads to your account:</p>
                        <a href="sharex-config.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Download Configuration
                        </a>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-2">Step 3: Import the Configuration</h3>
                        <p class="text-gray-300">After downloading the configuration file:</p>
                        <ol class="list-decimal list-inside space-y-1 text-gray-300 mt-2">
                            <li>Open ShareX</li>
                            <li>Click "Destinations" in the main menu</li>
                            <li>Select "Destination settings..."</li>
                            <li>Click "Import" and select the downloaded configuration file</li>
                            <li>Click "Yes" when asked if you want to set it as the active destination</li>
                        </ol>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-2">Step 4: Test It Out</h3>
                        <p class="text-gray-300">
                            Take a screenshot using ShareX (default hotkey: <kbd class="px-2 py-1 bg-gray-700 rounded">Ctrl + Print Screen</kbd>) or upload a file by dragging it onto the ShareX window. It will automatically upload to your account and copy the link to your clipboard.
                        </p>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">Manual Configuration</h3>
                        <p class="text-gray-300 mb-4">If you prefer to configure ShareX manually, use these settings:</p>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300">Your API Key:</label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input type="text" value="<?= htmlspecialchars($api_key) ?>" readonly
                                        class="flex-1 min-w-0 block w-full px-3 py-2 rounded-md text-gray-300 bg-gray-700 border border-gray-600">
                                    <button onclick="copyToClipboard('<?= htmlspecialchars($api_key) ?>')" 
                                        class="ml-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        Copy
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-400">Keep this key private! It allows uploading files to your account.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300">Upload URL:</label>
                                <input type="text" value="<?= htmlspecialchars($upload_url) ?>" readonly
                                    class="mt-1 block w-full px-3 py-2 rounded-md text-gray-300 bg-gray-700 border border-gray-600">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300">Request method:</label>
                                <input type="text" value="POST" readonly
                                    class="mt-1 block w-full px-3 py-2 rounded-md text-gray-300 bg-gray-700 border border-gray-600">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300">Body format:</label>
                                <input type="text" value="MultipartFormData" readonly
                                    class="mt-1 block w-full px-3 py-2 rounded-md text-gray-300 bg-gray-700 border border-gray-600">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300">File form name:</label>
                                <input type="text" value="file" readonly
                                    class="mt-1 block w-full px-3 py-2 rounded-md text-gray-300 bg-gray-700 border border-gray-600">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>