<?php
header('Content-Type: application/json');

require '../includes/db.php';

$db = new database();
$conn = $db->connect();

$result = $conn->query("SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name");
$faculty = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($faculty);