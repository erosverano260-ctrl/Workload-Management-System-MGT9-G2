<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
requireRoleApi('admin');
header('Content-Type: application/json');
$conn = (new database())->connect();
echo json_encode($conn->query("SELECT room_id, room_name FROM rooms ORDER BY room_name")->fetch_all(MYSQLI_ASSOC));
