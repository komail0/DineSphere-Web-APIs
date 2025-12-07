<?php
// ============ users/verify_otp.php ============
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
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if (empty($email) || empty($otp)) {
        throw new Exception('Email and OTP are required');
    }

    // Hash the provided OTP
    $otpHash = hash('sha256', $otp);

    // Check if OTP matches, hasn't expired, and hasn't been used
    $verifySql = "SELECT id FROM password_reset_otp 
                  WHERE email = ? 
                  AND otp_hash = ? 
                  AND is_used = 0 
                  AND expires_at > NOW() 
                  LIMIT 1";

    $stmt = $conn->prepare($verifySql);
    $stmt->bind_param('ss', $email, $otpHash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired OTP');
    }

    $otpRecord = $result->fetch_assoc();
    $otpId = $otpRecord['id'];
    $stmt->close();

    // Mark OTP as used
    $updateSql = "UPDATE password_reset_otp SET is_used = 1 WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param('i', $otpId);
    $stmt->execute();
    $stmt->close();

    // Generate reset token (valid for 15 minutes)
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Store reset token
    $tokenSql = "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($tokenSql);
    $stmt->bind_param('sss', $email, $resetToken, $tokenExpiresAt);

    if (!$stmt->execute()) {
        throw new Exception('Failed to generate reset token');
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['message'] = 'OTP verified successfully';
    $response['reset_token'] = $resetToken;
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>