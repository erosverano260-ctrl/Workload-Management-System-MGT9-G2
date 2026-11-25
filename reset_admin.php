<?php
/**
 * Administrator password recovery. Deliberately hard to reach from outside:
 *  1) the request must come from the server computer itself (localhost), AND
 *  2) a file named includes/allow_admin_reset.flag must exist. It is deleted again after a successful reset.
 */
require 'includes/auth.php';
require 'includes/ui.php';
require 'includes/db.php';

$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$flag  = __DIR__ . '/includes/allow_admin_reset.flag';
$ready = $local && is_file($flag);
$error = '';

function reset_page(string $body): void { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administrator password reset</title><link rel="stylesheet" href="assets/style.css"><link rel="icon" type="image/png" href="assets/ee-logo.png"><link rel="stylesheet" href="assets/login.css"></head>
<body class="login-body"><div class="login-wrap">
<div class="login-brand"><img src="assets/ee-logo.png" alt="logo"><div><strong>Administrator password reset</strong><small>Server computer only</small></div></div>
<?= $body ?>
</div></body></html>
<?php }

if (!$ready) {
    http_response_code(403);
    ob_start(); ?>
    <div class="login-panel">
        <p style="margin:0 0 8px;font-weight:700">This page is locked.</p>
        <?php if (!$local): ?>
        <p style="margin:0;font-size:14px">Open it on the computer that runs the system, at <code>http://localhost/…/reset_admin.php</code>. It can't be used from other devices.</p>
        <?php else: ?>
        <p style="margin:0 0 6px;font-size:14px">To unlock it:</p>
        <ol style="margin:0 0 10px;padding-left:20px;font-size:14px;line-height:1.6">
            <li>Open the project folder, then the <code>includes</code> folder.</li>
            <li>Create an empty file named <code>allow_admin_reset.flag</code>.</li>
            <li>Reload this page.</li>
        </ol>
        <p style="margin:0;font-size:13px;color:var(--muted)">The file deletes itself after the password is changed.</p>
        <?php endif; ?>
        <a href="login.php" style="display:inline-block;margin-top:12px;font-size:13px;font-weight:600;color:var(--amber-dark)">← Back to sign in</a>
    </div>
    <?php reset_page(ob_get_clean()); exit;
}

try { $conn = (new database())->connect(); $admins = $conn->query("SELECT admin_id, username, full_name FROM admins ORDER BY username")->fetch_all(MYSQLI_ASSOC); }
catch (Throwable $e) { http_response_code(500); exit('Database not ready.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['admin_id'] ?? 0); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
    if (strlen($new) < 8)      $error = 'The new password must be at least 8 characters.';
    elseif ($new !== $conf)    $error = 'The two passwords do not match.';
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
        $st->bind_param('si', $hash, $id);
        $st->execute();
        if ($st->affected_rows >= 0) { @unlink($flag); redirect('login.php?reset=1'); }
    }
}
ob_start(); ?>
<?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="login-panel">
    <?= csrf_field() ?>
    <label for="a">Administrator</label>
    <select id="a" name="admin_id" style="width:100%;height:46px;margin-bottom:10px;border:1.5px solid var(--border);border-radius:10px;padding:0 10px;font:inherit">
        <?php foreach ($admins as $a): ?><option value="<?= (int)$a['admin_id'] ?>"><?= h($a['username'] . ' — ' . $a['full_name']) ?></option><?php endforeach; ?>
    </select>
    <label for="n">New password (min. 8 characters)</label>
    <input id="n" type="password" name="new" required minlength="8" autocomplete="new-password">
    <label for="c">Confirm new password</label>
    <input id="c" type="password" name="confirm" required minlength="8" autocomplete="new-password">
    <button type="submit" class="primary-btn full">Set new password <span>→</span></button>
</form>
<?php reset_page(ob_get_clean());
