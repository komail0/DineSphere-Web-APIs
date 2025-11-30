<?php
// Include the database connection
require_once 'conn.php';

// Query to fetch all restaurants
$sql = "SELECT * FROM restaurant";
$result = $conn->query($sql);

$restaurants = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}

// Set header to JSON
header('Content-Type: application/json');

// Output JSON
echo json_encode($restaurants);

// Close connection
$conn->close();
?>
