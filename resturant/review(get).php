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

    $restaurantId = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 0;

    if ($restaurantId <= 0) {
        throw new Exception('Valid restaurant ID is required');
    }

    // Get restaurant's average rating from restaurant table
    $restaurantSql = "SELECT rating FROM restaurant WHERE restaurant_id = ?";
    $restaurantStmt = $conn->prepare($restaurantSql);
    $restaurantStmt->bind_param('i', $restaurantId);
    $restaurantStmt->execute();
    $restaurantResult = $restaurantStmt->get_result();

    $avgRating = 0;
    if ($restaurantResult->num_rows > 0) {
        $restaurantRow = $restaurantResult->fetch_assoc();
        $avgRating = floatval($restaurantRow['rating']);
    }
    $restaurantStmt->close();

    // Get all reviews for this restaurant with user information
    $reviewsSql = "SELECT 
                    r.review_id,
                    r.user_id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.restaurant_id = ?
                ORDER BY r.created_at DESC";
    
    $reviewsStmt = $conn->prepare($reviewsSql);
    $reviewsStmt->bind_param('i', $restaurantId);
    $reviewsStmt->execute();
    $reviewsResult = $reviewsStmt->get_result();

    $reviews = array();
    $totalReviews = 0;

    while ($row = $reviewsResult->fetch_assoc()) {
        $reviews[] = array(
            'review_id' => intval($row['review_id']),
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'] ?? 'Anonymous',
            'rating' => intval($row['rating']),
            'comment' => $row['comment'],
            'created_at' => $row['created_at']
        );
        $totalReviews++;
    }

    $reviewsStmt->close();
    $conn->close();

    $response['success'] = true;
    $response['avg_rating'] = $avgRating;
    $response['total_reviews'] = $totalReviews;
    $response['reviews'] = $reviews;

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>