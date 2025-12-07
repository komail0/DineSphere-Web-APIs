<?php
// ============ users/confirm_password_reset.php ============
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
    $resetToken = isset($_POST['reset_token']) ? trim($_POST['reset_token']) : '';
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

    if (empty($resetToken) || empty($newPassword)) {
        throw new Exception('Reset token and password are required');
    }

    if (strlen($newPassword) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }

    // Validate token exists and is not expired
    $tokenSql = "SELECT email FROM password_reset_tokens 
                 WHERE token = ? 
                 AND is_used = 0 
                 AND expires_at > NOW() 
                 LIMIT 1";

    $stmt = $conn->prepare($tokenSql);
    $stmt->bind_param('s', $resetToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired reset token');
    }

    $tokenRecord = $result->fetch_assoc();
    $email = $tokenRecord['email'];
    $stmt->close();

    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password in users table
    $updateSql = "UPDATE users SET password_hash = ? WHERE email = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param('ss', $passwordHash, $email);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update password');
    }

    $stmt->close();

    // Mark token as used
    $markUsedSql = "UPDATE password_reset_tokens SET is_used = 1 WHERE token = ?";
    $stmt = $conn->prepare($markUsedSql);
    $stmt->bind_param('s', $resetToken);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    $response['success'] = true;
    $response['message'] = 'Password reset successfully';
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>