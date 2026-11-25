<?php
require 'includes/auth.php';
require 'includes/ui.php';
require 'includes/db.php';

if (currentRole()) redirect(appUrl(homeFor(currentRole())));

$sent  = isset($_GET['sent']);
$flash = flash_get();
$error = ($flash && $flash['type'] === 'error') ? $flash['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user = trim($_POST['username'] ?? '');
    if ($user === '') {
        $error = 'Enter your username.';
    } else {
        try {
            $conn = (new database())->connect();
            ensure_reset_table($conn);
            $st = $conn->prepare("SELECT faculty_id FROM faculty WHERE username = ? LIMIT 1");
            $st->bind_param('s', $user);
            $st->execute();
            if ($row = $st->get_result()->fetch_assoc()) {
                $id = (int)$row['faculty_id'];
                $st = $conn->prepare("SELECT 1 FROM password_requests WHERE faculty_id = ? AND handled = 0 LIMIT 1");
                $st->bind_param('i', $id);
                $st->execute();
                if (!$st->get_result()->fetch_row()) {           // one open request per instructor, so it can't be spammed
                    $st = $conn->prepare("INSERT INTO password_requests (faculty_id) VALUES (?)");
                    $st->bind_param('i', $id);
                    $st->execute();
                }
            }
        } catch (Throwable $e) { /* same answer either way: never reveal whether a username exists */ }
        redirect('forgot.php?sent=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password — EE Workload System</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/png" href="assets/ee-logo.png">
<link rel="stylesheet" href="assets/login.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-brand">
        <img src="assets/ee-logo.png" alt="logo">
        <div><strong>Forgot your password?</strong><small>Institute of Integrated Electrical Engineers</small></div>
    </div>

    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>

    <?php if ($sent): ?>
    <div class="login-panel">
        <div class="flash success" style="margin:0 0 12px">Request sent.</div>
        <p style="margin:0 0 10px;font-size:14px">If that username belongs to an instructor, the department admin has been notified and will give you a temporary password. You'll be asked to choose your own when you sign in.</p>
        <a href="login.php" class="primary-btn full" style="text-align:center;text-decoration:none">Back to sign in</a>
    </div>
    <?php else: ?>
    <form method="post" class="login-panel">
        <?= csrf_field() ?>
        <label for="u">Your username</label>
        <input id="u" type="text" name="username" required autofocus autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
        <button type="submit" class="primary-btn full">Ask the admin to reset it <span>→</span></button>
        <small class="muted">Instructors: the department admin will see your request and issue a temporary password.<br>
        Administrators: reset your password on the server computer at <a href="reset_admin.php" style="color:var(--amber-dark);font-weight:600">reset_admin.php</a>.</small>
        <a href="login.php" style="font-size:13px;font-weight:600;color:var(--amber-dark)">← Back to sign in</a>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
