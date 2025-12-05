<?php
// users/update_location.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include 'conn.php';

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
    $lat = isset($_POST['latitude']) ? $_POST['latitude'] : '';
    $lng = isset($_POST['longitude']) ? $_POST['longitude'] : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';

    if (!empty($userId)) {
        $stmt = $conn->prepare("UPDATE users SET latitude = ?, longitude = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("ddsi", $lat, $lng, $address, $userId);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Location updated";
        } else {
            $response['success'] = false;
            $response['message'] = "Failed to update database";
        }
    } else {
        $response['success'] = false;
        $response['message'] = "User ID missing";
    }
} else {
    $response['success'] = false;
    $response['message'] = "Invalid Request";
}

echo json_encode($response);
?>