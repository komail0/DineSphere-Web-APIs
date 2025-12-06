<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/profile_errors.log');
header('Content-Type: application/json');

$response = array();

try {
    require_once 'conn.php';
    require_once 'upload_image.php'; // Reuse restaurant's upload function
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Include error: " . $e->getMessage()]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.');
    }

    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : ''; 
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    
    // Handle image upload
    $base64Image = isset($_POST['base64_image']) ? $_POST['base64_image'] : '';
    $profileImageUrl = isset($_POST['profile_image_url']) ? trim($_POST['profile_image_url']) : null;

    if (empty($userId)) {
        throw new Exception('User ID is required');
    }
    if (empty($firstName) || empty($lastName) || empty($phone)) {
        throw new Exception('First Name, Last Name, and Phone are required.');
    }

    // If new image is uploaded (base64), upload to Cloudinary
    if (!empty($base64Image)) {
        $uploadResult = uploadBase64ImageToCloudinary($base64Image, 'dinesphere/users/profiles');
        
        if (!$uploadResult['success']) {
            throw new Exception('Image upload failed: ' . ($uploadResult['message'] ?? 'Unknown error'));
        }
        
        if (empty($uploadResult['url'])) {
            throw new Exception('Upload succeeded but no URL returned');
        }
        
        // Use the newly uploaded image URL
        $profileImageUrl = $uploadResult['url'];
    }
    // else: keep existing $profileImageUrl from POST (old URL)

    // Update profile with new or existing image URL
    $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, gender = ?, profile_image_url = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param('ssssss', $firstName, $lastName, $phone, $gender, $profileImageUrl, $userId);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
        if (!empty($profileImageUrl)) {
            $response['image_url'] = $profileImageUrl;
        }
    } else {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>