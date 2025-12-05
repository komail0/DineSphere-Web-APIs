<?php
header("Content-Type: application/json");
require_once "conn.php";

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
$restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;

if ($menu_id <= 0 || $restaurant_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid IDs"]);
    exit();
}

// Verify ownership before delete
$sql = "DELETE FROM menu WHERE menu_id = ? AND restaurant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $menu_id, $restaurant_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Item deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Item not found or unauthorized"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>