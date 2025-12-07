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
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if (empty($email) || empty($otp)) {
        throw new Exception('Email and OTP are required');
    }

    // Fetch OTP from database
    $sql = "SELECT otp_code, expiry_time FROM otp_verification WHERE email = ? ORDER BY created_at DESC LIMIT 1";
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
        throw new Exception('No OTP found. Please request a new OTP.');
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $storedOtp = $row['otp_code'];
    $expiryTime = $row['expiry_time'];

    // Check if OTP has expired
    if (strtotime($expiryTime) < time()) {
        $conn->close();
        throw new Exception('OTP has expired. Please request a new OTP.');
    }

    // Verify OTP
    if ($otp !== $storedOtp) {
        $conn->close();
        throw new Exception('Invalid OTP. Please try again.');
    }

    // OTP is valid - Delete it so it can't be reused
    $deleteSql = "DELETE FROM otp_verification WHERE email = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('s', $email);
    $deleteStmt->execute();
    $deleteStmt->close();

    $conn->close();

    $response['success'] = true;
    $response['message'] = 'OTP verified successfully';
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if (strpos($e->getMessage(), 'required') !== false) {
        http_response_code(400);
    } elseif (strpos($e->getMessage(), 'Invalid OTP') !== false) {
        http_response_code(401);
    } else {
        http_response_code(500);
    }
    echo json_encode($response);
}
?>