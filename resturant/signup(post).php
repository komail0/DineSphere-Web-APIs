<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';
require_once 'upload_image.php';

try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['success'] = false;
        $response['message'] = 'Method not allowed. Use POST.';
        http_response_code(405);
        echo json_encode($response);
        exit;
    }

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        $response['success'] = false;
        $response['message'] = 'Database connection failed';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    // Get POST data
    $businessName = isset($_POST['business_name']) ? trim($_POST['business_name']) : '';
    $nameCnic = isset($_POST['name_per_cnic']) ? trim($_POST['name_per_cnic']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $businessType = isset($_POST['business_type']) ? trim($_POST['business_type']) : '';
    $businessCategory = isset($_POST['business_category']) ? trim($_POST['business_category']) : '';
    $businessUpdate = isset($_POST['business_update']) ? trim($_POST['business_update']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $base64Image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';

    // Validate required fields
    if (empty($businessName) || empty($nameCnic) || empty($lastName) || 
        empty($businessType) || empty($businessCategory) || empty($businessUpdate) ||
        empty($email) || empty($phone) || empty($password)) {
        $response['success'] = false;
        $response['message'] = 'All required fields must be filled';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate restaurant image
    if (empty($base64Image)) {
        $response['success'] = false;
        $response['message'] = 'Restaurant photo is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['success'] = false;
        $response['message'] = 'Invalid email address';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validate phone
    $phoneClean = preg_replace('/[^0-9+\-]/', '', $phone);
    if (strlen($phoneClean) < 7) {
        $response['success'] = false;
        $response['message'] = 'Invalid phone number';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Upload restaurant image to Cloudinary
    $uploadResult = uploadBase64ImageToCloudinary($base64Image, 'dinesphere/restaurants');

    if (!$uploadResult['success']) {
        http_response_code(400);
        $response['success'] = false;
        $response['message'] = 'Image upload failed: ' . ($uploadResult['message'] ?? 'Unknown error');
        echo json_encode($response);
        exit;
    }

    if (empty($uploadResult['url'])) {
        http_response_code(500);
        $response['success'] = false;
        $response['message'] = 'Upload succeeded but no URL returned';
        echo json_encode($response);
        exit;
    }

    $restaurantImageUrl = $uploadResult['url'];

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Check if email or phone already exists
    $checkSql = "SELECT restaurant_id FROM restaurant WHERE email = ? OR phone = ? LIMIT 1";
    $stmt = $conn->prepare($checkSql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ss', $email, $phoneClean);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Email or phone already registered';
        http_response_code(409);
        echo json_encode($response);
        exit;
    }
    $stmt->close();

    // Generate OTP
    $otp = rand(100000, 999999);

    // Insert new restaurant with image
    $insertSql = "INSERT INTO restaurant (business_name, name_per_cnic, last_name, business_type, 
                  business_category, business_update, email, phone, password_hash, restaurant_image, otp) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('ssssssssssi', $businessName, $nameCnic, $lastName, 
                     $businessType, $businessCategory, $businessUpdate, 
                     $email, $phoneClean, $passwordHash, $restaurantImageUrl, $otp);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        $response['success'] = true;
        $response['message'] = 'Signup successful';
        $response['restaurant_id'] = $newId;
        $response['restaurant_image'] = $restaurantImageUrl;
        $response['otp'] = $otp;
        http_response_code(201);
        echo json_encode($response);
    } else {
        $stmt->close();
        $conn->close();
        $response['success'] = false;
        $response['message'] = 'Failed to create account';
        http_response_code(500);
        echo json_encode($response);
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>