<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
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
        throw new Exception('Database connection failed');
    }

    // Get parameters
    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validate inputs
    if (empty($userId)) {
        throw new Exception('User ID is required');
    }

    if ($restaurantId <= 0) {
        throw new Exception('Valid restaurant ID is required');
    }

    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }

    // Check if review already exists
    $checkSql = "SELECT review_id FROM reviews WHERE user_id = ? AND restaurant_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('si', $userId, $restaurantId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update existing review
        $row = $checkResult->fetch_assoc();
        $reviewId = $row['review_id'];
        
        $updateSql = "UPDATE reviews SET rating = ?, comment = ? WHERE review_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('isi', $rating, $comment, $reviewId);
        
        if ($updateStmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Review updated successfully';
            $response['review_id'] = $reviewId;
            $response['action'] = 'updated';
        } else {
            throw new Exception('Failed to update review');
        }
        
        $updateStmt->close();
    } else {
        // Insert new review
        $insertSql = "INSERT INTO reviews (user_id, restaurant_id, rating, comment) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('siis', $userId, $restaurantId, $rating, $comment);
        
        if ($insertStmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Review added successfully';
            $response['review_id'] = $insertStmt->insert_id;
            $response['action'] = 'created';
        } else {
            throw new Exception('Failed to add review');
        }
        
        $insertStmt->close();
    }

    $checkStmt->close();
    $conn->close();
    
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>