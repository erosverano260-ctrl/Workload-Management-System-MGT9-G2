<?php
header('Content-Type: application/json');

require '../includes/db.php';

$db = new database();
$conn = $db->connect();

$roomId = (int)($_GET['room_id'] ?? 0);

$stmt = $conn->prepare("SELECT schedule_id, day_of_week, start_time, end_time FROM schedules WHERE room_id = ?");
$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

echo json_encode($bookings);