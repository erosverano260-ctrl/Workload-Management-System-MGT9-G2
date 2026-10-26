<?php
/** Shared renderers for the printed forms (instructor program, section program, room utilization, workload matrix). */
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/schedule_helpers.php';

function pcfg(): array { static $c = null; if ($c === null) $c = require __DIR__ . '/print_config.php'; return $c; }

/** Section name as it should print (old BSIT- prefix shown as BSEE-). */
function section_label(?string $s): string { return strtr((string)$s, pcfg()['section_aliases'] ?? []); }

function is_lab(string $code): bool { return (bool)preg_match('/\dL$/i', trim($code)); }

/** "Engr. Ramon Torres" -> "R.Torres" */
function short_name(?string $n): string {
    if (!$n) return '';
    $n = preg_replace('/^(engr|dr|prof|mr|ms|mrs)\.?\s+/i', '', trim($n));
    $p = preg_split('/\s+/', $n);
    return count($p) < 2 ? $n : strtoupper(mb_substr($p[0], 0, 1)) . '.' . end($p);
}

/** Mix a #rrggbb colour with white so printed cells stay readable. */
function tint(?string $hex, float $w = 0.55): string {
    if (!preg_match('/^#([0-9a-f]{6})$/i', (string)$hex, $m)) $m = [0, '67e0a3'];
    $out = '#';
    foreach ([0, 2, 4] as $i) $out .= str_pad(dechex((int)round(hexdec(substr($m[1], $i, 2)) * (1 - $w) + 255 * $w)), 2, '0', STR_PAD_LEFT);
    return $out;
}

/** Join back-to-back 30-minute rows of the same class into one block. */
function merge_adjacent(array $rows): array {
    $out = []; $open = [];
    foreach ($rows as $r) {
        $k = implode('|', [$r['day_of_week'], $r['course_id'], $r['room_id'], $r['section'], $r['faculty_id']]);
        if (isset($open[$k]) && $out[$open[$k]]['end_time'] === $r['start_time']) { $out[$open[$k]]['end_time'] = $r['end_time']; continue; }
        $out[] = $r; $open[$k] = count($out) - 1;
    }
    return $out;
}

/** Classes with course/room/instructor names. $by = 'section' | 'faculty' | 'room' | '' (everything). */
function load_program_entries(mysqli $c, string $by = '', $val = null): array {
    $cols = ['section' => ['s.section', 's'], 'faculty' => ['s.faculty_id', 'i'], 'room' => ['s.room_id', 'i']];
    $sql = "SELECT s.schedule_id, s.course_id, s.faculty_id, s.room_id, s.section, s.day_of_week, s.start_time, s.end_time, s.cell_color,
                   c.course_code, c.course_name, c.units, c.year_level, r.room_name, f.faculty_name
            FROM schedules s
            JOIN courses c ON c.course_id = s.course_id
            JOIN rooms r   ON r.room_id   = s.room_id
            LEFT JOIN faculty f ON f.faculty_id = s.faculty_id";
    if (isset($cols[$by])) $sql .= " WHERE {$cols[$by][0]} = ?";
    $sql .= " ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.start_time";
    $st = $c->prepare($sql);
    if (isset($cols[$by])) $st->bind_param($cols[$by][1], $val);
    $st->execute();
    $rows = merge_adjacent($st->get_result()->fetch_all(MYSQLI_ASSOC));
    foreach ($rows as &$r) $r['section'] = section_label($r['section']);
    unset($r);
    return $rows;
}

