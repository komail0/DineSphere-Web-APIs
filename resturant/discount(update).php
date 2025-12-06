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
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $discount = isset($_POST['discount']) ? trim($_POST['discount']) : '';

    // Validate required fields
    if ($restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Discount can be empty (to clear it), but if provided, validate length
    if (strlen($discount) > 100) {
        $response['success'] = false;
        $response['message'] = 'Discount text is too long (max 100 characters)';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Update restaurant discount
    $sql = "UPDATE restaurant SET discount = ? WHERE restaurant_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('si', $discount, $restaurantId);

    if ($stmt->execute()) {
        // Store affected rows BEFORE closing the statement
        $affected = $stmt->affected_rows;
        $error = $stmt->error;
        
        $stmt->close();
        $conn->close();

        // Check if update was successful
        if ($affected > 0 || empty($error)) {
            $response['success'] = true;
            $response['message'] = 'Discount updated successfully';
            $response['discount'] = $discount;
            http_response_code(200);
        } else {
            $response['success'] = false;
            $response['message'] = 'Restaurant not found or no changes made';
            http_response_code(404);
        }
    } else {
        // Store error before closing
        $error = $stmt->error;
        
        $stmt->close();
        $conn->close();
        
        $response['success'] = false;
        $response['message'] = 'Failed to update discount: ' . $error;
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