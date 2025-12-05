<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once "conn.php";
    require_once "upload_image.php";
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Include error"]);
    exit();
}

if (!$conn) {
    echo json_encode(["success" => false, "message" => "DB Connection failed"]);
    exit();
}

try {
    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
    $restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $menu_name = isset($_POST['menu_name']) ? trim($_POST['menu_name']) : '';
    $menu_description = isset($_POST['menu_description']) ? trim($_POST['menu_description']) : '';
    $menu_price = isset($_POST['menu_price']) ? floatval($_POST['menu_price']) : 0.0;
    $base64_image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    if ($menu_id <= 0 || $restaurant_id <= 0 || empty($menu_name) || $menu_price <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid input data"]);
        exit();
    }

    // Get old image
    $checkSql = "SELECT menu_image FROM menu WHERE menu_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('ii', $menu_id, $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Item not found"]);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $imageUrl = $row['menu_image'];
    $stmt->close();

    // Upload new image if provided
    if (!empty($base64_image)) {
        $uploadResult = uploadBase64ImageToCloudinary($base64_image, 'dinesphere/menu');
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['url'];
        } else {
            echo json_encode(["success" => false, "message" => "Image upload failed"]);
            exit();
        }
    }

    $sql = "UPDATE menu SET menu_name = ?, menu_description = ?, menu_price = ?, menu_image = ? WHERE menu_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsii", $menu_name, $menu_description, $menu_price, $imageUrl, $menu_id, $restaurant_id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Item updated",
            "data" => ["menu_image" => $imageUrl]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Update failed"]);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>