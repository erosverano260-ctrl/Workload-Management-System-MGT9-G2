<?php
$page='courses';$title='Course Catalog';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

$db = new database();
$conn = $db->connect();

$year   = (int)($_GET['year'] ?? 0);
$search = trim($_GET['search'] ?? '');
$back   = 'courses.php' . ($year ? '?year=' . $year : '');

/* ---------- Validate the add/edit form ---------- */
function validateCourse(): ?string {
    $code = post_str('course_code'); $name = post_str('course_name');
    $yr = (int)($_POST['year_level'] ?? 0); $units = (int)($_POST['units'] ?? 0);
    if ($code === '' || $name === '')   return 'Course code and name are required.';
    if (strlen($code) > 10)             return 'Course code can be at most 10 characters.';
    if (strlen($name) > 100)            return 'Course name can be at most 100 characters.';
    if ($yr < 1 || $yr > 4)             return 'Pick a year level from 1 to 4.';
    if ($units < 1 || $units > 12)      return 'Units must be between 1 and 12.';
    return null;
}

/* ---------- POST actions (post → redirect → get, so a refresh never re-submits) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_course') {
            if ($err = validateCourse()) { flash_set('error', $err); redirect($back); }
            [$code, $cname, $yr, $units] = [post_str('course_code'), post_str('course_name'), (int)$_POST['year_level'], (int)$_POST['units']]; // trimmed, same values that were validated
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, year_level, units, status) VALUES (?, ?, ?, ?, 'Open')");
            $stmt->bind_param("ssii", $code, $cname, $yr, $units);
            $stmt->execute();
            flash_set('success', 'Course added.');
        } elseif ($action === 'edit_course') {
            if ($err = validateCourse()) { flash_set('error', $err); redirect($back); }
            $status = ($_POST['status'] ?? '') === 'Closed' ? 'Closed' : 'Open';
            [$code, $cname, $yr, $units, $cid] = [post_str('course_code'), post_str('course_name'), (int)$_POST['year_level'], (int)$_POST['units'], (int)($_POST['course_id'] ?? 0)];
            $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, year_level=?, units=?, status=? WHERE course_id=?");
            $stmt->bind_param("ssiisi", $code, $cname, $yr, $units, $status, $cid);
            $stmt->execute();
            flash_set('success', 'Course updated.');
        } elseif ($action === 'delete_course') {
            $stmt = $conn->prepare("DELETE FROM courses WHERE course_id=?");
            $stmt->bind_param("i", $_POST['course_id']);
            $stmt->execute();
            flash_set('success', 'Course deleted.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'course'));
    }
    redirect($back);
}

require '../includes/header.php';
$flash = flash_get();

/* ---------- Server-side edit (?edit=ID) still works; JS opens the modal instantly instead ---------- */
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

$sql = "SELECT * FROM courses
        WHERE (? = 0 OR year_level = ?)
          AND (? = '' OR course_code LIKE CONCAT('%',?,'%') OR course_name LIKE CONCAT('%',?,'%'))
        ORDER BY year_level, course_code";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisss", $year, $year, $search, $search, $search);
$stmt->execute();
$filtered = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<link rel="stylesheet" href="<?= asset('ui.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
<div class="section-heading">
  <div><span class="eyebrow">COURSE CATALOG</span><h2>Manage courses</h2></div>
  <form class="search" onsubmit="return false;"><span>⌕</span><input id="courseSearch" name="search" value="<?= h($search) ?>" placeholder="Search course or name..."></form>
  <button class="primary-btn" id="openAddCourse" type="button">+ Add Course</button>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="year-tabs">
  <a class="<?= !$year?'selected':'' ?>" href="courses.php">All Years</a>
  <?php for($y=1;$y<=4;$y++): ?>
  <a class="<?= $year===$y?'selected':'' ?>" href="?year=<?=$y?>"><?=$y?><?= $y===1?'st':($y===2?'nd':($y===3?'rd':'th')) ?> Year</a>
  <?php endfor;?>
</div>

<div class="course-grid" id="courseGrid">
<?php foreach($filtered as $c): ?>
<article class="course-card searchable">
  <div class="course-top">
    <span class="code"><?=h($c['course_code'])?></span>
    <span class="status <?= h(strtolower($c['status'])) ?>"><?=h($c['status'])?></span>
  </div>
  <h3><?=h($c['course_name'])?></h3>
  <div class="course-bottom">
    <span><b><?=h($c['units'])?></b> units · Year <?=h($c['year_level'])?></span>
    <div class="btn-pair">
      <a href="?edit=<?= (int)$c['course_id'] ?>" class="outline-btn js-edit-course"
         data-id="<?= (int)$c['course_id'] ?>" data-code="<?= h($c['course_code']) ?>" data-name="<?= h($c['course_name']) ?>"
         data-year="<?= h($c['year_level']) ?>" data-units="<?= h($c['units']) ?>" data-status="<?= h($c['status']) ?>">Edit</a>
      <form method="post" data-confirm="Delete <?= h($c['course_code']) ?>? This can't be undone.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_course">
        <input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>">
        <button type="submit" class="outline-btn danger">Delete</button>
      </form>
    </div>
  </div>
