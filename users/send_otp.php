<?php
// NO PHPMailer required - works immediately!
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/otp_errors.log');
header('Content-Type: application/json');

$response = array();

try {
    error_log("POST Data: " . print_r($_POST, true));

    include 'conn.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.');
    }

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    error_log("Processing OTP request for email: " . $email);

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Check if user exists
    $sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
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
        error_log("Email not found in database: " . $email);
        throw new Exception('Email not registered');
    }

    $stmt->close();

    error_log("User found, generating OTP");

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    
    error_log("Generated OTP: " . $otp . " for email: " . $email);
    
    // Store OTP in database with expiry (10 minutes)
    $expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    error_log("Storing OTP in database");
    
    // Delete old OTPs for this email
    $deleteSql = "DELETE FROM otp_verification WHERE email = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if (!$deleteStmt) {
        error_log("Failed to prepare delete statement: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $deleteStmt->bind_param('s', $email);
    $deleteStmt->execute();
    $deleteStmt->close();

    error_log("Old OTPs deleted, inserting new OTP");

    // Insert new OTP
    $insertSql = "INSERT INTO otp_verification (email, otp_code, expiry_time, created_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    
    if (!$insertStmt) {
        error_log("Failed to prepare insert statement: " . $conn->error);
        throw new Exception('Failed to prepare OTP insert: ' . $conn->error);
    }

    $insertStmt->bind_param('sss', $email, $otp, $expiryTime);
    
    if (!$insertStmt->execute()) {
        error_log("Failed to execute insert: " . $insertStmt->error);
        throw new Exception('Failed to store OTP: ' . $insertStmt->error);
    }
    
    error_log("OTP stored successfully in database");
    
    $insertStmt->close();

    // DEBUG MODE: Return OTP directly (no email needed!)
    error_log("DEBUG MODE: Returning OTP without sending email");
    
    $conn->close();
    $response['success'] = true;
    $response['message'] = 'OTP generated successfully';
    $response['otp'] = $otp; // Shows in app for testing
    $response['expiry'] = $expiryTime;
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if ($e->getMessage() == 'Email not registered') {
        http_response_code(404);
    } elseif ($e->getMessage() == 'Email is required' || $e->getMessage() == 'Invalid email address') {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    echo json_encode($response);
}
?>