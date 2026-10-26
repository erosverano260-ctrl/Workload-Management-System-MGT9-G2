<?php
require 'includes/auth.php';
require 'includes/ui.php';
require 'includes/db.php';

// Already signed in? Go to your own area.
if (currentRole()) redirect(appUrl(homeFor(currentRole())));

$error = '';
$noAdminYet = false;
$notice = isset($_GET['created']) ? 'Administrator account created. Sign in below.' : '';

try {
    $conn = (new database())->connect();
    $noAdminYet = (int)$conn->query("SELECT COUNT(*) AS n FROM admins")->fetch_assoc()['n'] === 0;
} catch (Throwable $e) {
    $error = 'The database is not ready. Import sql/workload_db.sql, then run the migrations in the README.';
    $conn = null;
}

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

    // Small brute-force brake: 5 misses -> 60 second pause (per browser session).
    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $error = 'Too many attempts. Wait a minute and try again.';
    } else {
        $ok = false;
        try {
            $stmt = $conn->prepare("SELECT admin_id, full_name, password FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && password_verify($pass, $row['password'])) {
                loginUser('admin', (int)$row['admin_id'], $row['full_name']);
                $ok = true;
            } else {
                $stmt = $conn->prepare("SELECT faculty_id, faculty_name, password, must_change_password FROM faculty WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $user);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row && password_verify($pass, $row['password'])) {
                    loginUser('faculty', (int)$row['faculty_id'], $row['faculty_name'], (bool)$row['must_change_password']);
                    $ok = true;
                }
            }
        } catch (Throwable $e) {
            $error = 'Sign-in is unavailable: run sql/roles_migration.sql first.';
        }
        if ($ok) { unset($_SESSION['fails'], $_SESSION['lock_until']); redirect(appUrl(homeFor(currentRole()))); }
        if ($error === '') {
            $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
            if ($_SESSION['fails'] >= 5) { $_SESSION['lock_until'] = time() + 60; $_SESSION['fails'] = 0; }
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — EE Workload System</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-brand">
        <img src="assets/ee-logo.svg" alt="logo">
        <div>
            <strong>Institute of Integrated Electrical Engineers</strong>
            <small>Workload Management System</small>
        </div>
    </div>

    <?php if ($notice): ?><div class="flash success"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" class="login-panel">
        <label for="u">Username</label>
        <input id="u" type="text" name="username" autocomplete="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
        <label for="p">Password</label>
        <input id="p" type="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="primary-btn full">Sign in <span>→</span></button>
        <small class="muted">Administrators and instructors sign in here. You'll be taken to the right place for your role.</small>
    </form>

    <?php if ($noAdminYet): ?>
    <p class="muted" style="margin-top:14px">First time here? <a href="setup.php" style="color:var(--amber-dark);font-weight:700">Create the administrator account</a></p>
    <?php endif; ?>
</div>
</body>
</html>
