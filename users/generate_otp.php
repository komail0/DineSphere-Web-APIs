<?php
// users/generate_otp.php - SIMPLE VERSION (No Email)
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['success'] = false;
    $response['message'] = 'Use POST method';
    echo json_encode($response);
    exit;
}

try {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email exists in users table
    $checkSql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($checkSql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Email not found in our records');
    }

    $stmt->close();

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Hash OTP before storing
    $otpHash = hash('sha256', $otp);

    // Set expiration (10 minutes)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete previous unused OTPs for this email
    $deleteSql = "DELETE FROM password_reset_otp WHERE email = ? AND is_used = 0";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    // Insert new OTP into database
    $insertSql = "INSERT INTO password_reset_otp (email, otp_code, otp_hash, expires_at) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('ssss', $email, $otp, $otpHash, $expiresAt);

    if (!$stmt->execute()) {
        throw new Exception('Failed to generate OTP');
    }

    $stmt->close();
    $conn->close();

    // Return OTP for display on app
    $response['success'] = true;
    $response['message'] = 'OTP generated successfully';
    $response['otp'] = $otp;

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
}
?>