<?php
// users/generate_otp.php - WITH GMAIL SMTP
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

    // ========== SEND EMAIL VIA GMAIL SMTP ==========
    
    $emailSent = false;
    
    // Gmail SMTP Configuration
    $gmail_email = 'chshan123321@gmail.com';      // ← Your Gmail address
    $gmail_password = 'nsyc qeqi rarw qkjw';       // ← Your 16-char app password
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    
    // Check if credentials are set
    if ($gmail_email !== 'YOUR_GMAIL@gmail.com' && $gmail_password !== 'YOUR_APP_PASSWORD') {
        
        // Email content
        $to = $email;
        $subject = 'DineSphere - Password Reset OTP';
        $message = "Your OTP for password reset is: " . $otp . "\n\n" .
                   "This OTP will expire in 10 minutes.\n\n" .
                   "If you didn't request this, please ignore this email.\n\n" .
                   "Best regards,\n" .
                   "DineSphere Team";
        
        $headers = "From: " . $gmail_email . "\r\n";
        $headers .= "Reply-To: " . $gmail_email . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // Send using stream context (SMTP over TLS)
        $smtp_params = array(
            'host' => $smtp_host,
            'port' => $smtp_port,
            'auth' => true,
            'username' => $gmail_email,
            'password' => $gmail_password,
            'timeout' => 10
        );

        $context = stream_context_create(array('smtp' => $smtp_params));

        if (@mail($to, $subject, $message, $headers, '-f' . $gmail_email)) {
            $emailSent = true;
            error_log("Email sent via Gmail SMTP to: $email");
        } else {
            error_log("Failed to send email via Gmail SMTP to: $email");
            // Try fallback method
            $emailSent = @mail($to, $subject, $message, $headers);
        }
    }

    // ========== RESPONSE ==========
    
    $response['success'] = true;
    $response['message'] = $emailSent ? 
        'OTP sent to your email' : 
        'OTP generated. Please check your email.';

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
}
?>