</article>
<?php endforeach;?>
</div>
<?php if(!$filtered):?><div class="empty">No courses found.</div><?php endif;?>
</section>

<!-- ADD / EDIT COURSE MODAL -->
<div class="ui-modal" id="courseModal" role="dialog" aria-modal="true" aria-labelledby="courseModalTitle" aria-hidden="true" <?= $editing ? 'data-autoopen' : '' ?>>
  <div class="ui-modal-card">
    <header class="ui-modal-head">
      <span class="ui-modal-icon">▦</span>
      <div>
        <h2 id="courseModalTitle"><?= $editing ? 'Edit course' : 'Add course' ?></h2>
        <p id="courseModalSub"><?= $editing ? 'Update the details below, then save.' : 'New courses start as Open.' ?></p>
      </div>
      <button type="button" class="ui-modal-x" data-close-modal aria-label="Close">×</button>
    </header>

    <form method="post" class="ui-form" id="courseForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="cfAction" value="<?= $editing ? 'edit_course' : 'add_course' ?>">
      <input type="hidden" name="course_id" id="cfId" value="<?= h($editing['course_id'] ?? '') ?>">

      <div class="ui-grid">
        <div class="ui-field">
          <label class="ui-label" for="cfCode">Course code</label>
          <input type="text" id="cfCode" name="course_code" required maxlength="10" value="<?= h($editing['course_code'] ?? '') ?>" placeholder="e.g. EE301">
        </div>
        <div class="ui-field">
          <label class="ui-label" for="cfUnits">Units</label>
          <input type="number" id="cfUnits" name="units" required min="1" max="12" value="<?= h($editing['units'] ?? 3) ?>">
        </div>
        <div class="ui-field span2">
          <label class="ui-label" for="cfName">Course name</label>
          <input type="text" id="cfName" name="course_name" required maxlength="100" value="<?= h($editing['course_name'] ?? '') ?>" placeholder="e.g. Power Systems 1">
        </div>
        <div class="ui-field">
          <label class="ui-label" for="cfYear">Year level</label>
          <select id="cfYear" name="year_level" required>
            <option value="">Select</option>
            <?php for($y=1;$y<=4;$y++): ?>
            <option value="<?=$y?>" <?= (string)($editing['year_level'] ?? '')===(string)$y?'selected':'' ?>><?=$y?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="ui-field" id="cfStatusWrap" <?= $editing ? '' : 'hidden' ?>>
          <span class="ui-label" style="display:block;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--ui-muted)">Status</span>
          <div class="ui-seg">
            <input type="radio" name="status" id="cfOpen" value="Open" <?= ($editing['status'] ?? 'Open')==='Open'?'checked':'' ?>><label for="cfOpen">Open</label>
            <input type="radio" name="status" id="cfClosed" value="Closed" <?= ($editing['status'] ?? '')==='Closed'?'checked':'' ?>><label for="cfClosed">Closed</label>
          </div>
        </div>
      </div>

      <div class="ui-actions">
        <button type="button" class="ui-btn" data-close-modal>Cancel</button>
        <button type="submit" class="ui-btn primary" id="cfSubmit"><?= $editing ? 'Save changes' : 'Add course' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const modal = document.getElementById('courseModal');
    const $ = id => document.getElementById(id);

    function setMode(edit, d) {
        d = d || {};
        $('courseModalTitle').textContent = edit ? 'Edit course' : 'Add course';
        $('courseModalSub').textContent   = edit ? 'Update the details below, then save.' : 'New courses start as Open.';
        $('cfSubmit').textContent         = edit ? 'Save changes' : 'Add course';
        $('cfAction').value = edit ? 'edit_course' : 'add_course';
        $('cfId').value     = edit ? d.id : '';
        $('cfCode').value   = edit ? d.code : '';
        $('cfName').value   = edit ? d.name : '';
        $('cfYear').value   = edit ? d.year : '';
        $('cfUnits').value  = edit ? d.units : 3;
        $('cfStatusWrap').hidden = !edit;
        $(edit && d.status === 'Closed' ? 'cfClosed' : 'cfOpen').checked = true;
    }

    $('openAddCourse').addEventListener('click', () => { setMode(false); UI.open(modal); });

    document.querySelectorAll('.js-edit-course').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault(); e.stopPropagation(); // open instantly; the ?edit=ID link remains as a no-JS fallback
            setMode(true, a.dataset);
            UI.open(modal);
        });
    });

    // Closing a server-opened edit modal and then pressing "+ Add Course" must start blank.
    modal.addEventListener('ui:close', () => { /* state is reset by setMode on next open */ });
})();
</script>

<?php require '../includes/footer.php'; ?>
