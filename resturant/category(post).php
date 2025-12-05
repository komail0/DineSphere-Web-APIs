<?php
header("Content-Type: application/json");

// Enable error reporting and catch all errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/category_errors.log');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Fatal error: " . $error['message'],
            "file" => $error['file'],
            "line" => $error['line']
        ]);
    }
});

try {
    require_once "conn.php";
    require_once "upload_image.php";
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Include error: " . $e->getMessage()]);
    exit();
}

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

try {
    // Get POST data
    $restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    $base64_image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    // Validate required fields
    if ($restaurant_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Valid restaurant_id is required"]);
        exit();
    }

    if (empty($category_name)) {
        echo json_encode(["status" => "error", "message" => "Category name is required"]);
        exit();
    }

    if (empty($base64_image)) {
        echo json_encode(["status" => "error", "message" => "Category image is required"]);
        exit();
    }

    // Verify restaurant exists
    $checkRestaurant = "SELECT restaurant_id FROM restaurant WHERE restaurant_id = ?";
    $stmt = $conn->prepare($checkRestaurant);
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(["status" => "error", "message" => "Restaurant not found"]);
        exit();
    }
    $stmt->close();

    // Upload image to Cloudinary
    $uploadResult = uploadBase64ImageToCloudinary($base64_image, 'dinesphere/categories');

    if (!$uploadResult['success']) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Image upload failed: " . ($uploadResult['message'] ?? 'Unknown error'),
            "upload_result" => $uploadResult
        ]);
        exit();
    }

    if (empty($uploadResult['url'])) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Upload succeeded but no URL returned",
            "upload_result" => $uploadResult
        ]);
        exit();
    }

    $imageUrl = $uploadResult['url'];

    // Insert category into database
    $sql = "INSERT INTO category (category_name, category_image, restaurant_id) 
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $category_name, $imageUrl, $restaurant_id);

    if ($stmt->execute()) {
        $category_id = $stmt->insert_id;
        
        echo json_encode([
            "success" => true,
            "category_id" => $category_id,
            "image_url" => $imageUrl,
            "message" => "Category added successfully",
            "data" => [
                "category_id" => $category_id,
                "category_name" => $category_name,
                "category_image" => $imageUrl,
                "restaurant_id" => $restaurant_id
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to add category: " . $stmt->error
        ]);
    }

    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Exception: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>