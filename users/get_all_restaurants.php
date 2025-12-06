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

    // Get user location from query parameters
    $userLat = isset($_GET['latitude']) ? floatval($_GET['latitude']) : null;
    $userLng = isset($_GET['longitude']) ? floatval($_GET['longitude']) : null;

    if ($userLat === null || $userLng === null) {
        $response['success'] = false;
        $response['message'] = 'User location is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Get all restaurants with distance calculation and discount
    $sql = "SELECT 
                restaurant_id,
                business_name,
                address,
                phone,
                latitude,
                longitude,
                restaurant_image,
                discount,
                rating,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km
            FROM restaurant
            WHERE latitude IS NOT NULL 
                AND longitude IS NOT NULL
            ORDER BY distance_km ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ddd', $userLat, $userLng, $userLat);
    $stmt->execute();
    $result = $stmt->get_result();

    $restaurants = array();
    
    while ($row = $result->fetch_assoc()) {
        $restaurant = array(
            'restaurant_id' => intval($row['restaurant_id']),
            'business_name' => $row['business_name'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'distance_km' => round(floatval($row['distance_km']), 2),
            'rating' => floatval($row['rating'] ?? 4.0),
            'discount' => $row['discount'],
            'image_url' => $row['restaurant_image'] ?? null
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