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

    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : ''; 
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $profileImageUrl = isset($_POST['profile_image_url']) ? trim($_POST['profile_image_url']) : null; 

    if (empty($userId)) {
        throw new Exception('User ID is required');
    }
    if (empty($firstName) || empty($lastName) || empty($phone)) {
        throw new Exception('First Name, Last Name, and Phone are required.');
    }

    // UPDATED SQL: All fields being updated
    $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, gender = ?, profile_image_url = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    // UPDATED bind_param: Including all six parameters
    $stmt->bind_param('ssssss', $firstName, $lastName, $phone, $gender, $profileImageUrl, $userId);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
    } else {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>