<?php
require '../includes/db.php'; 
$db = new database();
$conn = $db->connect();

$year = (int)($_GET['year_level'] ?? 0);

$stmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses WHERE year_level = ? AND status = 'Open'");
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

header('Content-Type: application/json');
echo json_encode($courses);