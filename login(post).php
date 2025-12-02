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
    $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validate required fields
    if (empty($identifier) || empty($password)) {
        $response['success'] = false;
        $response['message'] = 'Identifier and password are required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Check if identifier is email or business name
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    // Prepare SQL query
    if ($isEmail) {
        $sql = "SELECT restaurant_id, business_name, name_per_cnic, last_name, business_type, 
                       business_category, business_update, email, phone, password_hash, 
                       restaurant_location, is_verified 
                FROM restaurant 
                WHERE email = ? 
                LIMIT 1";
    } else {
        $sql = "SELECT restaurant_id, business_name, name_per_cnic, last_name, business_type, 
                       business_category, business_update, email, phone, password_hash, 
                       restaurant_location, is_verified 
                FROM restaurant 
                WHERE business_name = ? 
                LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database query failed';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Invalid credentials';
        http_response_code(401);
        echo json_encode($response);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Invalid credentials';
        http_response_code(401);
        echo json_encode($response);
        exit;
    }

    // Remove sensitive data
    unset($user['password_hash']);

    $conn->close();

    // Success response
    $response['success'] = true;
    $response['message'] = 'Login successful';
    $response['user'] = $user;
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>