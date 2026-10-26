<?php
/**
 * One-time setup: creates the FIRST administrator. Locks itself as soon as any admin exists.
 */
require 'includes/auth.php';
require 'includes/ui.php';
require 'includes/db.php';

try {
    $conn = (new database())->connect();
    $count = (int)$conn->query("SELECT COUNT(*) AS n FROM admins")->fetch_assoc()['n'];
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database not ready. Import sql/workload_db.sql and run sql/roles_migration.sql first.');
}
if ($count > 0) { redirect('login.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post_str('full_name'); $user = strtolower(post_str('username'));
    $pass = (string)($_POST['password'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
    if ($name === '' || strlen($name) > 100)                  $error = 'Enter a name (up to 100 characters).';
    elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $user))      $error = 'Username: 3–50 letters, numbers, dots, dashes or underscores.';
    elseif (strlen($pass) < 8)                                $error = 'Password must be at least 8 characters.';
    elseif ($pass !== $conf)                                  $error = 'The two passwords do not match.';
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (username, full_name, password) VALUES (?,?,?)");
        $stmt->bind_param('sss', $user, $name, $hash);
        $stmt->execute();
        redirect('login.php?created=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>First-time setup — EE Workload System</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-brand">
        <img src="assets/ee-logo.svg" alt="logo">
        <div><strong>Create the administrator</strong><small>This page disappears once an admin exists</small></div>
    </div>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="login-panel">
        <?= csrf_field() ?>
        <label for="n">Full name</label>
        <input id="n" type="text" name="full_name" required maxlength="100" value="<?= h($_POST['full_name'] ?? '') ?>">
        <label for="u">Username</label>
        <input id="u" type="text" name="username" required maxlength="50" autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
        <label for="p">Password (min. 8 characters)</label>
        <input id="p" type="password" name="password" required minlength="8" autocomplete="new-password">
        <label for="c">Confirm password</label>
        <input id="c" type="password" name="confirm" required minlength="8" autocomplete="new-password">
        <button type="submit" class="primary-btn full">Create administrator <span>→</span></button>
    </form>
</div>
</body>
</html>
