<?php
header('Content-Type: application/json');

require '../includes/db.php';

$db = new database();
$conn = $db->connect();

$result = $conn->query("SELECT room_id, room_code, room_name, capacity FROM rooms ORDER BY room_name");
$rooms = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($rooms);