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
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

    if (empty($email) || empty($newPassword)) {
        throw new Exception('Email and new password are required');
    }

    if (strlen($newPassword) < 6) {
        throw new Exception('Password must be at least 6 characters long');
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
        throw new Exception('User not found');
    }

    $stmt->close();

    // Hash the new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $updateSql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        throw new Exception('Failed to prepare update query');
    }

    $updateStmt->bind_param('ss', $passwordHash, $email);

    if ($updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();

        $response['success'] = true;
        $response['message'] = 'Password reset successfully';
        http_response_code(200);
        echo json_encode($response);
    } else {
        throw new Exception('Failed to update password');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if (strpos($e->getMessage(), 'required') !== false || strpos($e->getMessage(), 'at least') !== false) {
        http_response_code(400);
    } elseif ($e->getMessage() == 'User not found') {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
    echo json_encode($response);
}
?>