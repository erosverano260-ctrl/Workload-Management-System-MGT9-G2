<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
requireRoleApi('admin');
header('Content-Type: application/json');
$conn = (new database())->connect();
$year = (int)($_GET['year_level'] ?? 0);
$stmt = $conn->prepare("SELECT course_id, course_code, course_name FROM courses WHERE year_level = ? AND status = 'Open' ORDER BY course_code");
$stmt->bind_param('i', $year);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
