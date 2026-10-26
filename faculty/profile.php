<?php
$page = 'profile'; $title = 'My Profile';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/schedule_helpers.php';
requireRole('faculty');

$conn = (new database())->connect();
$id   = currentUserId();

/* ---------- POST (post → redirect → get). An instructor can only ever change THEIR OWN row. ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_profile') {
            $spec = post_str('specialty');
            $days = array_values(array_intersect(FAC_DAY_KEYS, (array)($_POST['days'] ?? [])));
            $from = post_str('available_from'); $to = post_str('available_to');
            if (strlen($spec) > 100)                 { flash_set('error', 'Specialty can be at most 100 characters.'); redirect('profile.php'); }
            if (($from === '') !== ($to === ''))     { flash_set('error', 'Set both a start and an end time, or leave both empty.'); redirect('profile.php'); }
            if ($from !== '' && $from >= $to)        { flash_set('error', 'The end time must be after the start time.'); redirect('profile.php'); }
            $specV = $spec !== '' ? $spec : null;
            $daysV = implode(',', $days) ?: null;
            $fromV = $from !== '' ? $from . ':00' : null;
            $toV   = $to   !== '' ? $to   . ':00' : null;
            $stmt = $conn->prepare("UPDATE faculty SET specialty=?, available_days=?, available_from=?, available_to=? WHERE faculty_id=?");
            $stmt->bind_param('ssssi', $specV, $daysV, $fromV, $toV, $id);
            $stmt->execute();
            flash_set('success', 'Availability saved.');
        } elseif ($action === 'change_password') {
            $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
            $stmt = $conn->prepare("SELECT password FROM faculty WHERE faculty_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
            if (!password_verify($cur, $hash))       flash_set('error', 'Your current password is incorrect.');
            elseif (strlen($new) < 8)                flash_set('error', 'The new password must be at least 8 characters.');
            elseif ($new === $cur)                   flash_set('error', 'Choose a password different from the current one.');
            elseif ($new !== $conf)                  flash_set('error', 'The two new passwords do not match.');
            else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE faculty SET password=?, must_change_password=0 WHERE faculty_id=?");
                $stmt->bind_param('si', $newHash, $id);
                $stmt->execute();
                $_SESSION['must_change'] = false;
                session_regenerate_id(true);
                flash_set('success', 'Password changed.');
            }
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', 'Could not save your changes. Please try again.');
    }
    redirect('profile.php');
}

$inst  = loadInstructor($conn, $id);
$mustChange = !empty($_SESSION['must_change']);
require '../includes/faculty_header.php';
$flash = flash_get();
$picked = array_filter(explode(',', (string)$inst['available_days']));
?>
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">MY ACCOUNT</span><h2><?= h($inst['faculty_name']) ?></h2></div>
    <span class="date-chip">Username: <?= h($inst['username']) ?></span>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<?php if ($mustChange): ?>
<div class="ui-notice"><strong>Choose your own password to continue.</strong> You signed in with a temporary password from the department admin. The rest of the portal unlocks after you change it.</div>
<?php endif; ?>

<div class="profile-grid">
  <?php if (!$mustChange): ?>
  <form method="post" class="f-card f-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_profile">
    <div><h3>Teaching availability</h3><p class="f-sub">Tell the scheduling office when you can teach. Your name and class assignments are managed by the department admin.</p></div>

    <div class="f-field"><label for="spec">Specialty</label>
      <input type="text" id="spec" name="specialty" maxlength="100" value="<?= h($inst['specialty'] ?? '') ?>" placeholder="e.g. Power Systems"></div>

    <div class="f-field"><span class="f-label">Available days</span>
      <div class="ui-chips">
        <?php foreach (FAC_DAY_KEYS as $d): ?>
        <input type="checkbox" name="days[]" id="d<?= $d ?>" value="<?= $d ?>" <?= in_array($d, $picked, true) ? 'checked' : '' ?>><label for="d<?= $d ?>"><?= $d ?></label>
        <?php endforeach; ?>
      </div></div>

    <div class="f-grid2">
      <div class="f-field"><label for="from">From</label><input type="time" id="from" name="available_from" value="<?= h(substr((string)$inst['available_from'], 0, 5)) ?>"></div>
      <div class="f-field"><label for="to">To</label><input type="time" id="to" name="available_to" value="<?= h(substr((string)$inst['available_to'], 0, 5)) ?>"></div>
    </div>
    <div class="f-actions"><button type="submit" class="primary-btn">Save availability</button></div>
  </form>
  <?php endif; ?>

  <form method="post" class="f-card f-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div><h3>Change password</h3><p class="f-sub">At least 8 characters.</p></div>
    <div class="f-field"><label for="cur">Current password<?= $mustChange ? ' (the temporary one)' : '' ?></label><input type="password" id="cur" name="current" required autocomplete="current-password"></div>
    <div class="f-field"><label for="new">New password</label><input type="password" id="new" name="new" required minlength="8" autocomplete="new-password"></div>
    <div class="f-field"><label for="conf">Confirm new password</label><input type="password" id="conf" name="confirm" required minlength="8" autocomplete="new-password"></div>
    <div class="f-actions"><button type="submit" class="primary-btn">Change password</button></div>
  </form>
</div>
</section>

<?php require '../includes/footer.php'; ?>
