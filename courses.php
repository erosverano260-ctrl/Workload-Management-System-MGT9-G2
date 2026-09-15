<?php
$page='courses';$title='Course Catalog';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_code'], $_POST['student_id'])) {
    [$ok, $msg] = enrollStudent($_POST['student_id'], $_POST['course_code'], $courses);
    $flash = ['type' => $ok ? 'success' : 'error', 'msg' => $msg];
    $enrollmentsAll = loadEnrollments();
}

require 'includes/header.php';

$year=(int)($_GET['year']??0);$search=trim($_GET['search']??'');
$filtered=array_values(array_filter($courses,function($c)use($year,$search){
 return (!$year||$c['year']===$year) && (!$search||stripos($c['code'],$search)!==false||stripos($c['name'],$search)!==false||stripos($c['instructor'],$search)!==false);
}));
?>


<section class="page-section">
<div class="section-heading"><div><span class="eyebrow">COURSE CATALOG</span><h2>Find and enroll students</h2></div>
<form class="search"><span>⌕</span><input id="courseSearch" value="<?= h($search) ?>" placeholder="Search course or instructor..."></form></div>

<?php if ($flash): ?><div class="flash <?= $flash['type'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="year-tabs"><a class="<?= !$year?'selected':'' ?>" href="courses.php">All Years</a><?php for($y=1;$y<=4;$y++): ?><a class="<?= $year===$y?'selected':'' ?>" href="?year=<?=$y?>"><?=$y?><?= $y===1?'st':($y===2?'nd':($y===3?'rd':'th')) ?> Year</a><?php endfor;?></div>
<div class="course-grid" id="courseGrid">
<?php foreach($filtered as $c):
    $enrolled = enrolledCountForCourse($enrollmentsAll, $c['code']);
    $isFull = $enrolled >= $c['capacity'];
?>
<article class="course-card searchable">
<div class="course-top"><span class="code"><?=h($c['code'])?></span><span class="status <?= $isFull ? 'closed' : strtolower($c['status']) ?>"><?= $isFull ? 'Full' : h($c['status']) ?></span></div>
<h3><?=h($c['name'])?></h3><div class="course-meta"><span>◷ <?=h($c['schedule'])?></span><span>⌂ <?=h($c['room'])?></span><span>♙ <?=h($c['instructor'])?></span></div>
<div class="course-bottom"><span><b><?=$c['units']?></b> units · Year <?=$c['year']?> · <?=$enrolled?>/<?=$c['capacity']?> seats</span>
<button class="outline-btn enroll-btn" <?= ($c['status']==='Closed'||$isFull) ? 'disabled' : '' ?> data-code="<?=h($c['code'])?>" data-name="<?=h($c['name'])?>" data-instructor="<?=h($c['instructor'])?>" data-schedule="<?=h($c['schedule'])?>" data-room="<?=h($c['room'])?>" data-units="<?=$c['units']?>">Enroll Student</button></div>
</article>
<?php endforeach;?>
</div>
<?php if(!$filtered):?><div class="empty">No courses found.</div><?php endif;?>
</section>

<div class="modal-backdrop" id="enrollBackdrop"></div>
<div class="modal" id="enrollModal">
<button class="modal-close" id="closeModal">×</button><span class="pill">PROCESS ENROLLMENT</span><h2>Enroll a student</h2>
<div class="confirm-box"><strong id="modalCourse"></strong><span id="modalCode"></span><div><span>Instructor</span><b id="modalInstructor"></b></div><div><span>Schedule</span><b id="modalSchedule"></b></div><div><span>Classroom</span><b id="modalRoom"></b></div></div>
<form method="post" id="enrollForm">
<input type="hidden" name="course_code" id="modalCourseCode">
<label class="muted" for="modalStudentSelect">Select student</label>
<select name="student_id" id="modalStudentSelect" required style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);margin-top:6px;margin-bottom:14px">
<option value="">— Choose student —</option>
<?php foreach($students as $s): ?><option value="<?=h($s['id'])?>"><?=h($s['name'])?> (<?=h($s['id'])?>, Yr <?=$s['year']?>)</option><?php endforeach; ?>
</select>
<button type="submit" class="primary-btn full">Confirm Enrollment <span>→</span></button>
</form>
</div>
<?php require 'includes/footer.php'; ?>