/** One row per subject+section with lecture/lab hours, plus the load maths used by the workload matrix (lab hours count x0.7). */
function workload_summary(array $entries): array {
    $rows = [];
    foreach ($entries as $e) {
        $k = $e['course_code'] . '|' . $e['section'];
        if (!isset($rows[$k])) $rows[$k] = ['code' => $e['course_code'], 'name' => $e['course_name'], 'section' => $e['section'],
                                            'units' => (float)$e['units'], 'mins' => 0, 'rooms' => [], 'lab' => is_lab($e['course_code'])];
        $rows[$k]['mins'] += timeToMin($e['end_time']) - timeToMin($e['start_time']);
        $rows[$k]['rooms'][$e['room_name']] = true;
    }
    $lec = $lab = $unitsLec = $unitsLab = 0.0; $preps = [];
    foreach ($rows as &$r) {
        $r['hrs'] = $r['mins'] / 60;
        $r['room'] = implode(', ', array_keys($r['rooms']));
        $r['eq'] = $r['lab'] ? round($r['hrs'] * 0.7, 1) : 0.0;
        if ($r['lab']) { $lab += $r['hrs']; $unitsLab += $r['units']; } else { $lec += $r['hrs']; $unitsLec += $r['units']; }
        $preps[strtoupper(preg_replace('/\s*L$/i', '', trim($r['code'])))] = true;
    }
    unset($r);
    $np = count($preps);
    $allowed = $np >= 5 ? 15 : ($np >= 3 ? 18 : 21);          // regular load by number of preparations (from the HAIDE matrix)
    $comp = $lec + 0.7 * $lab;
    return ['rows' => array_values($rows), 'lec' => $lec, 'lab' => $lab, 'units' => $unitsLec + $unitsLab, 'unitsLec' => $unitsLec, 'unitsLab' => $unitsLab,
            'preps' => $np, 'allowed' => $allowed, 'comp' => round($comp, 1), 'overload' => round($comp - $allowed, 1)];
}

function nf($n): string { $n = round((float)$n, 1); return $n == (int)$n ? (string)(int)$n : (string)$n; }

/** Form header: logos left, titles centre, reference box + TUV NORD badge upper right. */
function print_header(string $title, string $refKey, string $subtitle = ''): void {
    $c = pcfg(); ?>
<table class="ph"><tr>
  <td class="ph-logos"><img src="<?= asset('logo-cote.png') ?>" alt=""><img src="<?= asset('logo-pit.png') ?>" alt=""></td>
  <td class="ph-title">
    <div><?= h($c['republic']) ?></div>
    <div class="b"><?= h($c['school']) ?></div>
    <div><?= h($c['place']) ?></div>
    <div class="b gap"><?= h($c['college']) ?></div>
    <div class="b gap2"><?= h($title) ?></div>
    <?php if ($subtitle): ?><div class="b"><?= h($subtitle) ?></div><?php endif; ?>
  </td>
  <td class="ph-right">
    <table class="ph-ref">
      <tr><td>Ref. Code : <?= h($c['codes'][$refKey] ?? '') ?></td></tr>
      <tr><td>Rev. No. : <?= h($c['rev_no']) ?></td></tr>
      <tr><td>Date : <?= h($c['date']) ?></td></tr>
      <tr><td>Prep. By : <?= h($c['prep_by']) ?> &nbsp; Rev. by : <?= h($c['rev_by']) ?></td></tr>
    </table>
    <img class="tuv" src="<?= asset('tuv-nord.png') ?>" alt="TUV NORD — PIT is ISO 9001:2015 certified by TUV NORD Philippines, Inc.">
  </td>
</tr></table>
<?php }

