<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo json_encode([
            "status" => "error",
            "message" => "Shutdown: " . $error['message'],
            "file" => basename($error['file']),
            "line" => $error['line']
        ]);
    }
});

try {
    require_once "conn.php";
    require_once "upload_image.php";
    
    if (!$conn) {
        throw new Exception("DB connection failed");
    }
    
    // Simulate the POST data
    $_POST['restaurant_id'] = 1;
    $_POST['category_name'] = 'Test Category';
    $_POST['base64_image'] = base64_encode('fake_image_data');
    
    $restaurant_id = intval($_POST['restaurant_id']);
    $category_name = trim($_POST['category_name']);
    $base64_image = $_POST['base64_image'];
    
    echo json_encode([
        "status" => "test_success",
        "message" => "All includes work, POST data received",
        "data" => [
            "restaurant_id" => $restaurant_id,
            "category_name" => $category_name,
            "base64_length" => strlen($base64_image),
            "conn_ok" => ($conn !== null),
            "function_exists" => function_exists('uploadBase64ImageToCloudinary')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "line" => $e->getLine()
    ]);
}
