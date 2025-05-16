<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;

            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = 'Username or email already exists';
            } else {
                $errors[] = 'Registration failed';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FileShare</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-gray-800 shadow-lg rounded-lg px-8 pt-6 pb-8 mb-4">
            <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>
            <?php if (!empty($errors)): ?>
                <div class="bg-red-500 text-white p-3 rounded mb-4">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold mb-2" for="username">
                        Username
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="username" type="text" name="username" required>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2" for="email">
                        Email
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="email" type="email" name="email" required>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2" for="password">
                        Password
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" 
                           id="password" type="password" name="password" required>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2" for="confirm_password">
                        Confirm Password
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" 
                           id="confirm_password" type="password" name="confirm_password" required>
                </div>
                <div>
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
                            type="submit">
                        Register
                    </button>
                </div>
            </form>
            <div class="text-center mt-4">
                <a href="login.php" class="text-blue-400 hover:text-blue-300">
                    Already have an account? Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>