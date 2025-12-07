<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/otp_errors.log');
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer (adjust path as needed)
require 'vendor/autoload.php'; // If using Composer
// OR manually include:
// require 'PHPMailer/src/Exception.php';
// require 'PHPMailer/src/PHPMailer.php';
// require 'PHPMailer/src/SMTP.php';

$response = array();

error_log("POST Data: " . print_r($_POST, true));

include 'conn.php';

try {
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

    // Send email using PHPMailer
    error_log("Attempting to send email via PHPMailer to: " . $email);
    
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Change to your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // Your Gmail address
        $mail->Password   = 'your-app-password'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom('your-email@gmail.com', 'DineSphere');
        $mail->addAddress($email);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'DineSphere - Password Reset OTP';
        $mail->Body    = "
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

        $mail->send();
        
        error_log("SUCCESS: Email sent via PHPMailer to " . $email);
        
        $conn->close();
        $response['success'] = true;
        $response['message'] = 'OTP sent successfully to your email';
        $response['debug_otp'] = $otp; // REMOVE IN PRODUCTION
        http_response_code(200);
        echo json_encode($response);

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        throw new Exception("Failed to send email: {$mail->ErrorInfo}");
    }

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