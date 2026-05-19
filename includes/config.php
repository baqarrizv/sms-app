<?php
// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'strongpassword');
define('DB_NAME', 'sms_app');


// App Configuration
define('APP_NAME', 'SIMSIN SMS Manager');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('SMS_FROM', '88107');
define('SMS_API_KEY', 'sad.eyJ1c2VybmFtZSI6IlNJTVNJTiIsImlhdCI6MTczMzcyOTc5N30.VdgnBZTPrwSEmpOlakNqdAcgzq8bCYJJqXwbwC5jMpU');
define('SMS_API_URL', 'https://cpass-api.eocean.net/');

// Auto-create uploads directory if missing
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create DB connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}
