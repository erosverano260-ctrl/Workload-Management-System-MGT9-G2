<?php
$page = 'account'; $title = 'My Account';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

$conn = (new database())->connect();
$id   = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    if (!password_verify($cur, $hash))  flash_set('error', 'Your current password is incorrect.');
    elseif (strlen($new) < 8)           flash_set('error', 'The new password must be at least 8 characters.');
    elseif ($new === $cur)              flash_set('error', 'Choose a password different from the current one.');
    elseif ($new !== $conf)             flash_set('error', 'The two new passwords do not match.');
    else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password=? WHERE admin_id=?");
        $stmt->bind_param('si', $newHash, $id);
        $stmt->execute();
        session_regenerate_id(true);
        flash_set('success', 'Password changed.');
    }
    redirect('account.php');
}

require '../includes/header.php';
$flash = flash_get();
?>
<link rel="stylesheet" href="../assets/portal.css">
<script src="../assets/ui.js"></script>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">ADMINISTRATOR</span><h2>My account</h2></div>
    <span class="date-chip"><?= h(currentUserName()) ?></span>
</div>
<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<form method="post" class="f-card f-form account-wrap">
    <?= csrf_field() ?>
    <div><h3>Change password</h3><p class="f-sub">At least 8 characters.</p></div>
    <div class="f-field"><label for="cur">Current password</label><input type="password" id="cur" name="current" required autocomplete="current-password"></div>
    <div class="f-field"><label for="new">New password</label><input type="password" id="new" name="new" required minlength="8" autocomplete="new-password"></div>
    <div class="f-field"><label for="conf">Confirm new password</label><input type="password" id="conf" name="confirm" required minlength="8" autocomplete="new-password"></div>
    <div class="f-actions"><button type="submit" class="primary-btn">Change password</button></div>
</form>
</section>

<?php require '../includes/footer.php'; ?>
