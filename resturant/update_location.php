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
    $restaurantId = isset($_POST['restaurant_id']) ? trim($_POST['restaurant_id']) : '';
    $latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : '';
    $longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    // Validate required fields
    if (empty($restaurantId)) {
        $response['success'] = false;
        $response['message'] = 'Restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($latitude) || empty($longitude)) {
        $response['success'] = false;
        $response['message'] = 'Location coordinates are required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($address)) {
        $response['success'] = false;
        $response['message'] = 'Address is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate latitude and longitude format
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        $response['success'] = false;
        $response['message'] = 'Invalid coordinates format';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate latitude range (-90 to 90)
    if ($latitude < -90 || $latitude > 90) {
        $response['success'] = false;
        $response['message'] = 'Invalid latitude value';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate longitude range (-180 to 180)
    if ($longitude < -180 || $longitude > 180) {
        $response['success'] = false;
        $response['message'] = 'Invalid longitude value';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Update restaurant location
    $updateSql = "UPDATE restaurant SET latitude = ?, longitude = ?, address = ? WHERE restaurant_id = ?";
    
    $stmt = $conn->prepare($updateSql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ddsi', $latitude, $longitude, $address, $restaurantId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Location updated successfully';
            http_response_code(200);
        } else {
            // Check if restaurant exists
            $checkSql = "SELECT restaurant_id FROM restaurant WHERE restaurant_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('i', $restaurantId);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows == 0) {
                $response['success'] = false;
                $response['message'] = 'Restaurant not found';
                http_response_code(404);
            } else {
                // Restaurant exists but no changes were made (same data)
                $response['success'] = true;
                $response['message'] = 'Location already up to date';
                http_response_code(200);
            }
            $checkStmt->close();
        }
        $stmt->close();
        $conn->close();
        echo json_encode($response);
    } else {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Failed to update location';
        http_response_code(500);
        echo json_encode($response);
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>