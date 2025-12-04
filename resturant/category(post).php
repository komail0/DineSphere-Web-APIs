<?php
header("Content-Type: application/json");
error_reporting(0);
ini_set('display_errors', 0);

$response = [];

include 'conn.php';

// ⛔ If DB failed silently in conn.php
if (!$conn) {
    $response['success'] = false;
    $response['message'] = "Database connection error";
    echo json_encode($response);
    exit;
}

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['success'] = false;
    $response['message'] = "Method not allowed. Use POST.";
    echo json_encode($response);
    exit;
}

// Collect POST data
$restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
$categoryName = isset($_POST['category_name']) ? trim($_POST['category_name']) : "";
$categoryImage = isset($_POST['category_image']) ? trim($_POST['category_image']) : ""; // Cloudinary URL

// Validate
if ($restaurantId <= 0 || empty($categoryName) || empty($categoryImage)) {
    $response['success'] = false;
    $response['message'] = "restaurant_id, category_name and category_image are required.";
    echo json_encode($response);
    exit;
}

// Check if restaurant exists
$check = $conn->prepare("SELECT restaurant_id FROM restaurant WHERE restaurant_id = ? LIMIT 1");
$check->bind_param("i", $restaurantId);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    $response['success'] = false;
    $response['message'] = "Invalid restaurant_id";
    echo json_encode($response);
    exit;
}
$check->close();

// Insert category
$sql = "INSERT INTO category (restaurant_id, category_name, category_image)
        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['success'] = false;
    $response['message'] = "Database error";
    echo json_encode($response);
    exit;
}

$stmt->bind_param("iss", $restaurantId, $categoryName, $categoryImage);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = "Category created successfully";
    $response['category_id'] = $stmt->insert_id;
} else {
    $response['success'] = false;
    $response['message'] = "Failed to create category";
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
