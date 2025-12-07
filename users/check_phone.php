<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$phone = $_GET['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['exists' => false, 'message' => 'Phone number required']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false, 'message' => 'Phone not registered']);
}

$stmt->close();
$conn->close();
?>