<?php
// login(post).php
// Accepts POST data to authenticate a user by business name or email.

header('Content-Type: application/json');

// Assuming 'conn.php' exists and provides $conn
require_once 'conn.php';

/**
 * Helper: send JSON response and exit
 * (Copied from signup(post).php context)
 */
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
$required = ['identifier', 'password'];

$input = [];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        respond(400, ['success' => false, 'message' => "Missing required field: $field"]);
    }
    $input[$field] = trim($_POST[$field]);
}

$identifier = $input['identifier'];
$password = $input['password'];

// 1. Search for user by business_name OR email
// The identifier is bound to both placeholders to check against either field.
$sql = "SELECT restaurant_id, password_hash FROM restaurant WHERE business_name = ? OR email = ? LIMIT 1";

if ($stmt = $conn->prepare($sql)) {
    // Bind the identifier to both placeholders
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($restaurant_id, $passwordHash);
        $stmt->fetch();
        $stmt->close();

        // 2. Verify password against hash
        if (password_verify($password, $passwordHash)) {
            // Success: Respond with a success message and restaurant_id
            respond(200, [
                'success' => true,
                'message' => 'Login successful',
                'restaurant_id' => $restaurant_id
            ]);
        } else {
            // Password mismatch: 401 Unauthorized
            respond(401, ['success' => false, 'message' => 'Invalid credentials.']);
        }
    } else {
        $stmt->close();
        // User not found: 401 Unauthorized
        respond(401, ['success' => false, 'message' => 'Invalid credentials.']);
    }
} else {
    // DB preparation error: 500 Internal Server Error
    respond(500, ['success' => false, 'message' => 'DB error: failed to prepare login query']);
}

$conn->close();

