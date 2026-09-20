<?php
$page='courses';$title='Course Catalog';
require 'data/get_courses.php';
require 'includes/auth.php';
requireRole('faculty');


function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$db = new database();
$conn = $db->connect();

$flash = null;

// --- ADD COURSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_course') {
    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, year_level, units, status) VALUES (?, ?, ?, ?, 'Open')");
    $stmt->bind_param("ssii", $_POST['course_code'], $_POST['course_name'], $_POST['year_level'], $_POST['units']);
    $stmt->execute();
    $flash = ['type'=>'success','msg'=>'Course added.'];
}

// --- EDIT COURSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_course') {
    $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, year_level=?, units=?, status=? WHERE course_id=?");
    $stmt->bind_param("ssiisi", $_POST['course_code'], $_POST['course_name'], $_POST['year_level'], $_POST['units'], $_POST['status'], $_POST['course_id']);
    $stmt->execute();
    $flash = ['type'=>'success','msg'=>'Course updated.'];
}

// --- DELETE COURSE ---
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    header('Location: courses.php'); exit;
}

require 'includes/header.php';

$year = (int)($_GET['year'] ?? 0);
$search = trim($_GET['search'] ?? '');

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

<section class="page-section">
<div class="section-heading">
  <div><span class="eyebrow">COURSE CATALOG</span><h2>Manage courses</h2></div>
  <form class="search"><span>⌕</span><input id="courseSearch" value="<?= h($search) ?>" placeholder="Search course or name..."></form>
<button class="primary-btn" id="openAddCourse">+ Add Course</button>
</div>

<?php if ($flash): ?><div class="flash <?= $flash['type'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

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
    <span class="status <?= strtolower($c['status']) ?>"><?=h($c['status'])?></span>
  </div>
  <h3><?=h($c['course_name'])?></h3>
  <div class="course-bottom">
    <span><b><?=h($c['units'])?></b> units · Year <?=h($c['year_level'])?></span>
    <a href="?edit=<?= $c['course_id'] ?>" class="outline-btn">Edit</a>
    <a href="?delete=<?= $c['course_id'] ?>" class="outline-btn" onclick="return confirm('Delete this course?')">Delete</a>
  </div>
</article>
<?php endforeach;?>
</div>
<?php if(!$filtered):?><div class="empty">No courses found.</div><?php endif;?>
</section>

<!-- ADD / EDIT COURSE MODAL -->
<div class="modal-backdrop" id="courseFormBackdrop"></div>
<div class="modal <?= $editing ? 'open' : '' ?>" id="courseFormModal">
  <button class="modal-close" id="closeCourseForm">×</button>
  <h2><?= $editing ? 'Edit Course' : 'Add Course' ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editing ? 'edit_course' : 'add_course' ?>">
    <?php if ($editing): ?><input type="hidden" name="course_id" value="<?= h($editing['course_id']) ?>"><?php endif; ?>

    <div class="form-group">
        <label >Course Code</label>
        <input type="text" name="course_code" required value="<?= h($editing['course_code'] ?? '') ?>" placeholder="e.g. EE301">
    </div>

    <div class="form-group">
        <label class="form-group">Course Name</label>
        <input type="text" name="course_name" required value="<?= h($editing['course_name'] ?? '') ?>" placeholder="e.g. Power Systems 1">
    </div>

    <div class="form-group">
        <label class="form-group">Year Level</label>
        <select name="year_level" required>
        <option value="">Select</option>
        <?php for($y=1;$y<=4;$y++): ?>
        <option value="<?=$y?>" <?= ($editing['year_level'] ?? '')==$y?'selected':'' ?>><?=$y?></option>
        <?php endfor; ?>
        </select>
    </div>

    <div class="form-group">
        <label class="form-group">Units</label>
        <input type="number" name="units" required value="<?= h($editing['units'] ?? 3) ?>">
    </div>

    <div class="form-group">
        <?php if ($editing): ?>
        <label>Status</label>
        <select name="status">
        <option value="Open" <?= $editing['status']==='Open'?'selected':'' ?>>Open</option>
        <option value="Closed" <?= $editing['status']==='Closed'?'selected':'' ?>>Closed</option>
        </select>
        <?php endif; ?>
    </div>

    <button type="submit" class="primary-btn full"><?= $editing ? 'Save Changes' : 'Add Course' ?></button>
  </form>
</div>

<?php require 'includes/footer.php'; ?>