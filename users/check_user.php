<?php
// users/check_user.php
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

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    $sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'User found';
    } else {
        $response['success'] = false;
        $response['message'] = 'Email not found in our records';
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>