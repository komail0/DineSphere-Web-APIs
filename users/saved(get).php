<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error.log');
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
        $response['message'] = 'Database connection failed: ' . (isset($conn) ? $conn->connect_error : 'Connection object not set');
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    // Get user_id from query parameters
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if ($userId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid user ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Get user location for distance calculation
    $userSql = "SELECT latitude, longitude FROM users WHERE user_id = ? LIMIT 1";
    $userStmt = $conn->prepare($userSql);
    
    if (!$userStmt) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $conn->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $userStmt->bind_param('i', $userId);
    
    if (!$userStmt->execute()) {
        $userStmt->close();
        $response['success'] = false;
        $response['message'] = 'Error executing user query: ' . $userStmt->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }
    
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        $userStmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'User not found';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    $userRow = $userResult->fetch_assoc();
    $userLat = floatval($userRow['latitude']);
    $userLng = floatval($userRow['longitude']);
    $userStmt->close();

    // Get saved restaurants with image_url to match other API responses
    $sql = "SELECT r.restaurant_id, r.business_name, r.address, r.latitude, r.longitude, 
                   r.restaurant_image, r.discount, r.rating,
                   (6371 * acos(cos(radians(?)) * cos(radians(r.latitude)) * 
                   cos(radians(r.longitude) - radians(?)) + 
                   sin(radians(?)) * sin(radians(r.latitude)))) AS distance_km
            FROM saved_restaurants sr
            INNER JOIN restaurants r ON sr.restaurant_id = r.restaurant_id
            WHERE sr.user_id = ?
            ORDER BY sr.saved_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $conn->error;
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('dddi', $userLat, $userLng, $userLat, $userId);
    
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

    $restaurants = array();

    while ($row = $result->fetch_assoc()) {
        $restaurant = array(
            'restaurant_id' => intval($row['restaurant_id']),
            'business_name' => $row['business_name'],
            'address' => $row['address'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'image_url' => $row['restaurant_image'],  // Renamed to match other APIs
            'discount' => $row['discount'],
            'rating' => floatval($row['rating']),
            'distance_km' => round(floatval($row['distance_km']), 2)
        );
        $restaurants[] = $restaurant;
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['restaurants'] = $restaurants;
    $response['count'] = count($restaurants);
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
    http_response_code(500);
    echo json_encode($response);
}
?>