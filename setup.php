<?php
/**
 * SMS App - Database Setup Script
 * Run this ONCE to create tables and seed default data
 * Access: http://your-domain/setup.php
 */

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'strongpassword');
define('DB_NAME', 'sms_app');


try {
    // Connect without DB first to create it
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(100) NOT NULL,
        `email`      VARCHAR(150) NOT NULL UNIQUE,
        `password`   VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create sms table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sms` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `msg`              TEXT NOT NULL,
        `number`           VARCHAR(20) NOT NULL,
        `current_status`   ENUM('pending','processing','sent','stop','failed') DEFAULT 'pending',
        `sms_reference_id` VARCHAR(100) DEFAULT NULL,
        `status`           ENUM('active','inactive') DEFAULT 'active',
        `activity_by`      INT UNSIGNED DEFAULT NULL,
        `activity_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`activity_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert default admin user (only if not exists)
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute(['admin@gmail.com']);
    if (!$check->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)")
            ->execute(['Default User', 'admin@gmail.com', $hash]);
    }

    echo '<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Setup Complete</title>
    <style>
        body { sans-serif; background: #0a0a0a; color: #00ff88; display: flex;
               align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { border: 1px solid #00ff88; padding: 2rem 3rem; text-align: center; }
        h2 { margin: 0 0 1rem; font-size: 1.5rem; }
        p  { margin: 0.3rem 0; color: #aaa; }
        a  { display: inline-block; margin-top: 1.5rem; padding: 0.6rem 1.5rem;
             background: #00ff88; color: #0a0a0a; text-decoration: none; font-weight: bold; }
    </style></head><body>
    <div class="box">
        <h2>✓ Setup Complete</h2>
        <p>Database: <strong style="color:#00ff88">sms_app</strong> created</p>
        <p>Tables: <strong style="color:#00ff88">users, sms</strong> ready</p>
        <p>Admin: <strong style="color:#00ff88">admin@gmail.com</strong> / admin123</p>
        <a href="login.php">Go to Login →</a>
    </div>
    </body></html>';

} catch (PDOException $e) {
    echo '<pre style="color:red">Setup Failed: ' . $e->getMessage() . '</pre>';
}
