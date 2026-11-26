<?php
$page = 'account'; $title = 'My Account';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require_once '../includes/admin_photo.php';
requireRole('admin');

$conn = (new database())->connect();
$id   = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'change_password';
    if ($action === 'upload_photo' || $action === 'remove_photo') {
        try {
            ensure_admin_photo_column($conn);
            $stmt = $conn->prepare("SELECT photo FROM admins WHERE admin_id=?");
            $stmt->bind_param('i', $id); $stmt->execute();
            $oldPhoto = $stmt->get_result()->fetch_assoc()['photo'] ?? null;
            if ($action === 'upload_photo') {
                try { $newPhoto = save_admin_photo($_FILES['photo'] ?? [], $id); }
                catch (RuntimeException $e) { flash_set('error', $e->getMessage()); redirect('account.php'); }
                flash_set('success', 'Profile photo updated.');
            } else { $newPhoto = null; flash_set('success', 'Profile photo removed.'); }
            $stmt = $conn->prepare("UPDATE admins SET photo=? WHERE admin_id=?");
            $stmt->bind_param('si', $newPhoto, $id); $stmt->execute();
            delete_admin_photo_file($oldPhoto);
        } catch (mysqli_sql_exception $e) {
            flash_set('error', 'Could not save your photo. Please try again.');
        }
        redirect('account.php');
    }
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

ensure_admin_photo_column($conn);
$stmt = $conn->prepare("SELECT photo FROM admins WHERE admin_id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$photoUrl = admin_photo_url($stmt->get_result()->fetch_assoc()['photo'] ?? '');
$initial  = strtoupper(mb_substr(currentUserName() ?: 'A', 0, 1));

require '../includes/header.php';
$flash = flash_get();
?>
<link rel="stylesheet" href="../assets/portal.css">
<script src="../assets/ui.js"></script>

<section class="page-section account-page">
<div class="section-heading">
    <div><span class="eyebrow">ADMINISTRATOR</span><h2>My account</h2></div>
    <span class="date-chip"><?= h(currentUserName()) ?></span>
</div>
<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="f-card photo-card" style="margin-bottom:18px">
    <div class="photo-preview" id="photoPreview">
        <?php if ($photoUrl): ?><img src="<?= h($photoUrl) ?>" alt="Your profile photo"><?php else: ?><span><?= h($initial) ?></span><?php endif; ?>
    </div>
    <div class="photo-body">
        <h3>Profile photo</h3>
        <p class="f-sub">JPG, PNG or WEBP, up to 2 MB. It is cropped to a square and shown in the sidebar.</p>
        <div class="photo-row">
            <form method="post" enctype="multipart/form-data" class="photo-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_photo">
                <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" required>
                <button type="submit" class="primary-btn">Upload photo</button>
            </form>
            <?php if ($photoUrl): ?>
            <form method="post" class="photo-actions" onsubmit="return confirm('Remove your profile photo?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_photo">
                <button type="submit" class="outline-btn photo-remove">Remove photo</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.getElementById('photoInput').addEventListener('change', function () {
    const f = this.files[0], box = document.getElementById('photoPreview');
    if (!f) return;
    if (f.size > 2 * 1024 * 1024) { alert('The photo is too large. Maximum is 2 MB.'); this.value = ''; return; }
    box.innerHTML = ''; const im = new Image(); im.src = URL.createObjectURL(f); box.appendChild(im);
});
</script>

<form method="post" class="f-card f-form account-wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div><h3>Change password</h3><p class="f-sub">At least 8 characters.</p></div>
    <div class="f-field"><label for="cur">Current password</label><input type="password" id="cur" name="current" required autocomplete="current-password"></div>
    <div class="f-field"><label for="new">New password</label><input type="password" id="new" name="new" required minlength="8" autocomplete="new-password"></div>
    <div class="f-field"><label for="conf">Confirm new password</label><input type="password" id="conf" name="confirm" required minlength="8" autocomplete="new-password"></div>
    <div class="f-actions"><button type="submit" class="primary-btn">Change password</button></div>
</form>
</section>

<?php require '../includes/footer.php'; ?>
