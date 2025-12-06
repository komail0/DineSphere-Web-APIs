<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php'; 

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed. Use GET.');
    }

    $userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

    if (empty($userId)) {
        throw new Exception('User ID is required');
    }

    // UPDATED: Selecting all necessary profile fields, including phone and image
    $sql = "SELECT user_id, first_name, last_name, email, phone, gender, address, latitude, longitude, profile_image_url FROM users WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $response['success'] = true;
        $response['user'] = $user;
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