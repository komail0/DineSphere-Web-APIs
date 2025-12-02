<?php


header('Content-Type: application/json');

require_once 'conn.php';

// Helper: send JSON response and exit
function respond($status, $data = []) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed. Use POST.']);
}

// Required fields
if (empty($_POST['identifier']) || empty($_POST['password'])) {
    respond(400, ['success' => false, 'message' => 'Missing required fields: identifier and password']);
}

$identifier = trim($_POST['identifier']);
$password = trim($_POST['password']);

// Check if identifier is email or business name
$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

// Prepare SQL query based on identifier type
if ($isEmail) {
    $sql = "SELECT restaurant_id, business_name, name_per_cnic, last_name, business_type, 
                   business_category, business_update, email, phone, password_hash, 
                   restaurant_location, is_verified 
            FROM restaurant 
            WHERE email = ? 
            LIMIT 1";
} else {
    $sql = "SELECT restaurant_id, business_name, name_per_cnic, last_name, business_type, 
                   business_category, business_update, email, phone, password_hash, 
                   restaurant_location, is_verified 
            FROM restaurant 
            WHERE business_name = ? 
            LIMIT 1";
}

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        respond(401, ['success' => false, 'message' => 'Invalid credentials']);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        respond(401, ['success' => false, 'message' => 'Invalid credentials']);
    }
    
    // Remove password_hash from response
    unset($user['password_hash']);
    
    // Successful login
    respond(200, [
        'success' => true, 
        'message' => 'Login successful',
        'user' => $user
    ]);
    
} else {
    respond(500, ['success' => false, 'message' => 'DB error: failed to prepare query']);
}

// Close connection (unreachable but safe)
$conn->close();
?>