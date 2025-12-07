<?php
header('Content-Type: application/json');
require_once 'conn.php';

$user_id = $_POST['user_id'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Debug logging
error_log("Reset Password - User ID: " . $user_id . ", Password length: " . strlen($new_password));

if (empty($user_id) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// First verify the user exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
$check_stmt->bind_param("s", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Now update the password
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$stmt->bind_param("ss", $hashed_password, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password or user not found']);
}

$stmt->close();
$conn->close();
?>