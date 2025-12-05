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
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        $userStmt->close();
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

    // Get saved restaurants
    $sql = "SELECT r.restaurant_id, r.business_name, r.address, r.latitude, r.longitude, 
                   r.restaurant_image, r.discount, r.rating,
                   (6371 * acos(cos(radians(?)) * cos(radians(r.latitude)) * 
                   cos(radians(r.longitude) - radians(?)) + 
                   sin(radians(?)) * sin(radians(r.latitude)))) AS distance_km
            FROM saved_restaurants sr
            INNER JOIN restaurant r ON sr.restaurant_id = r.restaurant_id
            WHERE sr.user_id = ?
            ORDER BY sr.saved_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('dddi', $userLat, $userLng, $userLat, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $restaurants = array();

    while ($row = $result->fetch_assoc()) {
        $restaurant = array(
            'restaurant_id' => intval($row['restaurant_id']),
            'business_name' => $row['business_name'],
            'address' => $row['address'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'restaurant_image' => $row['restaurant_image'],
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
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>