<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();

include 'conn.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.');
    }

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

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
        throw new Exception('Email not registered');
    }

    $stmt->close();

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    
    // Store OTP in database with expiry (10 minutes)
    $expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Create OTP table entry (delete old OTPs for this email first)
    $deleteSql = "DELETE FROM otp_verification WHERE email = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('s', $email);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Insert new OTP
    $insertSql = "INSERT INTO otp_verification (email, otp_code, expiry_time, created_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    
    if (!$insertStmt) {
        throw new Exception('Failed to prepare OTP insert');
    }

    $insertStmt->bind_param('sss', $email, $otp, $expiryTime);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to store OTP');
    }
    
    $insertStmt->close();

    // Send email using mail() function
    $subject = "DineSphere - Password Reset OTP";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
            .container { background-color: #ffffff; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; }
            .header { color: #F36600; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
            .otp-box { background-color: #f8f8f8; border: 2px dashed #F36600; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; color: #F36600; letter-spacing: 5px; margin: 20px 0; }
            .footer { color: #666; font-size: 14px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>DineSphere Password Reset</div>
            <p>Hello,</p>
            <p>You requested to reset your password. Use the OTP below to proceed:</p>
            <div class='otp-box'>$otp</div>
            <p><strong>This OTP is valid for 10 minutes.</strong></p>
            <p>If you didn't request this, please ignore this email.</p>
            <div class='footer'>
                <p>Best regards,<br>DineSphere Team</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DineSphere <noreply@dinesphere.com>" . "\r\n";

    // Send email
    if (mail($email, $subject, $message, $headers)) {
        $conn->close();
        $response['success'] = true;
        $response['message'] = 'OTP sent successfully to your email';
        http_response_code(200);
        echo json_encode($response);
    } else {
        throw new Exception('Failed to send email. Please try again.');
    }

} catch (Exception $e) {
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