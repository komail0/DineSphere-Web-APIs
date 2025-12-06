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
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    // Validate required fields
    if ($restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($title)) {
        $response['success'] = false;
        $response['message'] = 'Notification title is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($message)) {
        $response['success'] = false;
        $response['message'] = 'Notification message is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Insert notification into database
    $sql = "INSERT INTO notifications (restaurant_id, title, message, created_at) VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('iss', $restaurantId, $title, $message);

    if ($stmt->execute()) {
        $notificationId = $conn->insert_id;
        $stmt->close();
        $conn->close();

        $response['success'] = true;
        $response['message'] = 'Notification saved successfully';
        $response['notification_id'] = $notificationId;
        http_response_code(201);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        $response['success'] = false;
        $response['message'] = 'Failed to save notification: ' . $error;
        http_response_code(500);
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
    
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>