<?php
// conn.php
// Database connection for production

// DISABLE error display for production (prevents HTML output in JSON responses)
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Log errors to file instead
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Railway internal connection
$host = 'mysql.railway.internal';
$port = 3306;
$user = 'root';
$password = 'TGIZWAWItMMzmgxgRJozFHsTpWsZDbSt';
$database = 'railway';

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection (but don't output HTML)
if ($conn->connect_error) {
    // Log the error instead of displaying it
    error_log("Database connection failed: " . $conn->connect_error);
    // Don't die() with HTML message - let the calling script handle it
    $conn = null;
}

// Set charset to utf8mb4 for proper unicode support
if ($conn) {
    $conn->set_charset("utf8mb4");
}
?>