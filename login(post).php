<?php
// login(post).php
// Accepts POST data to authenticate a restaurant user.

ob_start();                  
error_reporting(0);          
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');




// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Clear output buffer to remove any unwanted output
ob_clean();

// Helper: send JSON response and exit
function respond($status, $data = []) {
    // Clear any existing output
    if (ob_get_length()) ob_clean();
    
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
    // Flush and end output buffering
    ob_end_flush();
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed. Use POST.']);
}

// Check if conn.php exists
if (!file_exists('conn.php')) {
    respond(500, ['success' => false, 'message' => 'Database connection file not found']);
}

// Include database connection
try {
    require_once 'conn.php';
} catch (Exception $e) {
    respond(500, ['success' => false, 'message' => 'Failed to load database connection']);
}

// Check database connection
if (!isset($conn)) {
    respond(500, ['success' => false, 'message' => 'Database connection not initialized']);
}

if ($conn->connect_error) {
    respond(500, ['success' => false, 'message' => 'Database connection failed']);
}

// Required fields
if (!isset($_POST['identifier']) || !isset($_POST['password'])) {
    respond(400, ['success' => false, 'message' => 'Missing required fields: identifier and password']);
}

if (empty($_POST['identifier']) || empty($_POST['password'])) {
    respond(400, ['success' => false, 'message' => 'Identifier and password cannot be empty']);
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

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, ['success' => false, 'message' => 'Failed to prepare database query']);
}

$stmt->bind_param('s', $identifier);

if (!$stmt->execute()) {
    $stmt->close();
    respond(500, ['success' => false, 'message' => 'Failed to execute query']);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    respond(401, ['success' => false, 'message' => 'Invalid credentials']);
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    $conn->close();
    respond(401, ['success' => false, 'message' => 'Invalid credentials']);
}

// Remove password_hash from response
unset($user['password_hash']);

// Close connection
$conn->close();

// Successful login
respond(200, [
    'success' => true, 
    'message' => 'Login successful',
    'user' => $user
]);
?>