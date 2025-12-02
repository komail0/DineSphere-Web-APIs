<?php
// signup(post).php
// Accepts POST data to create a new `restaurant` row.

// CRITICAL: Disable error display and start output buffering BEFORE anything else
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Clear any accidental output
ob_clean();

require_once 'conn.php';

// Helper: send JSON response and exit
function respond($status, $data = []) {
    if (ob_get_length()) ob_clean();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed. Use POST.']);
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    respond(500, ['success' => false, 'message' => 'Database connection failed']);
}

// Required fields
$required = [
    'business_name', 'name_per_cnic', 'last_name',
    'business_type', 'business_category', 'business_update',
    'email', 'phone', 'password'
];

$input = [];
foreach ($required as $field) {
    if (empty($_POST[$field]) && $_POST[$field] !== '0') {
        respond(400, ['success' => false, 'message' => "Missing required field: $field"]);
    }
    $input[$field] = trim($_POST[$field]);
}

// Optional fields
$restaurant_location = isset($_POST['restaurant_location']) && $_POST['restaurant_location'] !== ''
    ? (int) $_POST['restaurant_location'] : null;

// Validate email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    respond(400, ['success' => false, 'message' => 'Invalid email address']);
}

// Basic phone validation (digits, + and - allowed)
$phoneClean = preg_replace('/[^0-9+\-]/', '', $input['phone']);
if (strlen($phoneClean) < 7) {
    respond(400, ['success' => false, 'message' => 'Invalid phone number']);
}
$input['phone'] = $phoneClean;

// Password hashing
$passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
if ($passwordHash === false) {
    respond(500, ['success' => false, 'message' => 'Password hashing failed']);
}

// Check uniqueness of email and phone
$checkSql = "SELECT restaurant_id FROM restaurant WHERE email = ? OR phone = ? LIMIT 1";
if ($stmt = $conn->prepare($checkSql)) {
    $stmt->bind_param('ss', $input['email'], $input['phone']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        respond(409, ['success' => false, 'message' => 'Email or phone already registered']);
    }
    $stmt->close();
} else {
    respond(500, ['success' => false, 'message' => 'DB error: failed to prepare uniqueness check']);
}

// Generate OTP (optional) and insert
$otp = random_int(100000, 999999);

$insertSql = "INSERT INTO restaurant
    (business_name, name_per_cnic, last_name, business_type, business_category, business_update, email, phone, password_hash, restaurant_location, otp)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($insertSql)) {
    // restaurant_location may be null
    if ($restaurant_location === null) {
        $nullLoc = null;
        $stmt->bind_param('sssssssssis',
            $input['business_name'], $input['name_per_cnic'], $input['last_name'],
            $input['business_type'], $input['business_category'], $input['business_update'],
            $input['email'], $input['phone'], $passwordHash, $nullLoc, $otp
        );
    } else {
        $stmt->bind_param('ssssssssiis',
            $input['business_name'], $input['name_per_cnic'], $input['last_name'],
            $input['business_type'], $input['business_category'], $input['business_update'],
            $input['email'], $input['phone'], $passwordHash, $restaurant_location, $otp
        );
    }

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        respond(201, ['success' => true, 'message' => 'Signup successful', 'restaurant_id' => $newId, 'otp' => $otp]);
    } else {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        respond(500, ['success' => false, 'message' => 'Insert failed', 'error' => $err]);
    }
} else {
    $conn->close();
    respond(500, ['success' => false, 'message' => 'DB error: failed to prepare insert']);
}
?>