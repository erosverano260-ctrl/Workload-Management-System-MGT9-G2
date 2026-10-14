<?php
require 'includes/auth.php';
require 'includes/ui.php';
require 'includes/db.php';

// Already signed in? Go straight to the dashboard.
if (currentRole() === 'faculty') redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['faculty_user'] ?? '');
    $pass = $_POST['faculty_pass'] ?? '';
    $name = null;

    // 1) Real accounts: faculty table, hashed passwords.
    try {
        $conn = (new database())->connect();
        $stmt = $conn->prepare("SELECT faculty_name, password FROM faculty WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $user);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && password_verify($pass, $row['password'])) $name = $row['faculty_name'];
    } catch (Throwable $e) { /* DB unavailable: fall through to the demo login */ }

    // 2) Demo-only fallback. Remove once real faculty accounts exist.
    if ($name === null && $user !== '' && $pass === 'faculty123') $name = $user;

    if ($name !== null) {
        session_regenerate_id(true);          // prevents session fixation
        $_SESSION['role'] = 'faculty';
        $_SESSION['faculty_name'] = $name;
        redirect('index.php');                // lands on the statistics dashboard
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
        <input type="text" name="faculty_user" placeholder="e.g. Engr. Torres" required value="<?= h($_POST['faculty_user'] ?? '') ?>">
        <label>Password</label>
        <input type="password" name="faculty_pass" placeholder="Demo password: faculty123" required>
        <button type="submit" class="primary-btn full">Sign in <span>→</span></button>
        <small class="muted">After signing in you'll land on the dashboard with course, room and schedule statistics.</small>
    </form>
</div>
</body>
</html>
