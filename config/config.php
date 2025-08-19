<?php
// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'esports_tournament');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Site configuration
define('SITE_URL', $_ENV['SITE_URL'] ?? 'http://localhost:5000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Payment configuration
define('BKASH_API_KEY', $_ENV['BKASH_API_KEY'] ?? 'default_bkash_key');
define('NAGAD_API_KEY', $_ENV['NAGAD_API_KEY'] ?? 'default_nagad_key');

// Security
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}
?>
