<?php
require_once 'conn.php';

$sql = "SELECT * FROM restaurant";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$restaurants = array();
while ($row = $result->fetch_assoc()) {
    $restaurants[] = $row;
}

header('Content-Type: application/json');
echo json_encode($restaurants);

$conn->close();
?>
