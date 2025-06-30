<?php
session_start();
require_once 'config/database.php';

// Auto-login with remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    $token = $_COOKIE['rememberme'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_input = $_POST['user_input'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!empty($user_input) && !empty($password)) {
        // Try to find user by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user_input, $user_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Handle remember me
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('rememberme', $token, time() + (86400 * 30), "/", "", false, true); // 30 days, httpOnly
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
            } else {
                setcookie('rememberme', '', time() - 3600, "/");
                $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
            }

            header('Location: index.php');
            exit();
        }
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FileShare</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-gray-800 bg-opacity-80 shadow-lg rounded-lg px-8 pt-8 pb-8 mb-4 border border-gray-700">
            <div class="flex flex-col items-center mb-6">
                <i class="fas fa-user fa-2x text-white mb-2"></i>
                <h2 class="text-3xl font-bold text-white">Welcome Back</h2>
            </div>
            <?php if (isset($error)): ?>
                <div class="bg-red-500 text-white p-3 rounded mb-4 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-white font-semibold mb-1" for="user_input">
                        Username
                    </label>
                    <input class="w-full px-4 py-2 rounded bg-gray-900 border border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-600" 
                           id="user_input" type="text" name="user_input" placeholder="Enter your username or email" required autocomplete="username">
                </div>
                <div>
                    <label class="block text-white font-semibold mb-1" for="password">
                        Password
                    </label>
                    <input class="w-full px-4 py-2 rounded bg-gray-900 border border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-600" 
                           id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>
                <div class="flex items-center mb-2">
                    <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-300 font-semibold">Remember Me</label>
                </div>
                <div class="flex justify-between items-center">
                    <a href="#" class="text-blue-400 hover:underline text-sm font-semibold">Forgot?</a>
                </div>
                <div>
                    <button class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-2 rounded transition duration-200 border border-gray-700" 
                            type="submit">
                        Log In
                    </button>
                </div>
            </form>
            <div class="mt-6 flex flex-col items-center">
                <span class="text-gray-400">Don't have an account?</span>
                <a href="register.php" class="mt-2 w-full">
                    <button type="button" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-2 rounded transition duration-200 border border-gray-700">
                        Register
                    </button>
                </a>
            </div>
        </div>
    </div>
</body>
</html>