<?php
$page = 'schedule'; $title = 'My Schedule';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/schedule_helpers.php';
requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn    = (new database())->connect();
$inst    = loadInstructor($conn, currentUserId());
$entries = loadFacultySchedule($conn, (int)$inst['faculty_id']);

/* Timetable rows: 1 header · 2-11 = 7:00-12:00 · 12 lunch · 13-24 = 1:00-7:00 (30 min each) */
$AM0 = 7 * 60; $PM0 = 13 * 60;
$segments = []; $hidden = 0;
foreach ($entries as $e) {
    $di = array_search($e['day_of_week'], FAC_DAYS, true);
    if ($di === false) continue;
    $col = 3 + $di;
    $s = timeToMin($e['start_time']); $en = timeToMin($e['end_time']);
    $drawn = false;
    $as = max($s, $AM0); $ae = min($en, 12 * 60);
    if ($ae > $as) { $segments[] = [$col, 2 + intdiv($as - $AM0, 30), 2 + (int)ceil(($ae - $AM0) / 30), $e]; $drawn = true; }
    $ps = max($s, $PM0); $pe = min($en, 19 * 60);
    if ($pe > $ps) { $segments[] = [$col, 13 + intdiv($ps - $PM0, 30), 13 + (int)ceil(($pe - $PM0) / 30), $e]; $drawn = true; }
    if (!$drawn) $hidden++;
}
$lbl = fn(int $m) => ((intdiv($m, 60) % 12) ?: 12) . ':' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);

require '../includes/faculty_header.php';
?>
<style>
    .print-header { display: none; }
    @media print {
        .sidebar, .no-print { display: none !important; }
        .main-area { padding: 0; max-width: none; }
        .print-header { display: flex; }
        .fs-wrap { overflow: visible; border: 0; box-shadow: none; }
        .fs-wrap .schedule { min-width: 0; width: 100%;
            grid-template-columns: 34px 78px repeat(5, 1fr);
            grid-template-rows: 26px repeat(10, 26px) 22px repeat(12, 26px); }
        .schedule, .schedule * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .cell { padding: 1px; font-size: 8px; }
        .header { font-size: 9px; }
        .tt-entry { padding: 2px 4px; font-size: 7.5px; margin: 1px; }
        .tt-entry strong { font-size: 8.5px; }
        @page { size: landscape; margin: 8mm; }
    }
</style>
<link rel="stylesheet" href="<?= asset('faculty_schedule.css') ?>">

<section class="page-section">
<div class="section-heading no-print">
    <div><span class="eyebrow">READ-ONLY</span><h2>My weekly schedule</h2></div>
    <div style="display:flex;gap:10px"><a href="print.php" class="primary-btn" style="text-decoration:none">📄 Official class program</a>
    <button class="primary-btn" type="button" onclick="window.print()">🖨 Print this grid</button></div>
</div>

<div class="print-header">
    <img src="../assets/ee-logo.svg" alt="logo">
    <div>
        <div class="print-school">INSTITUTE OF INTEGRATED ELECTRICAL ENGINEERS</div>
        <h3><?= h($inst['faculty_name']) ?> — Weekly Teaching Load</h3>
        <p style="margin:0;font-size:12px;color:#555"><?= count($entries) ?> classes · <?= h(scheduleHours($entries)) ?> hrs · Printed <?= h(date('M j, Y')) ?></p>
    </div>
</div>

<?php if (!$entries): ?>
    <div class="empty">No classes are assigned to you yet. Once the department assigns them, they'll appear here.</div>
<?php else: ?>
<div class="fs-wrap">
  <div class="schedule">
    <div class="cell header" style="grid-column:1 / 3;grid-row:1">Time</div>
    <?php foreach (FAC_DAYS as $i => $d): ?><div class="cell header" style="grid-column:<?= 3 + $i ?>;grid-row:1"><?= h($d) ?></div><?php endforeach; ?>

    <div class="cell period" style="grid-column:1;grid-row:2 / 12">AM</div>
    <?php for ($i = 0; $i < 10; $i++): $m = $AM0 + $i * 30; ?>
    <div class="cell time" style="grid-column:2;grid-row:<?= 2 + $i ?>"><?= $lbl($m) ?> - <?= $lbl($m + 30) ?></div>
    <?php endfor; ?>

    <div class="cell lunch" style="grid-column:1 / 8;grid-row:12">LUNCH BREAK</div>

    <div class="cell period" style="grid-column:1;grid-row:13 / 25">PM</div>
    <?php for ($i = 0; $i < 12; $i++): $m = $PM0 + $i * 30; ?>
    <div class="cell time" style="grid-column:2;grid-row:<?= 13 + $i ?>"><?= $lbl($m) ?> - <?= $lbl($m + 30) ?></div>
    <?php endfor; ?>

    <?php /* empty background cells so the grid lines show */
    for ($c = 3; $c <= 7; $c++) {
        for ($r = 2; $r <= 11; $r++)  echo '<div class="cell" style="grid-column:' . $c . ';grid-row:' . $r . '"></div>';
        for ($r = 13; $r <= 24; $r++) echo '<div class="cell" style="grid-column:' . $c . ';grid-row:' . $r . '"></div>';
    } ?>

    <?php foreach ($segments as [$col, $r1, $r2, $e]): ?>
    <div class="tt-entry" style="grid-column:<?= $col ?>;grid-row:<?= $r1 ?> / <?= $r2 ?>;--c:<?= h($e['cell_color'] ?: '#ffb100') ?>"
         title="<?= h($e['course_code'] . ' · ' . $e['course_name'] . ' · ' . rangeLabel($e['start_time'], $e['end_time'])) ?>">
      <strong><?= h($e['course_code']) ?></strong>
      <span><?= h($e['course_name']) ?></span>
      <span><?= h($e['section']) ?> · <?= h($e['room_name']) ?></span>
      <span><?= h(rangeLabel($e['start_time'], $e['end_time'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php if ($hidden): ?>
<p class="tt-legend no-print"><?= $hidden ?> class<?= $hidden === 1 ? '' : 'es' ?> fall in the lunch hour or outside 7 AM – 7 PM and aren't drawn. Your dashboard lists everything.</p>
<?php endif; ?>
<?php endif; ?>
</section>

<?php require '../includes/footer.php'; ?>
