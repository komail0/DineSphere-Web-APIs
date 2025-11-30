<?php
require_once 'conn.php';


$sql = "SELECT * FROM test_users";  // updated table name
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$users = array();  // variable for storing rows
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

header('Content-Type: application/json');
echo json_encode($users);

$conn->close();
?>
