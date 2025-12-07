<?php
// Suppress HTML error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/password_reset_errors.log');

// Clean output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

// Set JSON header immediately
header('Content-Type: application/json');

// Function to send clean JSON response
function sendResponse($success, $message, $httpCode = 200) {
    if (ob_get_level()) ob_clean();
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// Log function
function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
}

try {
    logDebug("=== Password Reset Request ===");
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logDebug("Invalid method: " . $_SERVER['REQUEST_METHOD']);
        sendResponse(false, 'Method not allowed', 405);
    }
    
    // Include database connection
    if (!file_exists(__DIR__ . '/conn.php')) {
        logDebug("conn.php not found");
        sendResponse(false, 'Server configuration error', 500);
    }
    
    require_once __DIR__ . '/conn.php';
    
    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        logDebug("Database connection failed");
        sendResponse(false, 'Database connection failed', 500);
    }
    
    // Get and validate inputs
    $user_id = isset($_POST['user_id']) ? trim((string)$_POST['user_id']) : '';
    $new_password = isset($_POST['new_password']) ? trim((string)$_POST['new_password']) : '';
    
    logDebug("User ID: '$user_id' | Password length: " . strlen($new_password));
    
    // Validation
    if (empty($user_id)) {
        logDebug("User ID is empty");
        sendResponse(false, 'User ID is required', 400);
    }
    
    if (empty($new_password)) {
        logDebug("Password is empty");
        sendResponse(false, 'Password is required', 400);
    }
    
    if (strlen($new_password) < 6) {
        logDebug("Password too short: " . strlen($new_password));
        sendResponse(false, 'Password must be at least 6 characters', 400);
    }
    
    // Check if user exists
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    
    if (!$check_stmt) {
        logDebug("Prepare failed: " . $conn->error);
        sendResponse(false, 'Database error', 500);
    }
    
    $check_stmt->bind_param("s", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        logDebug("User not found: $user_id");
        $check_stmt->close();
        $conn->close();
        sendResponse(false, 'User not found', 404);
    }
    
    $check_stmt->close();
    logDebug("User found, proceeding with password update");
    
    // Hash password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    if ($hashed_password === false) {
        logDebug("Password hashing failed");
        $conn->close();
        sendResponse(false, 'Password hashing failed', 500);
    }
    
    // Update password
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    
    if (!$update_stmt) {
        logDebug("Update prepare failed: " . $conn->error);
        $conn->close();
        sendResponse(false, 'Database error', 500);
    }
    
    $update_stmt->bind_param("ss", $hashed_password, $user_id);
    
    if (!$update_stmt->execute()) {
        logDebug("Update execute failed: " . $update_stmt->error);
        $update_stmt->close();
        $conn->close();
        sendResponse(false, 'Failed to update password', 500);
    }
    
    $affected_rows = $update_stmt->affected_rows;
    logDebug("Affected rows: $affected_rows");
    
    $update_stmt->close();
    $conn->close();
    
    if ($affected_rows > 0) {
        logDebug("SUCCESS: Password updated for user: $user_id");
        sendResponse(true, 'Password updated successfully', 200);
    } else {
        logDebug("WARNING: No rows affected for user: $user_id");
        sendResponse(false, 'Password update failed - no changes made', 500);
    }
    
} catch (Exception $e) {
    logDebug("EXCEPTION: " . $e->getMessage());
    logDebug("Stack: " . $e->getTraceAsString());
    sendResponse(false, 'Server error occurred', 500);
} catch (Error $e) {
    logDebug("FATAL ERROR: " . $e->getMessage());
    logDebug("Stack: " . $e->getTraceAsString());
    sendResponse(false, 'Fatal server error', 500);
}

// Should never reach here
logDebug("ERROR: Reached end without sending response");
sendResponse(false, 'Unknown error', 500);
?>