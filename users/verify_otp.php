<?php
// users/verify_otp.php
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
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    // Log for debugging
    error_log("OTP Verification - Email: $email, OTP: $otp");

    if (empty($email) || empty($otp)) {
        throw new Exception('Email and OTP are required');
    }

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Hash the provided OTP using same algorithm as generate
    $otpHash = hash('sha256', $otp);
    
    error_log("OTP Hash: $otpHash");

    // Check if OTP matches, hasn't expired, and hasn't been used
    $verifySql = "SELECT id FROM password_reset_otp 
                  WHERE email = ? 
                  AND otp_hash = ? 
                  AND is_used = 0 
                  AND expires_at > NOW() 
                  LIMIT 1";

    $stmt = $conn->prepare($verifySql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('ss', $email, $otpHash);
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception('Query execution failed');
    }

    $result = $stmt->get_result();
    
    error_log("OTP Records found: " . $result->num_rows);

    if ($result->num_rows === 0) {
        // Debug: Check if OTP exists at all (even if expired or used)
        $debugSql = "SELECT id, is_used, expires_at FROM password_reset_otp WHERE email = ? ORDER BY created_at DESC LIMIT 1";
        $debugStmt = $conn->prepare($debugSql);
        $debugStmt->bind_param('s', $email);
        $debugStmt->execute();
        $debugResult = $debugStmt->get_result();
        
        if ($debugResult->num_rows > 0) {
            $debugRow = $debugResult->fetch_assoc();
            error_log("OTP exists but: is_used=" . $debugRow['is_used'] . ", expires_at=" . $debugRow['expires_at']);
        } else {
            error_log("No OTP found for email: $email");
        }
        
        throw new Exception('Invalid or expired OTP');
    }

    $otpRecord = $result->fetch_assoc();
    $otpId = $otpRecord['id'];
    $stmt->close();

    // Mark OTP as used
    $updateSql = "UPDATE password_reset_otp SET is_used = 1 WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    
    if (!$stmt) {
        throw new Exception('Database error updating OTP');
    }

    $stmt->bind_param('i', $otpId);
    
    if (!$stmt->execute()) {
        error_log("Failed to mark OTP as used: " . $stmt->error);
        throw new Exception('Failed to update OTP status');
    }

    $stmt->close();

    // Generate reset token (valid for 15 minutes)
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    error_log("Generated reset token: $resetToken");

    // Store reset token
    $tokenSql = "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($tokenSql);
    
    if (!$stmt) {
        error_log("Token prepare failed: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('sss', $email, $resetToken, $tokenExpiresAt);

    if (!$stmt->execute()) {
        error_log("Token insert failed: " . $stmt->error);
        throw new Exception('Failed to generate reset token');
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['message'] = 'OTP verified successfully';
    $response['reset_token'] = $resetToken;
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    error_log("OTP Verification Error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
}
?>