/** The weekly grid: 7:00-12:00, one LUNCH BREAK row, 1:00-7:00, in 30-minute rows with merged blocks. */
function timetable_html(array $entries, callable $lines, array $days = FAC_DAYS, bool $period = false): string {
    $lab = fn(int $m) => ((intdiv($m, 60) % 12) ?: 12) . ':' . sprintf('%02d', $m % 60);
    $rows = [];
    for ($m = 420; $m < 720; $m += 30)  $rows[] = $lab($m) . ' - ' . $lab($m + 30);   // rows 0-9
    $rows[] = '12:00 - 1:00';                                                           // row 10 = lunch
    for ($m = 780; $m < 1140; $m += 30) $rows[] = $lab($m) . ' - ' . $lab($m + 30);   // rows 11-22
    $rowOf = fn(int $m) => $m < 720 ? intdiv($m - 420, 30) : 11 + intdiv($m - 780, 30);
    $endOf = fn(int $m) => $m <= 720 ? intdiv($m - 420 + 29, 30) : 11 + intdiv($m - 780 + 29, 30);

    $grid = array_fill(0, count($days), []);
    foreach ($entries as $e) {
        $di = array_search($e['day_of_week'], $days, true);
        if ($di === false) continue;
        $s = max(420, timeToMin($e['start_time'])); $en = min(1140, timeToMin($e['end_time']));
        if ($s >= 720 && $s < 780) continue;
        if ($s < 720 && $en > 720) $en = 720;
        if ($en <= $s) continue;
        $r1 = $rowOf($s); $r2 = max($r1 + 1, $endOf($en));
        $free = true; for ($r = $r1; $r < $r2; $r++) if (isset($grid[$di][$r])) $free = false;
        if (!$free) continue;
        $grid[$di][$r1] = ['rs' => $r2 - $r1, 'e' => $e];
        for ($r = $r1 + 1; $r < $r2; $r++) $grid[$di][$r] = false;
    }

    ob_start(); ?>
<table class="tt">
<thead><tr><?php if ($period): ?><th class="per"></th><?php endif; ?><th class="tcol"><?= $period ? 'TIME' : 'TIME / PERIOD' ?></th>
<?php foreach ($days as $d): ?><th><?= h(strtoupper($d)) ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php foreach ($rows as $r => $label): ?>
<tr>
  <?php if ($period): if ($r === 0): ?><td class="per" rowspan="10"><span>A.M.</span></td><?php elseif ($r === 10): ?><td class="per"></td><?php elseif ($r === 11): ?><td class="per" rowspan="12"><span>P.M.</span></td><?php endif; endif; ?>
  <td class="tcol"><?= h($label) ?></td>
  <?php if ($r === 10): ?><td class="lunch" colspan="<?= count($days) ?>">L U N C H &nbsp; B R E A K</td>
  <?php else: foreach ($days as $di => $d):
      $cell = $grid[$di][$r] ?? null;
      if ($cell === false) continue;
      if ($cell === null): ?><td></td>
      <?php else: ?><td class="blk" rowspan="<?= $cell['rs'] ?>" style="background:<?= h(tint($cell['e']['cell_color'])) ?>">
        <?php foreach ($lines($cell['e']) as $i => $ln): if ($ln === '' || $ln === null) continue; ?><div<?= $i === 0 ? ' class="b"' : '' ?>><?= h($ln) ?></div><?php endforeach; ?>
      </td><?php endif;
  endforeach; endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php return ob_get_clean();
}

