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

    // 2. Fetch User
    $sql = "SELECT user_id, email, password_hash, created_at FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database query failed');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        // Don't reveal that the email doesn't exist for security
        throw new Exception('Invalid credentials');
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // 3. Verify Password
    if (!password_verify($password, $user['password_hash'])) {
        $conn->close();
        throw new Exception('Invalid credentials');
    }

    // 4. Success (Remove sensitive hash)
    unset($user['password_hash']);
    $conn->close();

    $response['success'] = true;
    $response['message'] = 'Login successful';
    $response['user'] = $user;
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if ($e->getMessage() == 'Invalid credentials') {
        http_response_code(401);
    } elseif ($e->getMessage() == 'Email and password are required') {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    echo json_encode($response);
}
?>