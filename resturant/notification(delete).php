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
    $notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;

    // Validate required fields
    if ($notificationId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid notification ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if ($restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Delete notification (ensure it belongs to this restaurant)
    $sql = "DELETE FROM notifications WHERE notification_id = ? AND restaurant_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ii', $notificationId, $restaurantId);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        
        $stmt->close();
        $conn->close();

        if ($affected > 0) {
            $response['success'] = true;
            $response['message'] = 'Notification deleted successfully';
            http_response_code(200);
        } else {
            $response['success'] = false;
            $response['message'] = 'Notification not found or already deleted';
            http_response_code(404);
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        $response['success'] = false;
        $response['message'] = 'Failed to delete notification: ' . $error;
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