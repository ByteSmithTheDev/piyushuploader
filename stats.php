<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get basic stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_files, SUM(file_size) as total_size, SUM(views) as total_views FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Get files by type
$stmt = $pdo->prepare("SELECT file_type, COUNT(*) as count FROM files WHERE user_id = ? GROUP BY file_type ORDER BY count DESC LIMIT 10");
$stmt->execute([$user_id]);
$file_types = $stmt->fetchAll();

// Get daily views for last 30 days
$stmt = $pdo->prepare("
    SELECT DATE(upload_date) as day, SUM(views) as views 
    FROM files 
    WHERE user_id = ? AND upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY day
    ORDER BY day
");
$stmt->execute([$user_id]);
$daily_views = $stmt->fetchAll();

// Get top viewed files
$stmt = $pdo->prepare("SELECT filename, original_filename, views FROM files WHERE user_id = ? ORDER BY views DESC LIMIT 5");
$stmt->execute([$user_id]);
$top_files = $stmt->fetchAll();

// Get hourly traffic pattern
$stmt = $pdo->prepare("
    SELECT HOUR(upload_date) as hour, COUNT(*) as uploads, SUM(views) as views 
    FROM files 
    WHERE user_id = ?
    GROUP BY hour
    ORDER BY hour
");
$stmt->execute([$user_id]);
$hourly_stats = $stmt->fetchAll();

// Prepare data for charts
$views_data = [];
$labels = [];
foreach ($daily_views as $day) {
    $labels[] = date('M j', strtotime($day['day']));
    $views_data[] = $day['views'];
}

$hourly_labels = [];
$hourly_views = [];
for ($i = 0; $i < 24; $i++) {
    $hourly_labels[] = sprintf("%02d:00", $i);
    $hourly_views[$i] = 0; // Initialize all hours
}
foreach ($hourly_stats as $hour) {
    $hourly_views[$hour['hour']] = $hour['views'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FileShare - Statistics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .bg-primary { background-color: #1a365d; }
        .bg-secondary { background-color: #2c5282; }
        .hover\:bg-secondary:hover { background-color: #2c5282; }
        .chart-container { position: relative; height: 300px; width: 100%; }
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
                <a href="index.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-home mr-3"></i> Dashboard
                </a>
                <a href="files.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-folder-open mr-3"></i> All Files
                </a>
                <a href="stats.php" class="block py-2 px-4 bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-chart-bar mr-3"></i> Stats
                </a>
                <a href="sharex.php" class="block py-2 px-4 hover:bg-secondary rounded-lg flex items-center">
                    <i class="fas fa-cog mr-3"></i> ShareX Config
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content flex-1 overflow-x-hidden">
        <nav class="bg-primary p-4 shadow-lg flex justify-between items-center">
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
            <h1 class="text-3xl font-bold mb-8 flex items-center">
                <i class="fas fa-chart-bar mr-3"></i> Statistics
            </h1>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-900 mr-4">
                            <i class="fas fa-file text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Files</p>
                            <h3 class="text-2xl font-bold"><?= $stats['total_files'] ?></h3>
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
                            <h3 class="text-2xl font-bold"><?= formatFileSize($stats['total_size']) ?></h3>
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
                            <h3 class="text-2xl font-bold"><?= $stats['total_views'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Views Chart -->
            <div class="bg-secondary rounded-lg p-6 shadow-lg mb-8">
                <h2 class="text-xl font-bold mb-4">Daily Views (Last 30 Days)</h2>
                <div class="chart-container">
                    <canvas id="dailyViewsChart"></canvas>
                </div>
            </div>

            <!-- Hourly Traffic Pattern -->
            <div class="bg-secondary rounded-lg p-6 shadow-lg mb-8">
                <h2 class="text-xl font-bold mb-4">Hourly Traffic Pattern</h2>
                <div class="chart-container">
                    <canvas id="hourlyTrafficChart"></canvas>
                </div>
            </div>

            <!-- File Types Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-4">File Types Distribution</h2>
                    <div class="chart-container">
                        <canvas id="fileTypesChart"></canvas>
                    </div>
                </div>

                <!-- Top Viewed Files -->
                <div class="bg-secondary rounded-lg p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-4">Top Viewed Files</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="text-gray-400 text-left border-b border-gray-700">
                                <tr>
                                    <th class="pb-2">File</th>
                                    <th class="pb-2">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_files as $file): ?>
                                    <tr class="border-b border-gray-700 hover:bg-gray-700">
                                        <td class="py-3">
                                            <div class="flex items-center">
                                                <i class="fas fa-file text-blue-400 mr-3"></i>
                                                <span class="truncate max-w-xs"><?= htmlspecialchars($file['original_filename']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= $file['views'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Daily Views Chart
        const dailyCtx = document.getElementById('dailyViewsChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Views',
                    data: <?= json_encode($views_data) ?>,
                    backgroundColor: 'rgba(66, 153, 225, 0.2)',
                    borderColor: 'rgba(66, 153, 225, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });

        // Hourly Traffic Chart
        const hourlyCtx = document.getElementById('hourlyTrafficChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($hourly_labels) ?>,
                datasets: [{
                    label: 'Views',
                    data: <?= json_encode(array_values($hourly_views)) ?>,
                    backgroundColor: 'rgba(66, 153, 225, 0.7)',
                    borderColor: 'rgba(66, 153, 225, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });

        // File Types Chart
        const fileTypesCtx = document.getElementById('fileTypesChart').getContext('2d');
        new Chart(fileTypesCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($file_types, 'file_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($file_types, 'count')) ?>,
                    backgroundColor: [
                        'rgba(66, 153, 225, 0.7)',
                        'rgba(102, 187, 106, 0.7)',
                        'rgba(171, 71, 188, 0.7)',
                        'rgba(239, 83, 80, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(38, 198, 218, 0.7)',
                        'rgba(126, 87, 194, 0.7)',
                        'rgba(239, 108, 0, 0.7)',
                        'rgba(38, 166, 154, 0.7)',
                        'rgba(141, 110, 99, 0.7)'
                    ],
                    borderColor: 'rgba(45, 55, 72, 0.8)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>