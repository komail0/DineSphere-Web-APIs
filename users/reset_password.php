<?php
// Suppress HTML error output - log only
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/password_reset_errors.log');

// Set JSON header first
header('Content-Type: application/json');

// Start output buffering to catch any stray output
ob_start();

try {
    require_once 'conn.php';

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get and validate inputs
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

    // Debug logging
    error_log("Reset Password - User ID: " . $user_id . ", Password length: " . strlen($new_password));

    // Validate inputs
    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }
    
    if (empty($new_password)) {
        throw new Exception('New password is required');
    }
    
    if (strlen($new_password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }

    // Verify user exists
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    if (!$check_stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $check_stmt->bind_param("s", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        throw new Exception('User not found');
    }
    $check_stmt->close();

    // Hash the password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    if ($hashed_password === false) {
        throw new Exception('Password hashing failed');
    }

    // Update the password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $hashed_password, $user_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update password: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Password update failed - no rows affected');
    }

    $stmt->close();
    $conn->close();
    
    // Clear any buffered output before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password updated successfully'
    ]);

} catch (Exception $e) {
    // Clear any buffered output
    ob_clean();
    
    error_log("Password Reset Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>