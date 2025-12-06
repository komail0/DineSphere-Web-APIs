<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';

try {
    // Only allow GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $response['success'] = false;
        $response['message'] = 'Method not allowed. Use GET.';
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

    // Get restaurant_id from query parameters
    $restaurantId = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 0;

    if ($restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Get notifications for this restaurant, ordered by most recent first
    $sql = "SELECT notification_id, restaurant_id, title, message, created_at 
            FROM notifications 
            WHERE restaurant_id = ? 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('i', $restaurantId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = array();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = array(
            'notification_id' => intval($row['notification_id']),
            'restaurant_id' => intval($row['restaurant_id']),
            'title' => $row['title'],
            'message' => $row['message'],
            'created_at' => $row['created_at']
        );
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['notifications'] = $notifications;
    $response['count'] = count($notifications);
    http_response_code(200);
    
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