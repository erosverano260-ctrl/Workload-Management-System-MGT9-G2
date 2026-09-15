<?php
$page='enroll';$title='Process Enrollment';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'drop' && isset($_POST['id'])) {
        dropEnrollment($_POST['id']);
        $flash = ['type' => 'success', 'msg' => 'Enrollment record dropped.'];
    } elseif (isset($_POST['course_code'], $_POST['student_id'])) {
        [$ok, $msg] = enrollStudent($_POST['student_id'], $_POST['course_code'], $courses);
        $flash = ['type' => $ok ? 'success' : 'error', 'msg' => $msg];
    }
    $enrollmentsAll = loadEnrollments();
}

require 'includes/header.php';

$filterStudent = $_GET['student'] ?? '';
$rows = $enrollmentsAll;
if ($filterStudent) $rows = array_values(array_filter($rows, fn($e) => $e['student_id'] === $filterStudent));
usort($rows, fn($a,$b) => strcmp($b['date_enrolled'], $a['date_enrolled']));
?>
<section class="page-section">
<div class="section-heading"><div><span class="eyebrow">REGISTRAR TOOLS</span><h2>Process student enrollment</h2></div>
<span class="date-chip"><?= count($enrollmentsAll) ?> total records</span></div>

<?php if ($flash): ?><div class="flash <?= $flash['type'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<form method="post" class="enroll-form-card">
    <div class="field">Student
        <select name="student_id" required>
            <option value="">— Choose student —</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= h($s['id']) ?>"><?= h($s['name']) ?> (<?= h($s['id']) ?>, Yr <?= $s['year'] ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">Course
        <select name="course_code" required>
            <option value="">— Choose course —</option>
            <?php foreach ($courses as $c):
                $enrolled = enrolledCountForCourse($enrollmentsAll, $c['code']);
            ?>
                <option value="<?= h($c['code']) ?>" <?= ($c['status']==='Closed' || $enrolled >= $c['capacity']) ? 'disabled' : '' ?>>
                    <?= h($c['code']) ?> — <?= h($c['name']) ?> (<?= $enrolled ?>/<?= $c['capacity'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="primary-btn">Process Enrollment <span>→</span></button>
</form>

<div class="section-heading" style="margin-top:8px">
    <div><span class="eyebrow">RECORDS</span><h2 style="font-size:18px">All enrollment records</h2></div>
    <form class="search"><span>⌕</span>
        <select name="student" onchange="this.form.submit()" style="border:none;background:transparent;font-size:13px;outline:none">
            <option value="">All students</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= h($s['id']) ?>" <?= $filterStudent===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="table-card">
<table><thead><tr><th>STUDENT</th><th>COURSE</th><th>SCHEDULE</th><th>DATE ENROLLED</th><th>PROCESSED BY</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r):
    $st = studentById($students, $r['student_id']);
    $co = courseByCode($courses, $r['course_code']);
    if (!$st || !$co) continue;
?>
<tr>
<td><strong><?= h($st['name']) ?></strong><br><small class="muted"><?= h($st['id']) ?></small></td>
<td><?= h($co['code']) ?> — <?= h($co['name']) ?></td>
<td><?= h($co['schedule']) ?></td>
<td><?= h($r['date_enrolled']) ?></td>
<td><?= h($r['processed_by']) ?></td>
<td><form method="post" data-confirm="Drop this enrollment?"><input type="hidden" name="action" value="drop"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><button type="submit" class="danger-btn">Drop</button></form></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="empty">No enrollment records yet.</td></tr><?php endif; ?>
</tbody></table>
</div>
</section>
<?php require 'includes/footer.php'; ?>
