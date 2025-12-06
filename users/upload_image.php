<?php
// Include Cloudinary SDK
require_once __DIR__ . '/../vendor/autoload.php';

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

/**
 * Upload base64 encoded image to Cloudinary
 * @param string $base64Image Base64 encoded image string
 * @param string $folder Cloudinary folder name (default: dinesphere)
 * @return array Result array with success status and URL or error message
 */
function uploadBase64ImageToCloudinary($base64Image, $folder = 'dinesphere') {
    global $cloudinary;

    if (empty($base64Image)) {
        return ["success" => false, "message" => "No image provided"];
    }

    try {
        // Remove data URI prefix if present (data:image/jpeg;base64,)
        if (strpos($base64Image, 'data:image') === 0) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
        }

        // Decode Base64 to temp file
        $imgData = base64_decode($base64Image, true);
        
        if ($imgData === false) {
            return ["success" => false, "message" => "Invalid base64 image data"];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'img_');
        if ($tempFile === false) {
            return ["success" => false, "message" => "Failed to create temp file"];
        }
        
        file_put_contents($tempFile, $imgData);

        // Upload to Cloudinary
        $result = $cloudinary->uploadApi()->upload($tempFile, [
            "folder" => $folder,
            "resource_type" => "auto"
        ]);

        $imageUrl = $result['secure_url'];

        // Remove temp file
        @unlink($tempFile);

        return ["success" => true, "url" => $imageUrl];

    } catch (Exception $e) {
        // Clean up temp file if exists
        if (isset($tempFile) && file_exists($tempFile)) {
            @unlink($tempFile);
        }
        
        error_log("Cloudinary upload error: " . $e->getMessage());
        return ["success" => false, "message" => $e->getMessage()];
    }
}
?>