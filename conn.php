<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Railway internal connection
$host = 'mysql.railway.internal';
$port = 3306;
$user = 'root';
$password = 'TGIZWAWItMMzmgxgRJozFHsTpWsZDbSt';
$database = 'railway';

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
