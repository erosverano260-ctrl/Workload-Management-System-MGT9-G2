<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
requireRoleApi('admin');
header('Content-Type: application/json');
$conn = (new database())->connect();
$roomId = (int)($_GET['room_id'] ?? 0);
$stmt = $conn->prepare("SELECT schedule_id, day_of_week, start_time, end_time FROM schedules WHERE room_id = ?");
$stmt->bind_param('i', $roomId);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
