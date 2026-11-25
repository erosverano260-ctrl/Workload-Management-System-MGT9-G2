<?php
$page = 'designations'; $title = 'Designations';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/approvals.php';
requireRole('admin');

$conn = (new database())->connect();

/* ---------- POST (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'save') {
            $name = post_str('name'); $hours = round((float)($_POST['hours'] ?? 0), 1); $credit = round((float)($_POST['credit'] ?? 0), 1);
            if ($name === '' || mb_strlen($name) > 100)   { flash_set('error', 'Enter a designation name (up to 100 characters).'); redirect('designations.php'); }
            if ($hours < 0 || $hours > FACULTY_WEEK_HOURS) { flash_set('error', 'Hours must be between 0 and ' . FACULTY_WEEK_HOURS . '.'); redirect('designations.php'); }
            if ($credit < 0 || $credit > 40)               { flash_set('error', 'Service credit must be between 0 and 40.'); redirect('designations.php'); }
            if (designation_is_chair_or_dean($name)) $credit = 0.0;           // Chair and Dean never earn service credit
            if ($action === 'add') {
                $st = $conn->prepare("INSERT INTO designations (name, hours, service_credit) VALUES (?, ?, ?)");
                $st->bind_param('sdd', $name, $hours, $credit); $st->execute();
                flash_set('success', 'Designation added.');
            } else {
                $id = (int)($_POST['designation_id'] ?? 0);
                $st = $conn->prepare("UPDATE designations SET name = ?, hours = ?, service_credit = ? WHERE designation_id = ?");
                $st->bind_param('sddi', $name, $hours, $credit, $id); $st->execute();
                flash_set('success', 'Designation updated' . (designation_is_chair_or_dean($name) ? ' (Chair / Dean earn no service credit).' : '.'));
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['designation_id'] ?? 0);
            $st = $conn->prepare("DELETE FROM designations WHERE designation_id = ?");
            $st->bind_param('i', $id); $st->execute();
            flash_set('success', 'Designation deleted (requests for it were removed too).');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'designation'));
    }
    redirect('designations.php');
}

$rows = $conn->query("SELECT designation_id, name, hours, service_credit FROM designations ORDER BY name")->fetch_all(MYSQLI_ASSOC);
require '../includes/header.php';
$flash = flash_get();
?>
<link rel="stylesheet" href="<?= asset('portal.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
<div class="section-heading"><div><span class="eyebrow">SECTION C</span><h2>Designations</h2></div></div>
<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<div class="ui-notice">
    <b>Hours</b> count inside the instructor's <?= FACULTY_WEEK_HOURS ?>-hour week. <b>Service credit</b> is extra pay and is <b>not</b> part of the <?= FACULTY_WEEK_HOURS ?> hours.
    Any designation with <b>Chair</b> or <b>Dean</b> in its name earns no service credit.
</div>

<form method="post" class="f-card ds-add">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h3>Add a designation</h3>
    <div class="ds-fields">
        <div class="f-field"><label for="n">Name</label><input id="n" type="text" name="name" maxlength="100" required placeholder="e.g. BSEE Adviser"></div>
        <div class="f-field"><label for="h">Hours / week</label><input id="h" type="number" name="hours" min="0" max="<?= FACULTY_WEEK_HOURS ?>" step="0.5" value="0"></div>
        <div class="f-field"><label for="c">Service credit</label><input id="c" type="number" name="credit" min="0" max="40" step="0.5" value="0"></div>
        <button type="submit" class="primary-btn">Add</button>
    </div>
</form>

<?php if (!$rows): ?><div class="empty">No designations yet.</div><?php endif; ?>
<?php foreach ($rows as $d): $none = designation_is_chair_or_dean($d['name']); ?>
<div class="ds-admin-row">
    <form method="post" class="ds-fields">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="designation_id" value="<?= (int)$d['designation_id'] ?>">
        <div class="f-field"><label>Name</label><input type="text" name="name" maxlength="100" required value="<?= h($d['name']) ?>"></div>
        <div class="f-field"><label>Hours / week</label><input type="number" name="hours" min="0" max="<?= FACULTY_WEEK_HOURS ?>" step="0.5" value="<?= h(nf($d['hours'])) ?>"></div>
        <div class="f-field"><label>Service credit<?= $none ? ' (none: Chair / Dean)' : '' ?></label><input type="number" name="credit" min="0" max="40" step="0.5" value="<?= $none ? 0 : h(nf($d['service_credit'])) ?>" <?= $none ? 'readonly' : '' ?>></div>
        <button type="submit" class="outline-btn">Save</button>
    </form>
    <form method="post" data-confirm="Delete <?= h($d['name']) ?>? Instructors' requests for it are removed too.">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="designation_id" value="<?= (int)$d['designation_id'] ?>">
        <button type="submit" class="danger-btn">Delete</button>
    </form>
</div>
<?php endforeach; ?>
</section>

<?php require '../includes/footer.php'; ?>
