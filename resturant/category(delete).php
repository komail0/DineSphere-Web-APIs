<?php
header("Content-Type: application/json");
require_once "conn.php";

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;

if ($category_id <= 0 || $restaurant_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid category_id and restaurant_id required"]);
    exit();
}

// Verify category belongs to restaurant before deleting
$sql = "DELETE FROM category WHERE category_id = ? AND restaurant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $category_id, $restaurant_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Category deleted successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Category not found or unauthorized"
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete category: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
