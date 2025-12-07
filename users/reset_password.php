<?php
header('Content-Type: application/json');
require_once 'conn.php';

// Get email and new password from POST
$email = $_POST['email'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Debug logging
error_log("Reset Password - Email: " . $email . ", Password length: " . strlen($new_password));

if (empty($email) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// First verify the user exists by email
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Now update the password
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password or email not found']);
}

$stmt->close();
$conn->close();
?>
