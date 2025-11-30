?>
<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Railway internal connection
$host = 'mysql.railway.internal';
$port = 3306; // Internal port
$user = 'root'; // MySQL username (from Railway plugin)
$password = 'TGIZWAWItMMzmgxgRJozFHsTpWsZDbSt'; // MySQL password
$database = 'railway'; // Database name

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
