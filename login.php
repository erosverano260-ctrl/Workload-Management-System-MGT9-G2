<?php
require 'includes/auth.php';
require 'includes/data.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Demo-only credential check. Replace with a real users table + password_verify().
    $user = trim($_POST['faculty_user'] ?? '');
    $pass = $_POST['faculty_pass'] ?? '';
    if ($user !== '' && $pass === 'faculty123') {
        $_SESSION['role'] = 'faculty';
        $_SESSION['faculty_name'] = $user;
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid faculty credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — EE Enrollment Portal</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-brand">
        <img src="assets/ee-logo.svg" alt="logo">
        <div>
            <strong>Institute of Integrated Electrical Engineers</strong>
            <small>Faculty Enrollment Portal</small>
        </div>
    </div>

    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" class="login-panel">
        <label>Faculty username</label>
        <input type="text" name="faculty_user" placeholder="e.g. Engr. Torres" required>
        <label>Password</label>
        <input type="password" name="faculty_pass" placeholder="Demo password: faculty123" required>
        <button type="submit" class="primary-btn full">Sign in <span>→</span></button>
        <small class="muted">Faculty accounts process enrollment and manage courses, rooms, and schedules.</small>
    </form>
</div>
</body>
</html>
