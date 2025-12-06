<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = array();
include 'conn.php';

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
    $restaurantId = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    // Validate required fields
    if ($restaurantId <= 0) {
        $response['success'] = false;
        $response['message'] = 'Valid restaurant ID is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($title)) {
        $response['success'] = false;
        $response['message'] = 'Notification title is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (empty($message)) {
        $response['success'] = false;
        $response['message'] = 'Notification message is required';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Insert notification into database
    $sql = "INSERT INTO notifications (restaurant_id, title, message, created_at) VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['success'] = false;
        $response['message'] = 'Database error';
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param('iss', $restaurantId, $title, $message);

    if ($stmt->execute()) {
        $notificationId = $conn->insert_id;
        $stmt->close();

        // Get device tokens of users who saved this restaurant
        $tokenSql = "SELECT DISTINCT u.device_token 
                     FROM users u 
                     INNER JOIN saved_restaurants sr ON u.user_id = sr.user_id 
                     WHERE sr.restaurant_id = ? AND u.device_token IS NOT NULL AND u.device_token != ''";
        
        $tokenStmt = $conn->prepare($tokenSql);
        
        if (!$tokenStmt) {
            $conn->close();
            $response['success'] = true;
            $response['message'] = 'Notification saved but failed to fetch device tokens';
            $response['notification_id'] = $notificationId;
            http_response_code(201);
            echo json_encode($response);
            exit;
        }

        $tokenStmt->bind_param('i', $restaurantId);
        $tokenStmt->execute();
        $tokenResult = $tokenStmt->get_result();

        $deviceTokens = array();
        while ($row = $tokenResult->fetch_assoc()) {
            $deviceTokens[] = $row['device_token'];
        }

        $tokenStmt->close();
        $conn->close();

        // Send FCM notifications
        $sentCount = 0;
        if (!empty($deviceTokens)) {
            $sentCount = sendFCMNotificationsV1($deviceTokens, $title, $message);
        }

        $response['success'] = true;
        $response['message'] = 'Notification sent successfully';
        $response['notification_id'] = $notificationId;
        $response['users_notified'] = $sentCount;
        http_response_code(201);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        $response['success'] = false;
        $response['message'] = 'Failed to send notification: ' . $error;
        http_response_code(500);
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
    
    $response['success'] = false;
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

function sendFCMNotificationsV1($deviceTokens, $title, $message) {
    // Firebase credentials
    $projectId = 'dinesphere-dc180';
    $privateKey = '-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC4QzGfEfIFJn6Y
CmM8wopT+NW61i0OzPnGXCi4cWMf/acpdh+HV58/urWZCriHKJwnDvH96ly9Mkew
7XvrPxZ5gR7L5qVhTHWCYY4m2+R09FGgHoCq9uLV0Q86nQekv1VtEJ2/7dlWZE74
Ba7Fy05zWsNHRBRse9VxjQcpvT4Bu8jPbDNC3/mDyuyDFVCEooAKAv3+hcUkU5uo
kXGPYYr1Gtq3tQqNTb5F9cZRAzsEbFxagprVbD1FQ7VX638HMaGvFDHM+gi5fIfS
prme/2XV/OIoS0BXKzFfQnx6RqB/Y6OEUTvwSMZHdMxLCxu6gXY1iEdx0hkTwRII
3GMKzAsdAgMBAAECggEAIGbsgGjy8rXouArHlaUuFwMgbnkANmSUHa4SGRETWcpM
jW1wsOeXIOlPyshIV7gr8XBe3IRMZ5bCZZn1WxJefOJYTInUFi6QUWufSDUN0QUv
n2UNwkKk8+2N7IQ1lmJw/rtXyirkt5zvCA1TxQNx2bYhtdQCdWs3Rv8CPfRBxaL3
tpuGWnxLJlSwYHs27BC/7A/kZQOes/7H+8khX4RWZZUIfBThW/6zL9f512FwwsSN
E6gDRXYLsHtMkREZMhltUwe2XJocQgUwcpZAAzLTjKcbjf+IENd5x2woC4aHr2QR
/gJ70rvWFtXevH5CMCdlZojlgQn5lrrGMxXc2yWD4QKBgQDvdcANEKyQ0v1f8maX
Z839yAoUxSSwZ/+K5dNDqWGc5jeeobTb89SwsN2gURBVj2J7/HFvXDXJxCKwZyT/
SKfqvJcEOaBdVToW7AcHJQJufKhB12J2hfml7GZeLWuPYxnFeRMbMpYhwReYIOAP
Qofco7NFhYhgfIQdupR388hCjQKBgQDE/WoQlQO2pXSUR9TG7FhhOvfS0uhmrco7
G/6PzSYx1V4B6s85hS3x7PH+GKqOBSGD3Zqpg4NDJRk4pUG74hDXYURhYbb4MSto
AmLoWHMDu8QzRHTry6wsAqv4ohOKQ9Ku5NYER9cfCjPOpuAHj7grLlS8CmYeRMuq
tE5tndIO0QKBgALLkBNW93y1Scnd7X6k8o2c0SlO58+7VwtLBX1Ls9z9/vY2EwNi
REBPwDaH27Xz94VU/An9vI7/YBxJB/CG65bc3rJo7ctJHGV6GdbmgrHBeMFT/008
4R4jtUoyI4hH8twQPr3ZiFEajOj0sUjcUPOtYYFVPrNJoM0sCWGhEdxdAoGBAJoS
AjT596+Q9P9MtzTmgbF6Z35zCuXUI4nbuVxLfgYX9bDWMEGy0l6XjiXIsQznInF6
j47pl26aw4E0b5c9lyJ9pvfrbynreyGcTDOhikNvRmM6tZ/+6qh5ZnvOeC36Ifw8
as9qSOy0FBUbG7mOROMxF4EDOR+PeyQGZhkCCNshAoGBAM21+2gwgYMdfp4rVwEH
d69mkxAWrxtxwzrO8b4jFffjvJqrAhjPKQfB3JOu9690N6Oi5LQioO2zjWV0EArK
+cPlYBHdFpjo0hhiSnyYHNMat65k0dFrYvrk6ArgBGRjTNoAx/1QUDoROpo4glsz
CGpiw5fmKgBy7EX2izpV0RmA
-----END PRIVATE KEY-----';
    $clientEmail = 'firebase-adminsdk-fbsvc@dinesphere-dc180.iam.gserviceaccount.com';
    
    // Generate OAuth2 access token
    $accessToken = getAccessToken($privateKey, $clientEmail);
    
    if (!$accessToken) {
        error_log('Failed to generate FCM access token');
        return 0;
    }
    
    $sentCount = 0;
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    
    foreach ($deviceTokens as $token) {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message
                ],
                'data' => [
                    'title' => $title,
                    'message' => $message,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'restaurant_notifications'
                    ]
                ]
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($result === FALSE) {
            error_log('FCM cURL Error: ' . curl_error($ch));
        } elseif ($httpCode == 200) {
            $sentCount++;
        } else {
            error_log('FCM Error Response (HTTP ' . $httpCode . '): ' . $result);
        }
        
        curl_close($ch);
    }
    
    return $sentCount;
}

function getAccessToken($privateKey, $clientEmail) {
    $now = time();
    
    // Create JWT header
    $header = json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]);
    
    // Create JWT payload
    $payload = json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);
    
    // Encode
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    // Create signature
    $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;
    $signature = '';
    openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // Create JWT
    $jwt = $signatureInput . '.' . $base64UrlSignature;
    
    // Exchange JWT for access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === FALSE || $httpCode != 200) {
        error_log('OAuth2 Token Error: ' . $response);
        return null;
    }
    
    $data = json_decode($response, true);
    return isset($data['access_token']) ? $data['access_token'] : null;
}
?>