<?php
header("Content-Type: application/json");
require_once "conn.php";

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Check if restaurant_id is provided
if (!isset($_GET['restaurant_id']) || empty($_GET['restaurant_id'])) {
    echo json_encode(["status" => "error", "message" => "restaurant_id is required"]);
    exit();
}

$restaurant_id = intval($_GET['restaurant_id']);

// Verify restaurant exists
$checkRestaurant = "SELECT restaurant_id FROM restaurants WHERE restaurant_id = $restaurant_id";
$result = $conn->query($checkRestaurant);

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Restaurant not found"]);
    exit();
}

// Fetch categories with item count
$sql = "SELECT 
            c.category_id,
            c.category_name,
            c.category_image,
            c.restaurant_id,
            COUNT(m.menu_id) as item_count
        FROM category c
        LEFT JOIN menu m ON c.category_id = m.category_id
        WHERE c.restaurant_id = $restaurant_id
        GROUP BY c.category_id
        ORDER BY c.category_id ASC";

$result = $conn->query($sql);

$categories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            "category_id" => intval($row['category_id']),
            "category_name" => $row['category_name'],
            "category_image" => $row['category_image'],
            "restaurant_id" => intval($row['restaurant_id']),
            "item_count" => intval($row['item_count'])
        ];
    }

    echo json_encode([
        "status" => "success",
        "count" => count($categories),
        "data" => $categories
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        "status" => "success",
        "count" => 0,
        "data" => []
    ]);
}

$conn->close();
?>
