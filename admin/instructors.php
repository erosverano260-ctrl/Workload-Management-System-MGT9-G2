<?php
$page='instructors';$title='Instructor Availability';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

$db = new database();
$conn = $db->connect();

const DAY_KEYS = ['Mon','Tue','Wed','Thu','Fri'];

/** ['Mon','Tue','Wed','Fri'] -> "Mon–Wed, Fri" */
function fmtDays(?string $csv): string {
    $picked = array_values(array_intersect(DAY_KEYS, array_filter(explode(',', (string)$csv))));
    if (!$picked) return 'Not set';
    $idx = array_map(fn($d) => array_search($d, DAY_KEYS), $picked);
    $runs = []; $start = $prev = $idx[0];
    foreach (array_slice($idx, 1) as $i) {
        if ($i === $prev + 1) { $prev = $i; continue; }
        $runs[] = [$start, $prev]; $start = $prev = $i;
    }
    $runs[] = [$start, $prev];
    return implode(', ', array_map(function ($r) {
        [$a, $b] = $r;
        if ($a === $b) return DAY_KEYS[$a];
        return $b - $a === 1 ? DAY_KEYS[$a] . ', ' . DAY_KEYS[$b] : DAY_KEYS[$a] . '–' . DAY_KEYS[$b];
    }, $runs));
}

function fmtTime(?string $t): string {
    return $t ? date('g:i A', strtotime($t)) : '';
}

function fmtAvailability(array $i): string {
    $days = fmtDays($i['available_days'] ?? '');
    if (!$i['available_from'] || !$i['available_to']) return $days;
    return $days . ', ' . fmtTime($i['available_from']) . '–' . fmtTime($i['available_to']);
}

function initialOf(string $name): string {
    $clean = preg_replace('/^(engr|dr|prof|mr|ms|mrs)\.?\s+/i', '', trim($name));
    return strtoupper(mb_substr($clean, 0, 1)) ?: '?';
}

function uniqueUsername(mysqli $c, string $name): string {
    $base = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name)), '.') ?: 'instructor';
    $u = $base; $n = 1;
    $stmt = $c->prepare("SELECT 1 FROM faculty WHERE username=?");
    while (true) {
        $stmt->bind_param('s', $u);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_row()) return $u;
        $u = $base . (++$n);
    }
}

/** Pull + validate the availability inputs. Returns [days_csv, from|null, to|null] or an error string. */
function readAvailability() {
    $days = array_values(array_intersect(DAY_KEYS, (array)($_POST['days'] ?? [])));
    $from = post_str('available_from'); $to = post_str('available_to');
    if (($from === '') !== ($to === ''))     return 'Set both a start and an end time, or leave both empty.';
    if ($from !== '' && $from >= $to)        return 'The end time must be after the start time.';
    return [implode(',', $days) ?: null, $from !== '' ? $from . ':00' : null, $to !== '' ? $to . ':00' : null];
}

