<?php
$page = 'print'; $title = 'Print Forms';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/print_helpers.php';
requireRole('admin');

$conn  = (new database())->connect();
$types = ['section' => "Student's Class Program", 'instructor' => "Instructor's Class Program", 'room' => 'Room Utilization', 'matrix' => 'Faculty Workload Matrix'];
$type  = $_GET['type'] ?? 'section';
if (!isset($types[$type])) $type = 'section';
$sel   = (string)($_GET['id'] ?? '');

$sections = array_column($conn->query("SELECT DISTINCT section FROM schedules WHERE section IS NOT NULL AND section <> '' ORDER BY section")->fetch_all(MYSQLI_ASSOC), 'section');
$faculty  = $conn->query("SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$rooms    = $conn->query("SELECT room_id, room_code, room_name, capacity FROM rooms ORDER BY room_name")->fetch_all(MYSQLI_ASSOC);

/* paper size per form: long bond / letter / long bond landscape / A4 */
$paper = ['instructor' => '8.5in 13in', 'section' => '8.5in 11in', 'room' => '13in 8.5in', 'matrix' => 'A4'][$type];

require '../includes/header.php';
?>
<link rel="stylesheet" href="<?= asset('print.css') ?>">
<style>@page { size: <?= $paper ?>; margin: 8mm; }</style>

<section class="page-section">
<div class="section-heading no-print">
    <div><span class="eyebrow">OFFICIAL FORMS</span><h2>Print forms</h2></div>
    <button class="primary-btn" type="button" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<nav class="print-tabs no-print">
<?php foreach ($types as $k => $label): ?><a href="?type=<?= h($k) ?>" class="<?= $k === $type ? 'on' : '' ?>"><?= h($label) ?></a><?php endforeach; ?>
</nav>

<?php if (in_array($type, ['section', 'instructor', 'room'], true)): ?>
<form method="get" class="print-bar no-print">
    <input type="hidden" name="type" value="<?= h($type) ?>">
    <select name="id" onchange="this.form.submit()" aria-label="Choose">
    <?php if ($type === 'section'): foreach ($sections as $s): ?><option value="<?= h($s) ?>" <?= $sel === $s ? 'selected' : '' ?>><?= h(section_label($s)) ?></option><?php endforeach;
          elseif ($type === 'instructor'): foreach ($faculty as $f): ?><option value="<?= (int)$f['faculty_id'] ?>" <?= $sel === (string)$f['faculty_id'] ? 'selected' : '' ?>><?= h($f['faculty_name']) ?></option><?php endforeach;
          else: ?><option value="all">All rooms</option><?php foreach ($rooms as $r): ?><option value="<?= (int)$r['room_id'] ?>" <?= $sel === (string)$r['room_id'] ? 'selected' : '' ?>><?= h($r['room_name']) ?></option><?php endforeach; endif; ?>
    </select>
    <span class="hint">Choose paper size "<?= h($paper) ?>" and turn on "Background graphics" if your browser asks.</span>
</form>
<?php endif; ?>

<?php
if ($type === 'section') {
    $section = $sel !== '' && in_array($sel, $sections, true) ? $sel : ($sections[0] ?? '');
    if ($section === '') { echo '<div class="empty">No scheduled sections yet.</div>'; }
    else {
        $entries = load_program_entries($conn, 'section', $section);
        $yr = $entries ? (int)$entries[0]['year_level'] : (preg_match('/(\d)[A-Za-z]?$/', $section, $m) ? (int)$m[1] : 0);
        $st = $conn->prepare("SELECT course_id, course_code, course_name, units FROM courses WHERE year_level = ? AND status = 'Open' ORDER BY course_code");
        $st->bind_param('i', $yr); $st->execute();
        render_section_program($section, $entries, $st->get_result()->fetch_all(MYSQLI_ASSOC));
    }
} elseif ($type === 'instructor') {
    $inst = null; foreach ($faculty as $f) if ($sel === (string)$f['faculty_id']) $inst = $f;
    $inst = $inst ?? ($faculty[0] ?? null);
    if (!$inst) { echo '<div class="empty">Add an instructor first.</div>'; }
    else render_instructor_program($inst, load_program_entries($conn, 'faculty', (int)$inst['faculty_id']));
} elseif ($type === 'room') {
    $list = $rooms;
    if ($sel !== '' && $sel !== 'all') $list = array_values(array_filter($rooms, fn($r) => (string)$r['room_id'] === $sel));
    render_room_utilization($list, load_program_entries($conn));
} else {
    render_workload_matrix($faculty, load_program_entries($conn));
}
?>
</section>

<?php require '../includes/footer.php'; ?>
