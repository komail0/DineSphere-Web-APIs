<?php
// Temporary debug endpoint - DELETE AFTER FIXING
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: text/plain");

echo "=== PHP Version ===\n";
echo phpversion() . "\n\n";

echo "=== Testing vendor autoload ===\n";
$vendorPath = __DIR__ . '/../vendor/autoload.php';
echo "Path: $vendorPath\n";
echo "Exists: " . (file_exists($vendorPath) ? "YES" : "NO") . "\n\n";

if (file_exists($vendorPath)) {
    try {
        require_once $vendorPath;
        echo "Autoload: SUCCESS\n";
        echo "Cloudinary class exists: " . (class_exists('Cloudinary\Cloudinary') ? "YES" : "NO") . "\n\n";
    } catch (Exception $e) {
        echo "Autoload ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Testing DB Connection ===\n";
require_once 'conn.php';
if ($conn) {
    echo "Connection: SUCCESS\n";
    echo "Server: " . $conn->server_info . "\n";
    
    // Test query
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "Tables:\n";
        while ($row = $result->fetch_array()) {
            echo "  - " . $row[0] . "\n";
        }
    }
} else {
    echo "Connection: FAILED\n";
}

echo "\n=== Testing upload_image.php ===\n";
try {
    require_once 'upload_image.php';
    echo "Include: SUCCESS\n";
    echo "Function exists: " . (function_exists('uploadBase64ImageToCloudinary') ? "YES" : "NO") . "\n";
} catch (Exception $e) {
    echo "Include ERROR: " . $e->getMessage() . "\n";
}
