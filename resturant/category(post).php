<?php
header("Content-Type: application/json");
require_once "conn.php";
require_once "upload_image.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

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
$checkRestaurant = "SELECT restaurant_id FROM restaurant WHERE restaurant_id = $restaurant_id";
$result = $conn->query($checkRestaurant);

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Restaurant not found"]);
    exit();
}

// Upload image to Cloudinary
$uploadResult = uploadBase64ImageToCloudinary($base64_image, 'dinesphere/categories');

if (!$uploadResult['success']) {
    echo json_encode([
        "status" => "error", 
        "message" => "Image upload failed: " . $uploadResult['message']
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
        "status" => "success",
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
        "status" => "error",
        "message" => "Failed to add category: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>