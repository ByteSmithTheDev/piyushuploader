<?php
require_once 'config/database.php';

try {
    // Add password column to files table
    $pdo->exec("ALTER TABLE files ADD COLUMN password VARCHAR(255) DEFAULT NULL");
    echo "Database updated successfully!";
} catch (PDOException $e) {
    // If the column already exists, that's fine
    if ($e->getCode() == '42S21') {
        echo "Password column already exists.";
    } else {
        echo "Error updating database: " . $e->getMessage();
    }
} 