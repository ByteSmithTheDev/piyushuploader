<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all files with optional search
$sql = "SELECT * FROM files WHERE user_id = ?";
$params = [$user_id];

if (!empty($search_query)) {
    $sql .= " AND (original_filename LIKE ? OR filename LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY upload_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$files = $stmt->fetchAll();

// Calculate total storage used
$total_size = 0;
foreach ($files as $file) {
    $total_size += $file['file_size'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files - FileShare</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-primary { background-color: #1a365d; }
        .bg-secondary { background-color: #2c5282; }
        .hover\:bg-secondary:hover { background-color: #2c5282; }
        .text-primary { color: #4299e1; }
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background-color: #2d3748;
        }
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background-color: #4299e1;
            width: <?= min(($total_size / (1024 * 1024 * 1024 * 2)) * 100, 100) ?>%;
        }
        .file-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 1rem;
        }
        .file-card {
            background-color: #2d3748;
            border-radius: 0.5rem;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .file-card:hover {
            transform: translateY(-2px);
        }
        .file-preview {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            background-color: #1a202c;
        }
        .file-info {
            padding: 0.75rem;
        }
        .file-name {
            font-size: 0.875rem;
            color: #e2e8f0;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-meta {
            font-size: 0.75rem;
            color: #a0aec0;
        }
        .file-actions {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            background-color: #1a202c;
        }
        .file-icon {
            width: 100%;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #1a202c;
            color: #4299e1;
            font-size: 2rem;
        }
        @media (max-width: 1280px) {
            .file-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 1024px) {
            .file-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .file-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <main class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-folder-open mr-2 text-primary"></i> My Files
            </h1>
            <div class="flex items-center space-x-4">
                <?php if (count($files) > 0): ?>
                    <a href="delete_all.php" 
                       onclick="return confirm('Are you sure you want to delete ALL files? This action cannot be undone!')"
                       class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center transition duration-300">
                        <i class="fas fa-trash-alt mr-2"></i> Delete All Files
                    </a>
                <?php endif; ?>
                <div class="relative w-64">
                    <form method="GET" action="files.php">
                        <input type="text" name="search" placeholder="Search files..." 
                               value="<?= htmlspecialchars($search_query) ?>"
                               class="bg-gray-800 text-white px-4 py-2 rounded-lg pl-10 w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-secondary rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-700">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-gray-400">Total Files:</span>
                        <span class="font-bold ml-2"><?= count($files) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-400">Storage Used:</span>
                        <span class="font-bold ml-2"><?= formatFileSize($total_size) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-400">Total Views:</span>
                        <span class="font-bold ml-2">
                            <?= array_sum(array_column($files, 'views')) ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span><?= formatFileSize($total_size) ?> used</span>
                        <span><?= formatFileSize((1024 * 1024 * 1024 * 2) - $total_size) ?> free</span>
                    </div>
                </div>
            </div>

            <?php if (count($files) > 0): ?>
                <div class="file-grid">
                    <?php foreach ($files as $file): ?>
                        <div class="file-card">
                            <?php if (isImageFile($file['file_type'])): ?>
                                <img src="view.php?id=<?= $file['filename'] ?>" 
                                     alt="<?= htmlspecialchars($file['original_filename']) ?>"
                                     class="file-preview">
                            <?php else: ?>
                                <div class="file-icon">
                                    <i class="fas <?= getFileIcon($file['file_type']) ?>"></i>
                                </div>
                            <?php endif; ?>
                            <div class="file-info">
                                <div class="file-name" title="<?= htmlspecialchars($file['original_filename']) ?>">
                                    <?= htmlspecialchars($file['original_filename']) ?>
                                </div>
                                <div class="file-meta">
                                    <?= formatFileSize($file['file_size']) ?> • <?= $file['views'] ?> views
                                </div>
                            </div>
                            <div class="file-actions">
                                <a href="view.php?id=<?= $file['filename'] ?>" 
                                   class="text-blue-400 hover:text-blue-300"
                                   title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="download.php?id=<?= $file['filename'] ?>" 
                                   class="text-green-400 hover:text-green-300"
                                   title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button onclick="copyLink('<?= getFileUrl($file['filename']) ?>')" 
                                        class="text-purple-400 hover:text-purple-300"
                                        title="Copy Link">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <a href="delete.php?id=<?= $file['id'] ?>" 
                                   class="text-red-400 hover:text-red-300"
                                   title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this file?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-folder-open text-4xl text-gray-500 mb-4"></i>
                    <h3 class="text-lg font-semibold">No files found</h3>
                    <p class="text-gray-400 mt-2">
                        <?php if (!empty($search_query)): ?>
                            No files match your search criteria
                        <?php else: ?>
                            Upload your first file to get started
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
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
    </script>
</body>
</html>