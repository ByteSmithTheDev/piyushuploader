<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);     // Log errors to file
ini_set('error_log', '/path/to/php-error.log'); // Set a writable log file
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get API key from database
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !isset($user['api_key'])) {
    // Generate new API key if none exists
    $api_key = generateRandomString(32);
    $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->execute([$api_key, $user_id]);
    $_SESSION['api_key'] = $api_key;
} else {
    $_SESSION['api_key'] = $user['api_key'];
}

$api_key = $_SESSION['api_key'];

// Get files (limit to 5 for recent files)
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY upload_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_files = $stmt->fetchAll();

// Get all files for stats
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY upload_date DESC");
$stmt->execute([$user_id]);
$all_files = $stmt->fetchAll();

// Get stats
$total_files = count($all_files);
$total_size = 0;
$total_views = 0;
$last_upload = null;

foreach ($all_files as $file) {
    $total_size += $file['file_size'];
    $total_views += $file['views'];
    if (!$last_upload || strtotime($file['upload_date']) > strtotime($last_upload)) {
        $last_upload = $file['upload_date'];
    }
}

// Storage limit (2GB for example)
$storage_limit = 2 * 1024 * 1024 * 1024; // 2GB in bytes
$storage_percent = min(($total_size / $storage_limit) * 100, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FileShare Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-primary {
            background-color: #1a365d;
        }
        .bg-secondary {
            background-color: #2c5282;
        }
        .hover\:bg-secondary:hover {
            background-color: #2c5282;
        }
        .border-primary {
            border-color: #1a365d;
        }
        .text-primary {
            color: #4299e1;
        }
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background-color: #2d3748;
        }
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background-color: #4299e1;
            width: <?= $storage_percent ?>%;
        }
        .sidebar {
            width: 250px;
            transition: all 0.3s;
        }
        .sidebar-collapsed {
            margin-left: -250px;
        }
        .main-content {
            transition: all 0.3s;
        }
        .sidebar-toggle {
            transition: all 0.3s;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
                position: absolute;
                z-index: 100;
                height: 100vh;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <div class="sidebar bg-primary text-white flex-shrink-0">
        <div class="p-4">
            <h1 class="text-xl font-bold mb-6 flex items-center">
                <i class="fas fa-cloud mr-2"></i> FileShare
            </h1>
            
            <div class="space-y-2 mb-8">
                <a href="index.php" class="block py-2 px-4 bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-home mr-3"></i> Dashboard
                </a>
                <a href="sharex.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-cog mr-3"></i> ShareX Config
                </a>
                <a href="files.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-folder-open mr-3"></i> All Files
                </a>
                <a href="stats.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-chart-bar mr-3"></i> Stats
                </a>
            </div>
            
            <div class="mb-8">
                <h3 class="text-sm uppercase font-semibold text-gray-400 mb-2 px-4">Storage</h3>
                <div class="px-4 mb-2">
                    <div class="flex justify-between text-sm mb-1">
                        <span><?= formatFileSize($total_size) ?> used</span>
                        <span><?= formatFileSize($storage_limit - $total_size) ?> free</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?= round($storage_percent, 2) ?>% of <?= formatFileSize($storage_limit) ?></p>
                </div>
            </div>
            
            <div class="px-4">
                <button onclick="toggleUploadModal()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                    <i class="fas fa-upload mr-2"></i> Upload File
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content flex-1 overflow-x-hidden">
        <nav class="bg-primary p-4 shadow-lg flex justify-between items-center">
            <button class="sidebar-toggle text-white p-2 rounded-lg hover:bg-secondary md:hidden">
                <i class="fas fa-bars"></i>
            </button>
            <div class="flex items-center space-x-6">
                <a href="profile.php" class="hover:text-gray-300 flex items-center">
                    <i class="fas fa-user mr-2"></i> Profile
                </a>
                <a href="logout.php" class="hover:text-gray-300 flex items-center">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </nav>

        <main class="container mx-auto px-4 py-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-900 mr-4">
                            <i class="fas fa-file text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Files</p>
                            <h3 class="text-2xl font-bold"><?= $total_files ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-900 mr-4">
                            <i class="fas fa-database text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Storage Used</p>
                            <h3 class="text-2xl font-bold"><?= formatFileSize($total_size) ?></h3>
                            <div class="progress-bar mt-2">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-900 mr-4">
                            <i class="fas fa-clock text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Last Upload</p>
                            <h3 class="text-2xl font-bold"><?= $last_upload ? formatDate($last_upload) : 'Never' ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-900 mr-4">
                            <i class="fas fa-eye text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Views</p>
                            <h3 class="text-2xl font-bold"><?= $total_views ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Files and ShareX Config -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Recent Files -->
                <div class="lg:col-span-2 bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-history mr-2 text-primary"></i> Recent Files
                        </h2>
                        <a href="files.php" class="text-blue-400 hover:text-blue-300 text-sm flex items-center">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (count($recent_files) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="text-gray-400 text-left border-b border-gray-700">
                                    <tr>
                                        <th class="pb-2">File</th>
                                        <th class="pb-2">Size</th>
                                        <th class="pb-2">Views</th>
                                        <th class="pb-2">Uploaded</th>
                                        <th class="pb-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_files as $file): ?>
                                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                                            <td class="py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-file text-blue-400 mr-3"></i>
                                                    <span class="truncate max-w-xs"><?= htmlspecialchars($file['original_filename']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= formatFileSize($file['file_size']) ?></td>
                                            <td><?= $file['views'] ?></td>
                                            <td><?= formatDate($file['upload_date']) ?></td>
                                            <td>
                                                <div class="flex space-x-2">
                                                    <a href="view.php?id=<?= $file['filename'] ?>" class="text-blue-400 hover:text-blue-300" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="download.php?id=<?= $file['filename'] ?>" class="text-green-400 hover:text-green-300" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button onclick="copyLink('<?= getFileUrl($file['filename']) ?>')" class="text-purple-400 hover:text-purple-300" title="Copy">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-folder-open text-4xl text-gray-500 mb-4"></i>
                            <h3 class="text-lg font-semibold">No recent files</h3>
                            <p class="text-gray-400 mt-2">Upload your first file to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- ShareX Config -->
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-share-alt mr-2 text-primary"></i> ShareX Config
                    </h2>
                    <div class="space-y-4">
                        <p class="text-gray-400">Configure ShareX for easy uploading:</p>
                        <div class="bg-gray-800 rounded-lg p-4">
                            <code class="text-sm text-green-400">https://<?= $_SERVER['HTTP_HOST'] ?>/upload.php</code>
                        </div>
                        <div class="relative">
                            <p class="text-gray-400 text-sm">API Key:</p>
                            <div class="api-key-container bg-gray-800 rounded-lg p-4 mt-1 overflow-x-auto w-full">
                                <code class="text-sm text-white blur-sm hover:blur-none transition-all duration-300 cursor-pointer select-none whitespace-nowrap" 
                                      onclick="copyApiKey('<?= htmlspecialchars($api_key) ?>')"
                                      title="Click to copy">
                                    <?= htmlspecialchars($api_key) ?>
                                </code>
                            </div>
                        </div>
                        <a href="sharex.php" class="block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i> Download ShareX Config
                        </a>
                        <div class="text-xs text-gray-400 mt-2">
                            <p>For help setting up ShareX, visit our <a href="#" class="text-blue-400 hover:underline">documentation</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Upload Section -->
            <div class="bg-secondary rounded-lg p-6 shadow-lg mb-8">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-cloud-upload-alt mr-2 text-primary"></i> Quick Upload
                </h2>
                <form id="uploadForm" class="space-y-4">
                    <div class="flex items-center justify-center w-full">
                        <label for="fileInput" class="w-full flex flex-col items-center px-4 py-8 bg-gray-800 rounded-lg border-2 border-dashed border-gray-600 cursor-pointer hover:border-blue-500 hover:bg-gray-700 transition duration-300">
                            <div class="text-center">
                                <i class="fas fa-file-upload text-4xl text-blue-400 mb-2"></i>
                                <p class="text-lg">Drag & drop files here</p>
                                <p class="text-sm text-gray-400 mt-1">or click to browse (Max 2GB)</p>
                            </div>
                            <input id="fileInput" type="file" class="hidden" required>
                        </label>
                    </div>
                    <div class="flex space-x-4">
                        <button type="button" onclick="uploadFile()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                            <i class="fas fa-upload mr-2"></i> Upload File
                        </button>
                        <button type="button" onclick="toggleUploadModal()" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i> Advanced Upload
                        </button>
                    </div>
                </form>
                <div id="uploadStatus" class="mt-4 hidden">
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-spinner fa-spin text-blue-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">Uploading file...</p>
                                <div class="w-full bg-gray-700 rounded-full h-2.5 mt-2">
                                    <div id="uploadProgress" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <p id="uploadProgressText" class="text-sm text-gray-400 mt-1">0%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="uploadResult" class="mt-4 hidden">
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">Upload successful!</p>
                                <p class="text-sm text-gray-400 mt-1">File URL: <span id="uploadedUrl" class="text-blue-400"></span></p>
                                <button onclick="copyUploadedUrl()" class="mt-2 text-sm bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded">
                                    <i class="fas fa-copy mr-1"></i> Copy URL
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="uploadError" class="mt-4 hidden">
                    <div class="bg-red-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium" id="errorMessage">Upload failed!</p>
                                <p class="text-sm text-red-300 mt-1" id="errorDetails"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 p-4">
                <h3 class="text-xl font-bold">Advanced File Upload</h3>
                <button onclick="toggleUploadModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="advancedUploadForm" class="space-y-4">
                    <div>
                        <label class="block text-gray-400 mb-2">File</label>
                        <input type="file" id="advancedFileInput" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Custom Filename (optional)</label>
                        <input type="text" id="customName" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2" placeholder="Leave blank for original filename">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Password Protection (optional)</label>
                        <input type="password" id="filePassword" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2" placeholder="Leave blank for no password">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="toggleUploadModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg">
                            Cancel
                        </button>
                        <button type="button" onclick="uploadAdvancedFile()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center">
                            <i class="fas fa-upload mr-2"></i> Upload
                        </button>
                    </div>
                </form>
                <div id="advancedUploadStatus" class="mt-4 hidden">
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-spinner fa-spin text-blue-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">Uploading file...</p>
                                <div class="w-full bg-gray-700 rounded-full h-2.5 mt-2">
                                    <div id="advancedUploadProgress" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <p id="advancedUploadProgressText" class="text-sm text-gray-400 mt-1">0%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="advancedUploadResult" class="mt-4 hidden">
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">Upload successful!</p>
                                <p class="text-sm text-gray-400 mt-1">File URL: <span id="advancedUploadedUrl" class="text-blue-400"></span></p>
                                <button onclick="copyAdvancedUploadedUrl()" class="mt-2 text-sm bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded">
                                    <i class="fas fa-copy mr-1"></i> Copy URL
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="advancedUploadError" class="mt-4 hidden">
                    <div class="bg-red-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium" id="advancedErrorMessage">Upload failed!</p>
                                <p class="text-sm text-red-300 mt-1" id="advancedErrorDetails"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Toggle upload modal
        function toggleUploadModal() {
            document.getElementById('uploadModal').classList.toggle('hidden');
        }
        
        // Copy link to clipboard
        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center';
                notification.innerHTML = `
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Link copied to clipboard!</span>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
        }
        
        // Drag and drop functionality
        const dropArea = document.querySelector('label[for="fileInput"]');
        const fileInput = document.getElementById('fileInput');
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight() {
            dropArea.classList.add('border-blue-500', 'bg-gray-700');
            dropArea.querySelector('.text-blue-400').classList.add('text-blue-300');
        }
        
        function unhighlight() {
            dropArea.classList.remove('border-blue-500', 'bg-gray-700');
            dropArea.querySelector('.text-blue-400').classList.remove('text-blue-300');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function updateFileInfo(file) {
            const fileNameElement = dropArea.querySelector('p.text-lg');
            const fileSizeElement = dropArea.querySelector('p.text-sm');
            
            fileNameElement.textContent = file.name;
            fileSizeElement.textContent = formatFileSize(file.size);
        }
        
        // Chunked file upload
        async function uploadFile() {
            const file = fileInput.files[0];
            if (!file) {
                showError('Please select a file first');
                return;
            }

            const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            let uploadedChunks = 0;
            let uploadId = null;

            // Show upload status
            const statusElement = document.getElementById('uploadStatus');
            const progressElement = document.getElementById('uploadProgress');
            const progressTextElement = document.getElementById('uploadProgressText');
            const resultElement = document.getElementById('uploadResult');
            const errorElement = document.getElementById('uploadError');

            // Reset UI
            resultElement.classList.add('hidden');
            errorElement.classList.add('hidden');
            statusElement.classList.remove('hidden');
            progressElement.style.width = '0%';
            progressTextElement.textContent = '0%';

            try {
                // Initialize upload
                const initResponse = await fetch('upload.php?action=init', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        filename: file.name,
                        size: file.size,
                        type: file.type
                    })
                });

                const initData = await initResponse.json();
                if (!initData.success) {
                    throw new Error(initData.error || 'Failed to initialize upload');
                }

                uploadId = initData.upload_id;

                // Upload chunks
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('chunk', chunk);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('upload_id', uploadId);

                    const response = await fetch('upload.php?action=chunk', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(`Failed to upload chunk ${chunkIndex + 1}/${totalChunks}: ${data.error || 'Unknown error'}`);
                    }

                    uploadedChunks++;
                    const progress = Math.round((uploadedChunks / totalChunks) * 100);
                    progressElement.style.width = progress + '%';
                    progressTextElement.textContent = `${progress}%`;
                }

                // Complete upload
                const completeResponse = await fetch('upload.php?action=complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upload_id: uploadId
                    })
                });

                const completeData = await completeResponse.json();
                if (!completeData.success) {
                    throw new Error(completeData.error || 'Failed to complete upload');
                }

                // Show success
                statusElement.classList.add('hidden');
                document.getElementById('uploadedUrl').textContent = completeData.url;
                document.getElementById('uploadedUrl').setAttribute('data-url', completeData.url);
                resultElement.classList.remove('hidden');

                // Reset form and file input
                fileInput.value = '';
                dropArea.querySelector('p.text-lg').textContent = 'Drag & drop files here';
                dropArea.querySelector('p.text-sm').textContent = 'or click to browse (Max 2GB)';

                // Refresh file list after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);

            } catch (error) {
                statusElement.classList.add('hidden');
                showError('Upload failed', error.message);
                
                // Reset file input on error
                fileInput.value = '';
                dropArea.querySelector('p.text-lg').textContent = 'Drag & drop files here';
                dropArea.querySelector('p.text-sm').textContent = 'or click to browse (Max 2GB)';
            }
        }

        function showError(message, details = '') {
            const errorElement = document.getElementById('uploadError');
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorDetails').textContent = details;
            errorElement.classList.remove('hidden');
            
            // Scroll to error message
            errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function copyUploadedUrl() {
            const url = document.getElementById('uploadedUrl').getAttribute('data-url');
            if (url) {
                navigator.clipboard.writeText(url);
                
                // Show copied notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center';
                notification.innerHTML = `
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>URL copied to clipboard!</span>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        }
        
        // Handle click on file input label
        document.querySelector('label[for="fileInput"]').addEventListener('click', function(e) {
            // Only trigger if the click is directly on the label, not on the input
            if (e.target.tagName === 'LABEL') {
                e.preventDefault();
                fileInput.click();
            }
        });
        
        // Update UI when file is selected via dialog
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length) {
                updateFileInfo(fileInput.files[0]);
            }
        });

        // Advanced file input handling
        document.getElementById('advancedFileInput').addEventListener('change', function() {
            if (this.files.length) {
                // Update custom name field with filename (without extension) if empty
                const customNameInput = document.getElementById('customName');
                if (!customNameInput.value) {
                    const fileName = this.files[0].name;
                    const nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
                    customNameInput.value = nameWithoutExt;
                }
            }
        });

        // Advanced file upload function
        async function uploadAdvancedFile() {
            const file = document.getElementById('advancedFileInput').files[0];
            if (!file) {
                showAdvancedError('Please select a file first');
                return;
            }

            const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            let uploadedChunks = 0;
            let uploadId = null;

            // Show upload status
            const statusElement = document.getElementById('advancedUploadStatus');
            const progressElement = document.getElementById('advancedUploadProgress');
            const progressTextElement = document.getElementById('advancedUploadProgressText');
            const resultElement = document.getElementById('advancedUploadResult');
            const errorElement = document.getElementById('advancedUploadError');

            // Reset UI
            resultElement.classList.add('hidden');
            errorElement.classList.add('hidden');
            statusElement.classList.remove('hidden');
            progressElement.style.width = '0%';
            progressTextElement.textContent = '0%';

            try {
                // Initialize upload
                const initResponse = await fetch('upload.php?action=init', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        filename: file.name,
                        size: file.size,
                        type: file.type,
                        custom_name: document.getElementById('customName').value,
                        password: document.getElementById('filePassword').value
                    })
                });

                const initData = await initResponse.json();
                if (!initData.success) {
                    throw new Error(initData.error || 'Failed to initialize upload');
                }

                uploadId = initData.upload_id;

                // Upload chunks
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('chunk', chunk);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('upload_id', uploadId);

                    const response = await fetch('upload.php?action=chunk', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(`Failed to upload chunk ${chunkIndex + 1}/${totalChunks}: ${data.error || 'Unknown error'}`);
                    }

                    uploadedChunks++;
                    const progress = Math.round((uploadedChunks / totalChunks) * 100);
                    progressElement.style.width = progress + '%';
                    progressTextElement.textContent = `${progress}%`;
                }

                // Complete upload
                const completeResponse = await fetch('upload.php?action=complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upload_id: uploadId,
                        custom_name: document.getElementById('customName').value,
                        password: document.getElementById('filePassword').value
                    })
                });

                const completeData = await completeResponse.json();
                if (!completeData.success) {
                    throw new Error(completeData.error || 'Failed to complete upload');
                }

                // Show success
                statusElement.classList.add('hidden');
                document.getElementById('advancedUploadedUrl').textContent = completeData.url;
                document.getElementById('advancedUploadedUrl').setAttribute('data-url', completeData.url);
                resultElement.classList.remove('hidden');

                // Reset form
                document.getElementById('advancedFileInput').value = '';
                document.getElementById('customName').value = '';
                document.getElementById('filePassword').value = '';

                // Close modal after 2 seconds
                setTimeout(() => {
                    toggleUploadModal();
                    window.location.reload();
                }, 2000);

            } catch (error) {
                statusElement.classList.add('hidden');
                showAdvancedError('Upload failed', error.message);
                
                // Reset form on error
                document.getElementById('advancedFileInput').value = '';
                document.getElementById('customName').value = '';
                document.getElementById('filePassword').value = '';
            }
        }

        function showAdvancedError(message, details = '') {
            const errorElement = document.getElementById('advancedUploadError');
            document.getElementById('advancedErrorMessage').textContent = message;
            document.getElementById('advancedErrorDetails').textContent = details;
            errorElement.classList.remove('hidden');
            
            // Scroll to error message
            errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function copyAdvancedUploadedUrl() {
            const url = document.getElementById('advancedUploadedUrl').getAttribute('data-url');
            if (url) {
                navigator.clipboard.writeText(url);
                
                // Show copied notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center';
                notification.innerHTML = `
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>URL copied to clipboard!</span>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        }

        function copyApiKey(key) {
            navigator.clipboard.writeText(key).then(() => {
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center';
                notification.innerHTML = `
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>API Key copied to clipboard!</span>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
        }
    </script>
</body>
</html>