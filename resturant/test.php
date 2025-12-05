<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;

header("Content-Type: application/json");

$cloudName = 'dswpsw0wb';
$apiKey = '551839219855211';
$apiSecret = 'FI1zvDw2X7hjmPBobYwKzPzADR4';

echo json_encode([
    "cloud_name" => $cloudName,
    "api_key" => $apiKey,
    "api_secret_set" => !empty($apiSecret),
    "api_secret_length" => strlen($apiSecret),
    "api_secret_first_4" => substr($apiSecret, 0, 4),
    "config_test" => "Testing Cloudinary config"
]);

try {
    $cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => $cloudName,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ]
    ]);
    
    echo "\nCloudinary object created successfully!";
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage();
}
?>
```

**Visit:** `https://dinesphere-web-apis-production.up.railway.app/resturant/test_cloudinary.php`

This will show if the credentials are being read correctly.

---

## Quick Fix: Verify No Special Characters

Sometimes credentials get corrupted. Double-check your `api_secret`:
```
FI1zvDw2X7hjmPBobYwKzPzADR4