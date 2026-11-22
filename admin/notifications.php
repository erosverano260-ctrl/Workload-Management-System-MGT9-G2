<?php
$page = 'notifications'; $title = 'Notifications';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

$conn = (new database())->connect();
notifications_handle($conn, 'admin', currentUserId());     // ?count=1 (badge refresh), ?go=ID (open + mark read), POST mark-all-read

require '../includes/header.php';
notifications_render($conn, 'admin', currentUserId());
require '../includes/footer.php';
