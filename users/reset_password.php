<?php
header('Content-Type: application/json');
require_once 'conn.php';

$user_id = $_POST['user_id'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($user_id) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password or user not found']);
}

$stmt->close();
$conn->close();
?>