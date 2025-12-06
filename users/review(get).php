<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
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
        throw new Exception('Database connection failed');
    }

    $userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
    $restaurantId = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 0;

    if (empty($userId)) {
        throw new Exception('User ID is required');
    }

    if ($restaurantId > 0) {
        // Get specific review for a restaurant by this user
        $sql = "SELECT 
                    r.review_id,
                    r.restaurant_id,
                    r.user_id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    rest.business_name,
                    rest.restaurant_image
                FROM reviews r
                INNER JOIN restaurant rest ON r.restaurant_id = rest.restaurant_id
                WHERE r.user_id = ? AND r.restaurant_id = ?
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $userId, $restaurantId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $review = $result->fetch_assoc();
            $response['success'] = true;
            $response['review'] = array(
                'review_id' => intval($review['review_id']),
                'restaurant_id' => intval($review['restaurant_id']),
                'user_id' => $review['user_id'],
                'rating' => intval($review['rating']),
                'comment' => $review['comment'],
                'created_at' => $review['created_at'],
                'business_name' => $review['business_name'],
                'restaurant_image' => $review['restaurant_image']
            );
        } else {
            $response['success'] = false;
            $response['message'] = 'No review found';
        }
        
        $stmt->close();
    } else {
        // Get all reviewed restaurants by this user
        $sql = "SELECT 
                    r.review_id,
                    r.restaurant_id,
                    r.user_id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    rest.business_name,
                    rest.address,
                    rest.restaurant_image
                FROM reviews r
                INNER JOIN restaurant rest ON r.restaurant_id = rest.restaurant_id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = array();
        while ($row = $result->fetch_assoc()) {
            $reviews[] = array(
                'review_id' => intval($row['review_id']),
                'restaurant_id' => intval($row['restaurant_id']),
                'user_id' => $row['user_id'],
                'rating' => intval($row['rating']),
                'comment' => $row['comment'],
                'created_at' => $row['created_at'],
                'business_name' => $row['business_name'],
                'address' => $row['address'],
                'restaurant_image' => $row['restaurant_image']
            );
        }
        
        $response['success'] = true;
        $response['reviews'] = $reviews;
        $response['count'] = count($reviews);
        
        $stmt->close();
    }

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