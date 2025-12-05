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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Include error: " . $e->getMessage()]);
    exit();
}

// Check database connection
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed",
        "details" => $conn ? $conn->connect_error : "Connection is null"
    ]);
    exit();
}

try {
    // Check if restaurant_id is provided
    if (!isset($_GET['restaurant_id']) || empty($_GET['restaurant_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "restaurant_id is required"]);
        exit();
    }

    $restaurant_id = intval($_GET['restaurant_id']);

    if ($restaurant_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Valid restaurant_id is required"]);
        exit();
    }

    // Verify restaurant exists
    $checkRestaurant = "SELECT restaurant_id FROM restaurant WHERE restaurant_id = ?";
    $stmt = $conn->prepare($checkRestaurant);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Restaurant not found"]);
        exit();
    }
    $stmt->close();

    // Fetch categories with item count
    $sql = "SELECT 
                c.category_id,
                c.category_name,
                c.category_image,
                c.restaurant_id,
                COUNT(m.menu_id) as item_count
            FROM category c
            LEFT JOIN menu m ON c.category_id = m.category_id
            WHERE c.restaurant_id = ?
            GROUP BY c.category_id
            ORDER BY c.category_id ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

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

    $stmt->close();

} catch (Exception $e) {
    error_log("Exception in category(get).php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>