/* ====================== 1. INSTRUCTOR'S CLASS PROGRAM ====================== */
function render_instructor_program(array $inst, array $entries): void {
    $c = pcfg(); $w = workload_summary($entries);
    $blank = max(0, 8 - count($w['rows']));
    $remarks = [['No. of Preparations:', (string)$w['preps']], ['Service Credits:', ''], ['Teaching Overload:', nf($w['overload'])]];
?>
<div class="sheet sheet-instructor">
  <?php print_header("INSTRUCTOR'S CLASS PROGRAM", 'instructor'); ?>
  <table class="info">
    <tr><td class="k">Name:</td><td class="ul"><?= h($inst['faculty_name']) ?></td><td class="k">Term:</td><td class="ul"><?= h($c['term'] . ', S.Y. ' . $c['sy']) ?></td></tr>
    <tr><td class="k">Academic Rank:</td><td class="ul"></td><td class="k">Department:</td><td class="ul b"><?= h($c['department']) ?></td></tr>
    <tr><td></td><td></td><td class="k">College:</td><td class="ul"><?= h($c['college_short']) ?></td></tr>
  </table>
  <?= timetable_html($entries, fn($e) => [$e['course_code'], $e['section'], short_name($e['faculty_name']), $e['room_name']]) ?>
  <p class="note">Indicate subject, year and section room, and number of students (Estimated)</p>

  <table class="wl">
    <tr class="h"><th>Workload</th><th>Lecture Hrs/Wk</th><th>Lab Hrs./Wk</th><th>Total</th><th colspan="2">Remarks</th></tr>
    <tr><td class="l b">A. Teaching Load:</td><td></td><td></td><td></td><td><?= h($remarks[0][0]) ?></td><td class="r"><?= h($remarks[0][1]) ?></td></tr>
    <?php foreach ($w['rows'] as $i => $r): $rm = $remarks[$i + 1] ?? ['', '']; ?>
    <tr><td class="l"><?= h($r['code'] . ' — ' . $r['section']) ?></td>
        <td><?= $r['lab'] ? '' : nf($r['hrs']) ?></td><td><?= $r['lab'] ? nf($r['hrs']) : '' ?></td><td><?= nf($r['hrs']) ?></td>
        <td><?= h($rm[0]) ?></td><td class="r"><?= h($rm[1]) ?></td></tr>
    <?php endforeach; for ($i = 0; $i < $blank; $i++): $rm = $remarks[count($w['rows']) + $i + 1] ?? ['', '']; ?>
    <tr><td class="l">&nbsp;</td><td></td><td></td><td></td><td><?= h($rm[0]) ?></td><td class="r"><?= h($rm[1]) ?></td></tr>
    <?php endfor; ?>
    <tr class="sub"><td class="r b">Sub-Total</td><td><?= nf($w['lec']) ?></td><td><?= nf($w['lab']) ?></td><td><?= nf($w['lec'] + $w['lab']) ?></td><td colspan="2"></td></tr>
    <tr><td class="l b">B. Other Functions:</td><td></td><td></td><td></td><td colspan="2" class="b">Total Number of Students:</td></tr>
    <?php foreach (['Research', 'Extension', 'IMP', 'Consultation'] as $o): ?><tr><td class="l"><?= $o ?></td><td></td><td></td><td></td><td colspan="2"></td></tr><?php endforeach; ?>
    <tr class="sub"><td class="r b">Sub-Total</td><td></td><td></td><td>0</td><td colspan="2"></td></tr>
    <tr><td class="l b">C. Designation</td><td></td><td></td><td></td><td colspan="2"></td></tr>
    <tr><td class="l">Dept.Chair</td><td></td><td></td><td></td><td colspan="2"></td></tr>
    <tr><td class="l">BSEE Adviser</td><td></td><td></td><td></td><td colspan="2"></td></tr>
    <tr class="sub"><td class="r b">Sub-Total</td><td></td><td></td><td>0</td><td colspan="2"></td></tr>
    <tr class="grand"><td colspan="3" class="b">Grand Total (A+B+C)</td><td class="b"><?= nf($w['lec'] + $w['lab']) ?></td><td colspan="2"></td></tr>
  </table>

  <table class="act">
    <tr><th>ACTION TAKEN</th><th>NAME</th><th>POSITION</th><th>SIGNATURE</th></tr>
    <tr><td>Prepared by:</td><td class="c"><?= h($inst['faculty_name']) ?></td><td class="c">Faculty</td><td></td></tr>
    <tr><td>Reviewed by:</td><td></td><td class="c">Chair</td><td></td></tr>
    <tr><td>Verified by:</td><td></td><td class="c">Dean, COTE</td><td></td></tr>
    <tr><td>Approved by:</td><td></td><td class="c">VP-Academic Affairs</td><td></td></tr>
  </table>
  <p class="eff">Effective:</p>
</div>
<?php }

