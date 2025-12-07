<?php
// ============ users/generate_otp.php ============
error_reporting(0);
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

    // Insert new OTP
    $insertSql = "INSERT INTO password_reset_otp (email, otp_code, otp_hash, expires_at) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param('ssss', $email, $otp, $otpHash, $expiresAt);

    if (!$stmt->execute()) {
        throw new Exception('Failed to generate OTP');
    }

    $stmt->close();

    // Send OTP via email
    $to = $email;
    $subject = "DineSphere - Password Reset OTP";
    $message = "Your OTP for password reset is: " . $otp . "\n\n";
    $message .= "This OTP will expire in 10 minutes.\n\n";
    $message .= "If you didn't request this, please ignore this email.\n\n";
    $message .= "DineSphere Team";
    
    $headers = "From: noreply@dinesphere.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Send email (this will work if Railway has mail configured)
    $emailSent = mail($to, $subject, $message, $headers);

    if ($emailSent) {
        $response['success'] = true;
        $response['message'] = 'OTP sent to your email';
    } else {
        // OTP stored but email failed - still return success for testing
        $response['success'] = true;
        $response['message'] = 'OTP generated (email may not be configured)';
    }

    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>