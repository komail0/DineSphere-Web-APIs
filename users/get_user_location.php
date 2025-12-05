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

    // Get user_id from query parameters
    $userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

    if (empty($userId)) {
        $response['success'] = false;
        $response['message'] = 'User ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Get user location from database
    $sql = "SELECT latitude, longitude, address FROM users WHERE user_id = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Check if location data exists
        if ($row['latitude'] && $row['longitude']) {
            $response['success'] = true;
            $response['data'] = array(
                'latitude' => floatval($row['latitude']),
                'longitude' => floatval($row['longitude']),
                'address' => $row['address'] ?? 'Address not available'
            );
            http_response_code(200);
        } else {
            $response['success'] = false;
            $response['message'] = 'User location not set';
            http_response_code(404);
        }
    } else {
        $response['success'] = false;
        $response['message'] = 'User not found';
        http_response_code(404);
    }

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