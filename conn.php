<?php
// Railway Database Connection

$host = "turntable.proxy.rlwy.net";  // replace with your Railway external host
$port = 58937;                                // replace with your Railway port
$user = "root";                               // Railway username
$password = "TGIZWAWItMMzmgxgRJozFHsTpWsZDbSt"; // Railway password
$database = "railway";                        // Railway database name

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>
