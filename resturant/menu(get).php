<?php
header("Content-Type: application/json");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/menu_errors.log');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Fatal error: " . $error['message']
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

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

try {
    if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "category_id is required"]);
        exit();
    }

    $category_id = intval($_GET['category_id']);

    $sql = "SELECT menu_id, menu_name, menu_description, menu_price, menu_image, category_id, restaurant_id 
            FROM menu 
            WHERE category_id = ? 
            ORDER BY menu_id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $menuItems = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $menuItems[] = [
                "menu_id" => intval($row['menu_id']),
                "menu_name" => $row['menu_name'],
                "menu_description" => $row['menu_description'],
                "menu_price" => floatval($row['menu_price']),
                "menu_image" => $row['menu_image'],
                "category_id" => intval($row['category_id']),
                "restaurant_id" => intval($row['restaurant_id'])
            ];
        }

        echo json_encode([
            "status" => "success",
            "count" => count($menuItems),
            "data" => $menuItems
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
    error_log("Exception in menu(get).php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>