/* ====================== 2. STUDENT'S CLASS PROGRAM (per section) ====================== */
function render_section_program(string $section, array $entries, array $courses): void {
    $c = pcfg();
    $hrs = [];
    foreach ($entries as $e) $hrs[$e['course_id']] = ($hrs[$e['course_id']] ?? 0) + (timeToMin($e['end_time']) - timeToMin($e['start_time'])) / 60;
?>
<div class="sheet sheet-section">
  <?php print_header("STUDENT'S CLASS PROGRAM S.Y. " . $c['sy'], 'section'); ?>
  <table class="info">
    <tr><td class="k">COURSE:</td><td class="u b"><?= h($c['program']) ?></td><td class="k">YEAR &amp; SECTION:</td><td class="u b"><?= h(section_label($section)) ?></td></tr>
    <tr><td class="k">DEPARTMENT:</td><td class="u b"><?= h(strtoupper($c['department'])) ?></td><td class="k">TERM:</td><td class="u"><?= h($c['term']) ?></td></tr>
  </table>
  <?= timetable_html($entries, fn($e) => [$e['course_code'], short_name($e['faculty_name']), $e['room_name']], FAC_DAYS, true) ?>

  <p class="cap">SUBJECTS FOR LOADING:</p>
  <table class="sl">
    <tr><th>Subject Code</th><th>Subject Description</th><th>Units</th><th>Hours / week scheduled</th></tr>
    <?php foreach ($courses as $co): ?>
    <tr><td class="b"><?= h($co['course_code']) ?></td><td><?= h($co['course_name']) ?></td><td><?= h($co['units']) ?></td>
        <td><?= isset($hrs[$co['course_id']]) ? nf($hrs[$co['course_id']]) : '<i>not yet scheduled</i>' ?></td></tr>
    <?php endforeach; if (!$courses): ?><tr><td colspan="4"><i>No open courses for this year level.</i></td></tr><?php endif; ?>
  </table>
</div>
<?php }

/* ====================== 3. ROOM UTILIZATION ====================== */
function render_room_utilization(array $rooms, array $allEntries): void {
    $c = pcfg();
    $pairs = array_chunk($rooms, 2);
?>
<div class="sheet sheet-room">
<?php foreach ($pairs as $pi => $pair): ?>
  <div class="room-page">
    <?php if ($pi === 0) print_header('ROOM UTILIZATION', 'room', strtoupper($c['term']) . ' ' . $c['sy']); ?>
    <div class="room-row">
    <?php foreach ($pair as $room):
        $mine = array_values(array_filter($allEntries, fn($e) => (int)$e['room_id'] === (int)$room['room_id']));
        $hours = 0; foreach ($mine as $e) $hours += (timeToMin($e['end_time']) - timeToMin($e['start_time'])) / 60;
        $pct = min(100, round($hours / 60 * 100)); ?>
      <div class="room-cell">
        <div class="room-title"><?= h(strtoupper($room['room_name'])) ?></div>
        <div class="room-stat"><?= h($room['room_code'] !== '' ? 'Room ' . $room['room_code'] . ' · ' : '') ?>Capacity <?= h($room['capacity']) ?> · <?= nf($hours) ?> of 60 hrs used (<?= $pct ?>%)</div>
        <?= timetable_html($mine, fn($e) => [$e['course_code'], $e['section'], short_name($e['faculty_name'])]) ?>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; if (!$rooms): ?><p>No rooms found.</p><?php endif; ?>
</div>
<?php }

