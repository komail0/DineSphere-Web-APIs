<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
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

    // Get user_id from query parameters
    $userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

    if (empty($userId)) {
        $response['success'] = false;
        $response['message'] = 'User ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Get notifications only from saved restaurants
    // Join: notifications -> saved_restaurants (filtered by user_id) -> restaurants (for details)
    $sql = "SELECT 
                n.notification_id,
                n.restaurant_id,
                n.title,
                n.message,
                n.created_at,
                r.business_name,
                r.restaurant_image
            FROM notifications n
            INNER JOIN saved_restaurants sr ON n.restaurant_id = sr.restaurant_id
            INNER JOIN restaurant r ON n.restaurant_id = r.restaurant_id
            WHERE sr.user_id = ?
            ORDER BY n.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database prepare error: ' . $conn->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('s', $userId);
    
    if (!$stmt->execute()) {
        $response['success'] = false;
        $response['message'] = 'Query execution error: ' . $stmt->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }
    
    $result = $stmt->get_result();

    $notifications = array();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = array(
            'notification_id' => intval($row['notification_id']),
            'restaurant_id' => intval($row['restaurant_id']),
            'title' => $row['title'],
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'business_name' => $row['business_name'],
            'restaurant_image' => $row['restaurant_image']
        );
    }

    $response['success'] = true;
    $response['notifications'] = $notifications;
    $response['count'] = count($notifications);
    http_response_code(200);

    $stmt->close();
    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>