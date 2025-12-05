<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();

// Include the local conn.php found in this same folder
include 'conn.php';

try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.');
    }

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Get POST data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // 1. Validation
    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // 2. Check if email already exists
    $checkSql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($checkSql);
    
    if (!$stmt) {
        throw new Exception('Database error checking user');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Email already registered';
        http_response_code(409); // Conflict
        echo json_encode($response);
        exit;
    }
    $stmt->close();

    // 3. Hash Password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 4. Insert New User
    $insertSql = "INSERT INTO users (email, password_hash) VALUES (?, ?)";
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        throw new Exception('Database error preparing insert');
    }

    $stmt->bind_param('ss', $email, $passwordHash);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        $response['success'] = true;
        $response['message'] = 'User signup successful';
        $response['user_id'] = $newId;
        http_response_code(201);
        echo json_encode($response);
    } else {
        throw new Exception('Failed to create user account');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    // Determine appropriate error code
    if ($e->getMessage() == 'Method not allowed. Use POST.') {
        http_response_code(405);
    } elseif ($e->getMessage() == 'Email and password are required') {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    echo json_encode($response);
}
?>