/* ====================== 4. FACULTY WORKLOAD MATRIX (admin) ====================== */
function render_workload_matrix(array $faculty, array $entries): void {
    $c = pcfg();
    $by = []; foreach ($entries as $e) $by[(int)($e['faculty_id'] ?? 0)][] = $e;
    $blocks = [];
    foreach ($faculty as $f) { $blocks[] = ['name' => $f['faculty_name'], 'w' => workload_summary($by[(int)$f['faculty_id']] ?? [])]; }
    if (!empty($by[0])) $blocks[] = ['name' => 'UNASSIGNED CLASSES', 'w' => workload_summary($by[0])];
?>
<div class="sheet sheet-matrix">
  <?php print_header('FACULTY WORKLOAD MATRIX', 'matrix', strtoupper($c['term']) . ' ' . $c['sy']); ?>

  <p class="cap">SUMMARY</p>
  <table class="mx">
    <tr><th class="l">Faculty</th><th>No. of Prep.</th><th>Total Units</th><th>Lec Hrs</th><th>Lab Hrs</th><th>Comp: lec+lab (×0.7)</th><th>Regular load</th><th>Overload</th></tr>
    <?php foreach ($blocks as $b): $w = $b['w']; ?>
    <tr><td class="l"><?= h($b['name']) ?></td><td><?= $w['preps'] ?></td><td><?= nf($w['units']) ?></td><td><?= nf($w['lec']) ?></td><td><?= nf($w['lab']) ?></td>
        <td><?= nf($w['comp']) ?></td><td><?= $w['preps'] ? $w['allowed'] : '—' ?></td><td class="<?= $w['overload'] > 0 && $w['preps'] ? 'over' : '' ?>"><?= $w['preps'] ? nf($w['overload']) : '—' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="note l">Regular load: 2 preparations = 21 · 3–4 preparations = 18 · 5 or more = 15. Lab hours count at 0.7.</p>

  <?php foreach ($blocks as $b): $w = $b['w']; ?>
  <table class="mx fac">
    <tr class="fh"><td colspan="4" class="l b">Faculty: <?= h($b['name']) ?></td><td colspan="2">Unit / subject</td><td colspan="3">No. of hrs. / subject</td></tr>
    <tr class="h"><th class="l">Subject</th><th>Room (Lab)</th><th>Room (Lec)</th><th># of Sections</th><th>Lec</th><th>Lab</th><th>Lec</th><th>Lab</th><th>Lab EQ</th></tr>
    <?php foreach ($w['rows'] as $i => $r): ?>
    <tr><td class="l"><?= ($i + 1) . '. ' . h($r['code'] . ' (' . $r['section'] . ')') ?></td>
        <td><?= $r['lab'] ? h($r['room']) : '' ?></td><td><?= $r['lab'] ? '' : h($r['room']) ?></td><td>1</td>
        <td><?= $r['lab'] ? '' : nf($r['units']) ?></td><td><?= $r['lab'] ? nf($r['units']) : '' ?></td>
        <td><?= $r['lab'] ? '' : nf($r['hrs']) ?></td><td><?= $r['lab'] ? nf($r['hrs']) : '' ?></td><td><?= $r['lab'] ? nf($r['eq']) : '' ?></td></tr>
    <?php endforeach; if (!$w['rows']): ?><tr><td class="l" colspan="9"><i>No classes assigned.</i></td></tr><?php endif; ?>
    <tr class="tot"><td class="l b">No. of Prep. <?= $w['preps'] ?></td><td></td><td></td><td><?= count($w['rows']) ?></td>
        <td><?= nf($w['unitsLec']) ?></td><td><?= nf($w['unitsLab']) ?></td><td><?= nf($w['lec']) ?></td><td><?= nf($w['lab']) ?></td><td><?= nf(round($w['lab'] * 0.7, 1)) ?></td></tr>
    <tr class="tot"><td class="l b" colspan="4">Total Units: <?= nf($w['units']) ?> &nbsp;·&nbsp; Total Hrs.: <?= nf($w['lec'] + $w['lab']) ?></td>
        <td colspan="3" class="b">Comp: lec+lab (×0.7) = <?= nf($w['comp']) ?></td><td colspan="2" class="b <?= $w['overload'] > 0 && $w['preps'] ? 'over' : '' ?>">Overload: <?= $w['preps'] ? nf($w['overload']) : '—' ?></td></tr>
  </table>
  <?php endforeach; ?>
</div>
<?php }
