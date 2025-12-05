<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/menu_errors.log');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Fatal error: " . $error['message']]);
    }
});

try {
    require_once "conn.php";
    require_once "upload_image.php";
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Include error: " . $e->getMessage()]);
    exit();
}

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

try {
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $menu_name = isset($_POST['menu_name']) ? trim($_POST['menu_name']) : '';
    $menu_description = isset($_POST['menu_description']) ? trim($_POST['menu_description']) : '';
    $menu_price = isset($_POST['menu_price']) ? floatval($_POST['menu_price']) : 0.0;
    $base64_image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    if ($category_id <= 0 || $restaurant_id <= 0) {
        echo json_encode(["success" => false, "message" => "Valid category and restaurant IDs required"]);
        exit();
    }
    if (empty($menu_name) || empty($menu_price) || empty($base64_image)) {
        echo json_encode(["success" => false, "message" => "Name, price, and image are required"]);
        exit();
    }

    // Verify foreign keys
    $checkFK = "SELECT category_id FROM category WHERE category_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($checkFK);
    $stmt->bind_param('ii', $category_id, $restaurant_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        echo json_encode(["success" => false, "message" => "Invalid category or restaurant"]);
        exit();
    }
    $stmt->close();

    // Upload Image
    $uploadResult = uploadBase64ImageToCloudinary($base64_image, 'dinesphere/menu');
    if (!$uploadResult['success']) {
        echo json_encode(["success" => false, "message" => "Image upload failed"]);
        exit();
    }
    $imageUrl = $uploadResult['url'];

    $sql = "INSERT INTO menu (category_id, restaurant_id, menu_name, menu_description, menu_price, menu_image) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissds", $category_id, $restaurant_id, $menu_name, $menu_description, $menu_price, $imageUrl);

    if ($stmt->execute()) {
        $menu_id = $stmt->insert_id;
        echo json_encode([
            "success" => true,
            "menu_id" => $menu_id,
            "image_url" => $imageUrl,
            "message" => "Menu item added successfully"
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add item: " . $stmt->error]);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Exception: " . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>