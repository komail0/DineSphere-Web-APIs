<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$phone = $_POST['phone'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($phone) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE phone = ?");
$stmt->bind_param("ss", $hashed_password, $phone);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}

$stmt->close();
$conn->close();
?>