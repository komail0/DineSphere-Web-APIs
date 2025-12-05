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
    $maxDistance = isset($_GET['max_distance']) ? floatval($_GET['max_distance']) : 5; // Default 5 km

    if ($userLat === null || $userLng === null) {
        $response['success'] = false;
        $response['message'] = 'User location is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Haversine formula to calculate distance
    // Distance in kilometers
    // Note: restaurant_image column should be added to restaurant table for images
    $sql = "SELECT 
                restaurant_id,
                business_name,
                address,
                latitude,
                longitude,
                restaurant_image,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance_km
            FROM restaurant
            WHERE latitude IS NOT NULL 
                AND longitude IS NOT NULL
            HAVING distance_km <= ?
            ORDER BY distance_km ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('dddd', $userLat, $userLng, $userLat, $maxDistance);
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
            'distance_km' => round(floatval($row['distance_km']), 2),
            'rating' => 4.0, // Default rating, you can add a rating column later
            'discount' => null, // You can add discount logic later
            'image_url' => $row['restaurant_image'] ?? null // Cloudinary URL from database
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