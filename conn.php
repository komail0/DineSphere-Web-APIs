<?php
// Railway Database Connection

$host = "containers-us-west-43.railway.app";  // replace with your Railway external host
$port = 3306;                                // replace with your Railway port
$user = "root";                               // Railway username
$password = "TGIZWAWItMMzmgxgRJozFHsTpWsZDbSt"; // Railway password
$database = "railway";                        // Railway database name

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: uncomment to test connection
// echo "Connected successfully!";
?>
