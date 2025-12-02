<?php
// Include database connection
require_once 'conn.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include Cloudinary SDK
require_once 'vendor/autoload.php';
use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;

// Cloudinary configuration
$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => 'dswpsw0wb',
        'api_key'    => '551839219855211',
        'api_secret' => 'FI1zvDw2X7hjmPBobYwKzPzADR4',
    ]
]);


function uploadBase64Image($base64Image, $table = 'user_images', $column = 'image_path') {
    global $conn, $cloudinary;

    if (!$base64Image) {
        return json_encode(["success" => false, "message" => "No image provided"]);
    }

    // Decode Base64 to temp file
    $imgData = base64_decode($base64Image);
    $tempFile = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tempFile, $imgData);

    // Upload to Cloudinary
    try {
        $result = (new UploadApi())->upload($tempFile, ["folder" => "dinesphere_images"]);
        $imageUrl = $result['secure_url'];

        // Insert URL into Railway DB
        $query = "INSERT INTO `$table` (`$column`) VALUES ('$imageUrl')";
        if ($conn->query($query) === TRUE) {
            unlink($tempFile); // remove temp file
            return json_encode(["success" => true, "url" => $imageUrl]);
        } else {
            unlink($tempFile);
            return json_encode(["success" => false, "message" => "DB insert failed"]);
        }

    } catch (Exception $e) {
        unlink($tempFile);
        return json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// Example usage: check if POST has base64_image
if (isset($_POST['base64_image'])) {
    echo uploadBase64Image($_POST['base64_image']);
} else {
    echo json_encode(["success" => false, "message" => "No image sent"]);
}
