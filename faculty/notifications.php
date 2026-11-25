<?php
$page = 'notifications'; $title = 'Notifications';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('faculty');

$conn = (new database())->connect();
notifications_handle($conn, 'faculty', currentUserId());     // ?count=1 (badge refresh), ?go=ID (open + mark read), POST mark-all-read

require '../includes/faculty_header.php';
notifications_render($conn, 'faculty', currentUserId());
require '../includes/footer.php';
