<?php
header("Content-Type: application/json");

// Enable error reporting
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
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Include error: " . $e->getMessage()]);
    exit();
}

// Check database connection
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "details" => $conn ? $conn->connect_error : "Connection is null"
    ]);
    exit();
}

try {
    // Get POST data
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    $base64_image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    // Validate required fields
    if ($category_id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Valid category_id is required"]);
        exit();
    }

    if ($restaurant_id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Valid restaurant_id is required"]);
        exit();
    }

    if (empty($category_name)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Category name is required"]);
        exit();
    }

    // Verify category belongs to restaurant
    $checkCategory = "SELECT category_id, category_image FROM category WHERE category_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($checkCategory);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $category_id, $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Category not found or unauthorized"]);
        exit();
    }

    $row = $result->fetch_assoc();
    $oldImageUrl = $row['category_image'];
    $stmt->close();

    // If new image is provided, upload it
    $imageUrl = $oldImageUrl; // Keep old image by default
    
    if (!empty($base64_image)) {
        error_log("Uploading new image for category update...");
        $uploadResult = uploadBase64ImageToCloudinary($base64_image, 'dinesphere/categories');
        error_log("Upload result: " . json_encode($uploadResult));

        if (!$uploadResult['success']) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Image upload failed: " . ($uploadResult['message'] ?? 'Unknown error')
            ]);
            exit();
        }

        if (empty($uploadResult['url'])) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Upload succeeded but no URL returned"
            ]);
            exit();
        }

        $imageUrl = $uploadResult['url'];
    }

    // Update category in database
    $sql = "UPDATE category SET category_name = ?, category_image = ? WHERE category_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare update failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssii", $category_name, $imageUrl, $category_id, $restaurant_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows > 0 || ($stmt->affected_rows === 0 && $imageUrl === $oldImageUrl)) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Category updated successfully",
            "data" => [
                "category_id" => $category_id,
                "category_name" => $category_name,
                "category_image" => $imageUrl,
                "restaurant_id" => $restaurant_id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to update category"
        ]);
    }

    $stmt->close();
    
} catch (Exception $e) {
    error_log("Exception in category(update).php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>