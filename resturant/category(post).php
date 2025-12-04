<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

try {
    require_once "conn.php";
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Get POST data
    $restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    $base64_image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    // Validate
    if ($restaurant_id <= 0) {
        throw new Exception("Valid restaurant_id is required");
    }

    if (empty($category_name)) {
        throw new Exception("Category name is required");
    }

    // Verify restaurant
    $stmt = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE restaurant_id = ?");
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Restaurant not found");
    }
    $stmt->close();

    // For now, use placeholder image URL or store base64 directly
    // TEMPORARY: Store first 200 chars of base64 or use placeholder
    $imageUrl = !empty($base64_image) ? 
        "https://via.placeholder.com/300x200?text=" . urlencode($category_name) : 
        "https://via.placeholder.com/300x200";

    // Insert category
    $sql = "INSERT INTO category (category_name, category_image, restaurant_id) 
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssi", $category_name, $imageUrl, $restaurant_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $category_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    ob_end_clean();
    
    echo json_encode([
        "status" => "success",
        "message" => "Category added successfully",
        "data" => [
            "category_id" => $category_id,
            "category_name" => $category_name,
            "category_image" => $imageUrl,
            "restaurant_id" => $restaurant_id
        ]
    ], JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    ob_end_clean();
    
    error_log("Category Error: " . $e->getMessage());
    
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>