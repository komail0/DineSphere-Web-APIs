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

    // Get device tokens of users who saved this restaurant
    $sql = "SELECT DISTINCT u.device_token 
            FROM users u 
            INNER JOIN saved_restaurants sr ON u.user_id = sr.user_id 
            WHERE sr.restaurant_id = ? 
            AND u.device_token IS NOT NULL 
            AND u.device_token != ''";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $conn->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('i', $restaurantId);
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Error executing query: ' . $stmt->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }
    
    $result = $stmt->get_result();

    $tokens = array();
    while ($row = $result->fetch_assoc()) {
        $tokens[] = $row['device_token'];
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['tokens'] = $tokens;
    $response['count'] = count($tokens);
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