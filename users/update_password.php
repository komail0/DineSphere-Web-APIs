<?php
// users/update_password.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Use POST method');
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email) || empty($password)) {
        throw new Exception('Email and new password are required');
    }

    // Hash the new password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password_hash = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $passwordHash, $email);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Password updated successfully';
    } else {
        throw new Exception('Failed to update password');
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>