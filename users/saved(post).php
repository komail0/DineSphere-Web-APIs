<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';

try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['success'] = false;
        $response['message'] = 'Method not allowed. Use POST.';
        http_response_code(405);
        echo json_encode($response);
        exit;
    }

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        $response['success'] = false;
        $response['message'] = 'Database connection failed';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    // Get POST data
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;

    // Validate required fields
    if ($userId <= 0 || $restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid user ID and restaurant ID are required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Check if already saved
    $checkSql = "SELECT id FROM saved_restaurants WHERE user_id = ? AND restaurant_id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $checkStmt->bind_param('ii', $userId, $restaurantId);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Restaurant already saved';
        http_response_code(409);
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();

    // Insert saved restaurant
    $insertSql = "INSERT INTO saved_restaurants (user_id, restaurant_id) VALUES (?, ?)";
    
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ii', $userId, $restaurantId);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();

        $response['success'] = true;
        $response['message'] = 'Restaurant saved successfully';
        http_response_code(201);
        echo json_encode($response);
    } else {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Failed to save restaurant';
        http_response_code(500);
        echo json_encode($response);
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>