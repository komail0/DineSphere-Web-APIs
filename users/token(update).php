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
    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $deviceToken = isset($_POST['device_token']) ? trim($_POST['device_token']) : '';

    // Validate required fields
    if (empty($userId)) {
        $response['success'] = false;
        $response['message'] = 'User ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($deviceToken)) {
        $response['success'] = false;
        $response['message'] = 'Device token is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Update device token
    $sql = "UPDATE users SET device_token = ? WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ss', $deviceToken, $userId);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        
        $stmt->close();
        $conn->close();

        if ($affected > 0 || $stmt->errno === 0) {
            $response['success'] = true;
            $response['message'] = 'Device token updated successfully';
            http_response_code(200);
        } else {
            $response['success'] = false;
            $response['message'] = 'User not found or token unchanged';
            http_response_code(404);
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        $response['success'] = false;
        $response['message'] = 'Failed to update device token: ' . $error;
        http_response_code(500);
    }
    
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