<?php
// users/generate_otp.php - PRODUCTION VERSION WITH MAILTRAP
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

    error_log("OTP Generation - Email: $email");

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
        error_log("Email not found: $email");
        throw new Exception('Email not found in our records');
    }

    $stmt->close();

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    error_log("Generated OTP: $otp for email: $email");

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
        error_log("Failed to insert OTP: " . $stmt->error);
        throw new Exception('Failed to generate OTP');
    }

    $stmt->close();

    // ========== SEND EMAIL VIA MAILTRAP API ==========
    
    $emailSent = false;
    $emailError = '';

    // Mailtrap API Configuration
    $mailtrap_api_url = 'https://send.api.mailtrap.io/api/send';
    $mailtrap_api_token = '1e5b11b384f906e1f6679839d475e833';  // ← REPLACE WITH YOUR TOKEN
    
    // Prepare email data
    $emailData = array(
        'from' => array(
            'email' => 'noreply@dinesphere.com',
            'name' => 'DineSphere'
        ),
        'to' => array(
            array(
                'email' => $email,
                'name' => 'DineSphere User'
            )
        ),
        'subject' => 'DineSphere - Password Reset OTP',
        'text' => "Your OTP for password reset is: " . $otp . "\n\n" .
                  "This OTP will expire in 10 minutes.\n\n" .
                  "If you didn't request this, please ignore this email.\n\n" .
                  "Best regards,\n" .
                  "DineSphere Team"
    );

    // Send via Mailtrap API using cURL
    if (extension_loaded('curl')) {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $mailtrap_api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($emailData),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $mailtrap_api_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $emailSent = true;
            error_log("Email sent successfully via Mailtrap to: $email (HTTP: $http_code)");
        } else {
            $emailError = "Mailtrap Error (HTTP $http_code): $response_body";
            error_log($emailError);
        }
    } else {
        $emailError = "cURL extension not available";
        error_log($emailError);
    }

    // ========== RESPONSE ==========
    
    $conn->close();

    if ($emailSent) {
        $response['success'] = true;
        $response['message'] = 'OTP sent to your email successfully';
    } else {
        // OTP is still in database, but email failed
        $response['success'] = true;
        $response['message'] = 'OTP generated (Email may be delayed). Check your email in a few moments.';
        error_log("Warning: OTP generated but email sending failed: $emailError");
    }

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    error_log("OTP Generation Error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
}
?>