/* ---------- POST actions (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_instructor' || $action === 'edit_instructor') {
            $name = post_str('faculty_name'); $spec = post_str('specialty');
            $av = readAvailability();
            if ($name === '' || strlen($name) > 100) { flash_set('error', 'Enter the instructor\'s name (up to 100 characters).'); redirect('instructors.php'); }
            if (is_string($av))                      { flash_set('error', $av); redirect('instructors.php'); }
            [$days, $from, $to] = $av;
            $spec = $spec !== '' ? $spec : null;

            if ($action === 'add_instructor') {
                $user = uniqueUsername($conn, $name);
                $role = 'instructor';
                $temp = temp_password();                       // shown once; instructor must change it at first sign-in
                $pass = password_hash($temp, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO faculty (username, faculty_name, specialty, available_days, available_from, available_to, password, must_change_password, role) VALUES (?,?,?,?,?,?,?,1,?)");
                $stmt->bind_param('ssssssss', $user, $name, $spec, $days, $from, $to, $pass, $role);
                $stmt->execute();
                flash_set('success', "Instructor added. Faculty portal login → username: $user · temporary password: $temp (shown once — give it to the instructor now).");
            } else {
                $stmt = $conn->prepare("UPDATE faculty SET faculty_name=?, specialty=?, available_days=?, available_from=?, available_to=? WHERE faculty_id=?");
                $stmt->bind_param('sssssi', $name, $spec, $days, $from, $to, $_POST['faculty_id']);
                $stmt->execute();
                flash_set('success', 'Instructor updated.');
            }
        } elseif ($action === 'reset_password') {
            $id = (int)($_POST['faculty_id'] ?? 0);
            $temp = temp_password();
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE faculty SET password=?, must_change_password=1 WHERE faculty_id=?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
            $stmt = $conn->prepare("SELECT username FROM faculty WHERE faculty_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc()['username'] ?? '?';
            flash_set('success', "Password reset. Username: $u · temporary password: $temp (shown once — give it to the instructor now).");
        } elseif ($action === 'delete_instructor') {
            $id = (int)($_POST['faculty_id'] ?? 0);
            // keep the classes; just un-assign them
            $stmt = $conn->prepare("UPDATE schedules SET faculty_id=NULL WHERE faculty_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            flash_set('success', 'Instructor removed. Their classes are now unassigned.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'instructor'));
    }
    redirect('instructors.php');
}

require '../includes/header.php';
$flash = flash_get();

/* ---------- Load instructors (needs instructors_migration.sql) ---------- */
$needsMigration = false;
$instructors = [];
try {
    $instructors = $conn->query(
        "SELECT f.faculty_id, f.username, f.faculty_name, f.specialty, f.available_days, f.available_from, f.available_to,
                COUNT(s.schedule_id) AS classes
         FROM faculty f
         LEFT JOIN schedules s ON s.faculty_id = f.faculty_id
         GROUP BY f.faculty_id
         ORDER BY f.faculty_name"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    $needsMigration = true;
}

$editing = null;
if (!$needsMigration && isset($_GET['edit'])) {
    foreach ($instructors as $i) if ((int)$i['faculty_id'] === (int)$_GET['edit']) $editing = $i;
}
$editDays = $editing ? array_filter(explode(',', (string)$editing['available_days'])) : [];
?>
<link rel="stylesheet" href="<?= asset('ui.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">FACULTY AVAILABILITY</span><h2>Instructors available to conduct classes</h2></div>
    <?php if (!$needsMigration): ?><button class="primary-btn" id="openAddInstructor" type="button">+ Add Instructor</button><?php endif; ?>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<?php if ($needsMigration): ?>
<div class="ui-notice">The instructor columns are missing. Run <code>instructors_migration.sql</code> once in phpMyAdmin, then reload this page.</div>
<?php endif; ?>

<div class="instructor-grid">
<?php foreach ($instructors as $i): ?>
<div class="instructor-card">
    <div class="faculty-avatar"><?= h(initialOf($i['faculty_name'])) ?></div>
    <div class="faculty-info">
        <h3><?= h($i['faculty_name']) ?></h3>
        <p><?= h($i['specialty'] ?: 'No specialty set') ?></p>
        <div class="faculty-row"><span>Available</span><b><?= h(fmtAvailability($i)) ?></b></div>
        <div class="faculty-row"><span>Assigned classes</span><b><?= (int)$i['classes'] ?></b></div>
        <div class="faculty-row"><span>Portal username</span><b><?= h($i['username']) ?></b></div>
        <div class="card-actions">
            <div class="btn-pair">
                <a href="?edit=<?= (int)$i['faculty_id'] ?>" class="outline-btn js-edit-instructor"
                   data-id="<?= (int)$i['faculty_id'] ?>" data-name="<?= h($i['faculty_name']) ?>" data-specialty="<?= h($i['specialty']) ?>"
                   data-days="<?= h($i['available_days']) ?>" data-from="<?= h(substr((string)$i['available_from'], 0, 5)) ?>" data-to="<?= h(substr((string)$i['available_to'], 0, 5)) ?>">Edit</a>
                <form method="post" data-confirm="Issue a new temporary password for <?= h($i['faculty_name']) ?>? Their current password stops working." data-confirm-ok="Reset">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="faculty_id" value="<?= (int)$i['faculty_id'] ?>">
                    <button type="submit" class="outline-btn">Reset password</button>
                </form>
                <form method="post" data-confirm="Remove <?= h($i['faculty_name']) ?>? Their <?= (int)$i['classes'] ?> class(es) will become unassigned.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_instructor">
                    <input type="hidden" name="faculty_id" value="<?= (int)$i['faculty_id'] ?>">
                    <button type="submit" class="outline-btn danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <span class="online-dot">● Available</span>
</div>
<?php endforeach; ?>
</div>
<?php if (!$instructors && !$needsMigration): ?><div class="empty">No instructors yet. Use “+ Add Instructor” to create the first one.</div><?php endif; ?>
</section>

<?php if (!$needsMigration): ?>
<!-- ADD / EDIT INSTRUCTOR MODAL -->
<div class="ui-modal" id="instructorModal" role="dialog" aria-modal="true" aria-labelledby="instModalTitle" aria-hidden="true" <?= $editing ? 'data-autoopen' : '' ?>>
  <div class="ui-modal-card">
    <header class="ui-modal-head">
      <span class="ui-modal-icon">♙</span>
      <div>
        <h2 id="instModalTitle"><?= $editing ? 'Edit instructor' : 'Add instructor' ?></h2>
        <p id="instModalSub"><?= $editing ? 'Update teaching details and availability.' : 'Set when this instructor can teach. A portal login is created automatically.' ?></p>
      </div>
      <button type="button" class="ui-modal-x" data-close-modal aria-label="Close">×</button>
    </header>

    <form method="post" class="ui-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="ifAction" value="<?= $editing ? 'edit_instructor' : 'add_instructor' ?>">
      <input type="hidden" name="faculty_id" id="ifId" value="<?= h($editing['faculty_id'] ?? '') ?>">

      <div class="ui-grid">
        <div class="ui-field span2">
          <label class="ui-label" for="ifName">Full name</label>
          <input type="text" id="ifName" name="faculty_name" required maxlength="100" value="<?= h($editing['faculty_name'] ?? '') ?>" placeholder="e.g. Engr. Ramon Torres">
        </div>
        <div class="ui-field span2">
          <label class="ui-label" for="ifSpec">Specialty</label>
          <input type="text" id="ifSpec" name="specialty" maxlength="100" value="<?= h($editing['specialty'] ?? '') ?>" placeholder="e.g. Power Systems">
        </div>
        <div class="ui-field span2">
          <span class="ui-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--ui-muted)">Available days</span>
          <div class="ui-chips" id="ifDays">
            <?php foreach (DAY_KEYS as $d): ?>
            <input type="checkbox" name="days[]" id="day<?= $d ?>" value="<?= $d ?>" <?= in_array($d, $editDays, true) ? 'checked' : '' ?>><label for="day<?= $d ?>"><?= $d ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="ui-field">
          <label class="ui-label" for="ifFrom">From</label>
          <input type="time" id="ifFrom" name="available_from" value="<?= h(substr((string)($editing['available_from'] ?? ''), 0, 5)) ?>">
        </div>
        <div class="ui-field">
          <label class="ui-label" for="ifTo">To</label>
          <input type="time" id="ifTo" name="available_to" value="<?= h(substr((string)($editing['available_to'] ?? ''), 0, 5)) ?>">
        </div>
      </div>

      <div class="ui-actions">
        <button type="button" class="ui-btn" data-close-modal>Cancel</button>
        <button type="submit" class="ui-btn primary" id="ifSubmit"><?= $editing ? 'Save changes' : 'Add instructor' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const modal = document.getElementById('instructorModal');
    const $ = id => document.getElementById(id);

    function setMode(edit, d) {
        d = d || {};
        $('instModalTitle').textContent = edit ? 'Edit instructor' : 'Add instructor';
        $('instModalSub').textContent   = edit ? 'Update teaching details and availability.' : 'Set when this instructor can teach.';
        $('ifSubmit').textContent       = edit ? 'Save changes' : 'Add instructor';
        $('ifAction').value = edit ? 'edit_instructor' : 'add_instructor';
        $('ifId').value     = edit ? d.id : '';
        $('ifName').value   = edit ? d.name : '';
        $('ifSpec').value   = edit ? d.specialty : '';
        $('ifFrom').value   = edit ? d.from : '';
        $('ifTo').value     = edit ? d.to : '';
        const picked = edit ? (d.days || '').split(',') : [];
        document.querySelectorAll('#ifDays input').forEach(cb => cb.checked = picked.includes(cb.value));
    }

    $('openAddInstructor').addEventListener('click', () => { setMode(false); UI.open(modal); });
    document.querySelectorAll('.js-edit-instructor').forEach(a => {
        a.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); setMode(true, a.dataset); UI.open(modal); });
    });
})();
</script>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
