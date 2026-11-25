<?php
/**
 * Excel versions of the printed forms (instructor program, section program, room utilization, workload matrix).
 * Every sheet is set up for LONG bond paper (8.5 x 13 in): portrait, except Room Utilization which is landscape (13 x 8.5 in),
 * scaled to fit the page width. Uses the same data and load maths as the on-screen print forms.
 */
require_once __DIR__ . '/print_helpers.php';
require_once __DIR__ . '/xlsx_writer.php';

const XL_BORDER = 'LRTB';

/** "1.5" -> 1.5 so Excel stores a real number; anything else is left alone. */
function xl_num($v) { return (is_string($v) && $v !== '' && is_numeric($v)) ? (float)$v : $v; }
function xl_fill(?string $hex): string { return strtoupper(ltrim(tint($hex), '#')); }

/** Letterhead: title block on the left, reference-code box on the right. Returns the next free row. */
function xl_header(XlsxSheet $sh, int $r, int $c0, int $titleCols, int $refCols, string $title, string $refKey, string $subtitle = ''): int {
    $c = pcfg();
    $runs = [[$c['republic'] . "\n", false], [$c['school'] . "\n", true], [$c['place'] . "\n\n", false], [$c['college'] . "\n", true],
             [$title . ($subtitle !== '' ? "\n" : ''), true]];
    if ($subtitle !== '') $runs[] = [$subtitle, true];
    for ($i = 0; $i < 6; $i++) $sh->height($r + $i, $i === 3 ? 24 : 18);
    $sh->cell($r, $c0, $runs, ['sz' => 10, 'ha' => 'center', 'va' => 'center', 'wrap' => 1, 'bd' => XL_BORDER], 6, $titleCols);

    $rc = $c0 + $titleCols;
    $refs = ['Ref. Code : ' . ($c['codes'][$refKey] ?? ''), 'Rev. No. : ' . $c['rev_no'], 'Date : ' . $c['date']];
    foreach ($refs as $i => $t) $sh->cell($r + $i, $rc, $t, ['sz' => 8, 'ha' => 'left', 'bd' => XL_BORDER], 1, $refCols);
    $sh->cell($r + 3, $rc, 'Prep. By : ' . $c['prep_by'] . '   Rev. by : ' . $c['rev_by'], ['sz' => 8, 'ha' => 'left', 'wrap' => 1, 'bd' => XL_BORDER], 1, $refCols);
    $sh->cell($r + 4, $rc, "ISO 9001:2015 Certified\nTUV NORD Philippines, Inc.", ['sz' => 8, 'b' => 1, 'ha' => 'center', 'wrap' => 1, 'bd' => XL_BORDER], 2, $refCols);
    return $r + 6;
}

/** One "Label: value ____   Label: value ____" line. Layout: label(1) value(2 cols) label(1) value(2 cols). */
function xl_info_row(XlsxSheet $sh, int $r, int $c0, string $lk, string $lv, string $rk, string $rv, bool $boldL = false, bool $boldR = false): void {
    $k = ['sz' => 10, 'ha' => 'left'];
    $sh->height($r, 17);
    $sh->cell($r, $c0, $lk, $k);
    if ($lk !== '' || $lv !== '') $sh->cell($r, $c0 + 1, $lv, ['sz' => 10, 'ha' => 'left', 'b' => $boldL ? 1 : 0, 'bd' => 'B'], 1, 2);
    $sh->cell($r, $c0 + 3, $rk, $k);
    $sh->cell($r, $c0 + 4, $rv, ['sz' => 10, 'ha' => 'left', 'b' => $boldR ? 1 : 0, 'bd' => 'B'], 1, 2);
}

/** The weekly grid (7:00-12:00, LUNCH BREAK, 1:00-7:00 in 30-minute rows) with merged, coloured class blocks. Returns the next free row. */
function xl_timetable(XlsxSheet $sh, int $r, int $c0, array $entries, callable $lines, bool $period = false, ?string $hdrFill = null, int $rowH = 18, int $fs = 8): int {
    $days = FAC_DAYS;
    $lab = fn(int $m) => ((intdiv($m, 60) % 12) ?: 12) . ':' . sprintf('%02d', $m % 60);
    $rows = [];
    for ($m = 420; $m < 720; $m += 30)  $rows[] = $lab($m) . ' - ' . $lab($m + 30);
    $rows[] = '12:00 - 1:00';                                                           // index 10 = lunch
    for ($m = 780; $m < 1140; $m += 30) $rows[] = $lab($m) . ' - ' . $lab($m + 30);
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
        $free = true; for ($x = $r1; $x < $r2; $x++) if (isset($grid[$di][$x])) $free = false;
        if (!$free) continue;
        $grid[$di][$r1] = ['rs' => $r2 - $r1, 'e' => $e];
        for ($x = $r1 + 1; $x < $r2; $x++) $grid[$di][$x] = false;                       // covered by the merged block
    }

    $tc = $c0 + ($period ? 1 : 0);
    $hs = ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER];
    if ($hdrFill) $hs['fill'] = $hdrFill;
    $sh->height($r, 20);
    if ($period) $sh->cell($r, $c0, '', $hs);
    $sh->cell($r, $tc, $period ? 'TIME' : 'TIME / PERIOD', $hs);
    foreach ($days as $i => $d) $sh->cell($r, $tc + 1 + $i, strtoupper($d), $hs);

    $plain = ['sz' => $fs, 'ha' => 'center', 'bd' => XL_BORDER, 'wrap' => 1];
    foreach ($rows as $i => $label) {
        $rr = $r + 1 + $i;
        $sh->height($rr, $rowH);
        $sh->cell($rr, $tc, $label, ['sz' => 9, 'ha' => 'center', 'bd' => XL_BORDER]);
        if ($i === 10) { $sh->cell($rr, $tc + 1, 'L U N C H   B R E A K', ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER], 1, count($days)); continue; }
        foreach ($days as $di => $d) {
            $cv = $grid[$di][$i] ?? null;
            if ($cv === false) continue;
            if ($cv === null) { $sh->cell($rr, $tc + 1 + $di, '', $plain); continue; }
            $ls = array_values(array_filter($lines($cv['e']), fn($x) => $x !== '' && $x !== null));
            if (!$ls) $ls = [''];
            $runs = [[$ls[0] . (count($ls) > 1 ? "\n" : ''), true]];
            if (count($ls) > 1) $runs[] = [implode("\n", array_slice($ls, 1)), false];
            $sh->cell($rr, $tc + 1 + $di, $runs, ['sz' => $fs, 'ha' => 'center', 'bd' => XL_BORDER, 'wrap' => 1, 'fill' => xl_fill($cv['e']['cell_color'] ?? null)], $cv['rs'], 1);
        }
    }
    if ($period) {
        $sh->cell($r + 1,  $c0, 'A.M.', ['sz' => 9, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'rot' => 90], 10, 1);
        $sh->cell($r + 11, $c0, '', ['sz' => 9, 'bd' => XL_BORDER]);
        $sh->cell($r + 12, $c0, 'P.M.', ['sz' => 9, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'rot' => 90], 12, 1);
    }
    return $r + 1 + count($rows);
}

/* ====================== 1. INSTRUCTOR'S CLASS PROGRAM ====================== */
function xl_instructor(XlsxBook $book, array $inst, array $entries, array $designations = [], array $sessions = []): XlsxSheet {
    $c = pcfg(); $w = workload_summary($entries);
    $sh = $book->addSheet('Instr - ' . $inst['faculty_name']);
    foreach ([26, 15, 15, 15, 22, 15] as $i => $wd) $sh->width($i + 1, $wd);

    $r = xl_header($sh, 1, 1, 4, 2, "INSTRUCTOR'S CLASS PROGRAM", 'instructor') + 1;
    xl_info_row($sh, $r,     1, 'Name:', (string)$inst['faculty_name'], 'Term:', $c['term'] . ', S.Y. ' . $c['sy']);
    xl_info_row($sh, $r + 1, 1, 'Academic Rank:', '', 'Department:', $c['department'], false, true);
    xl_info_row($sh, $r + 2, 1, '', '', 'College:', $c['college_short']);
    $r = xl_timetable($sh, $r + 4, 1, $entries, fn($e) => [$e['course_code'] . ' (' . type_tag($e) . ')', $e['section'], short_name($e['faculty_name']), $e['room_name']]);
    $sh->height($r, 15);
    $sh->cell($r, 1, 'Indicate subject, year and section room, and number of students (Estimated)', ['sz' => 8, 'i' => 1, 'ha' => 'center'], 1, 6);
    $r += 2;

    $FL = faculty_load($entries, $designations, $sessions); $w = $FL; $desHrs = $FL['desHrs'];      // lec + lab x 0.7, preps, 40-hr week, claim, service credits
    $remarks = [['No. of Preparations:', (string)$w['preps']], ['Service Credits:', nf($FL['serviceCredit'])], ['Teaching Overload:', nf($w['overload'])],
                ['Overload hrs to claim:', nf($w['claim'])], ['Teaching Service Credit:', nf($w['credit'])]];

    $H  = ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'EEF1F8'];
    $L  = ['sz' => 10, 'ha' => 'left',   'bd' => XL_BORDER];
    $Lb = ['sz' => 10, 'b' => 1, 'ha' => 'left', 'bd' => XL_BORDER];
    $C  = ['sz' => 10, 'ha' => 'center', 'bd' => XL_BORDER];
    $Cb = ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER];
    $Rt = ['sz' => 10, 'ha' => 'right',  'bd' => XL_BORDER];
    $Rb = ['sz' => 10, 'b' => 1, 'ha' => 'right', 'bd' => XL_BORDER];
    $Sub = ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'F7F8FC'];

    // one row of the workload table: Workload | Lecture | Lab | Total | Remarks label | Remarks value
    $line = function (int $rr, $a, $b, $cc, $d, $e1, $e2, ?array $ast = null, ?array $mid = null) use ($sh, $L, $C, $Rt) {
        $sh->height($rr, 17);
        $sh->cell($rr, 1, $a, $ast ?? $L);
        $sh->cell($rr, 2, $b, $mid ?? $C); $sh->cell($rr, 3, $cc, $mid ?? $C); $sh->cell($rr, 4, $d, $mid ?? $C);
        $sh->cell($rr, 5, $e1, $L);
        $sh->cell($rr, 6, xl_num($e2), $Rt);
    };

    $sh->height($r, 18);
    foreach (['Workload', 'Lecture Hrs/Wk', 'Lab Hrs./Wk', 'Total'] as $i => $t) $sh->cell($r, 1 + $i, $t, $H);
    $sh->cell($r, 5, 'Remarks', $H, 1, 2);
    $r++;
    $line($r, 'A. Teaching Load:', '', '', '', $remarks[0][0], $remarks[0][1], $Lb); $r++;
    $subs = $w['subjects']; $blank = max(0, 8 - count($subs));
    foreach ($subs as $i => $s) {
        $rm = $remarks[$i + 1] ?? ['', ''];
        $line($r, $s['code'] . ' — ' . $s['section'], $s['lecHrs'] > 0 ? round($s['lecHrs'], 1) : '', $s['labHrs'] > 0 ? round($s['labHrs'], 1) : '',
              round($s['lecHrs'] + 0.7 * $s['labHrs'], 1), $rm[0], $rm[1]);
        $r++;
    }
    for ($i = 0; $i < $blank; $i++) {
        $rm = $remarks[count($subs) + $i + 1] ?? ['', ''];
        $line($r, '', '', '', '', $rm[0], $rm[1]); $r++;
    }
    $line($r, 'Sub-Total', round($w['lec'], 1), round($w['lab'], 1), round($w['comp'], 1), '', '', $Rb, $Sub);
    $sh->cell($r, 5, '', $C, 1, 2); $r++;
    $sh->height($r, 17); $sh->cell($r, 1, 'Regular load (max ' . (int)$w['allowed'] . ' hrs)', $Rt); $sh->cell($r, 2, '', $C); $sh->cell($r, 3, '', $C);
    $sh->cell($r, 4, round($FL['regular'], 1), $C); $sh->cell($r, 5, 'counted in the ' . FACULTY_WEEK_HOURS . ' hrs', $L, 1, 2); $r++;

    $sh->height($r, 17);
    $sh->cell($r, 1, 'B. Other Functions:', $Lb);
    for ($i = 2; $i <= 4; $i++) $sh->cell($r, $i, '', $C);
    $sh->cell($r, 5, 'Total Number of Students:', $Lb, 1, 2); $r++;
    foreach (['Research', 'Extension', 'IMP', 'Consultation'] as $o) {
        $sh->height($r, 17); $sh->cell($r, 1, $o, $L);
        for ($i = 2; $i <= 4; $i++) $sh->cell($r, $i, '', $C);
        $sh->cell($r, 5, '', $C, 1, 2); $r++;
    }
    $sh->height($r, 17); $sh->cell($r, 1, 'Sub-Total', $Rb); $sh->cell($r, 2, '', $Sub); $sh->cell($r, 3, '', $Sub); $sh->cell($r, 4, round($FL['other'], 1), $Sub); $sh->cell($r, 5, 'fills the ' . FACULTY_WEEK_HOURS . '-hr week', $L, 1, 2); $r++;

    $sh->height($r, 17); $sh->cell($r, 1, 'C. Designation', $Lb);
    for ($i = 2; $i <= 4; $i++) $sh->cell($r, $i, '', $C);
    $sh->cell($r, 5, '', $C, 1, 2); $r++;
    $shown = array_values($designations);
    while (count($shown) < 2) $shown[] = null;
    foreach ($shown as $dg) {
        $sh->height($r, 17);
        if ($dg) { $sh->cell($r, 1, (string)$dg['name'], $L); $sh->cell($r, 2, (float)$dg['hours'], $C); $sh->cell($r, 3, '', $C); $sh->cell($r, 4, (float)$dg['hours'], $C); }
        else { $sh->cell($r, 1, '', $L); for ($i = 2; $i <= 4; $i++) $sh->cell($r, $i, '', $C); }
        $sh->cell($r, 5, '', $C, 1, 2); $r++;
    }
    $sh->height($r, 17); $sh->cell($r, 1, 'Sub-Total', $Rb); $sh->cell($r, 2, '', $Sub); $sh->cell($r, 3, '', $Sub); $sh->cell($r, 4, round($desHrs, 1), $Sub); $sh->cell($r, 5, '', $C, 1, 2); $r++;
    $sh->height($r, 18); $sh->cell($r, 1, 'Grand Total (A+B+C)', $Lb, 1, 3); $sh->cell($r, 4, round($FL['weekTotal'], 1), $Cb);
    $sh->cell($r, 5, $FL['over40'] > 0 ? 'OVER ' . FACULTY_WEEK_HOURS . ' hrs by ' . nf($FL['over40']) : 'of ' . FACULTY_WEEK_HOURS . ' hrs', $Lb, 1, 2); $r += 2;

    // ---- Claim for Teaching Overload: approved 1-hour sessions, total session hours, teaching service credit ----
    $approved = array_values(array_filter($sessions, fn($x) => $x['status'] === 'Approved'));
    $cl = [['Teaching Overload (total - regular):', nf($w['overload'])], ['Whole hours to claim:', nf($w['claim'])], ['Teaching Service Credit (under 1 hr):', nf($w['credit'])]];
    $sh->height($r, 18); $sh->cell($r, 1, 'CLAIM FOR TEACHING OVERLOAD', $H, 1, 6); $r++;
    $sh->height($r, 17); $sh->cell($r, 1, 'Day', $H); $sh->cell($r, 2, 'Time', $H, 1, 2); $sh->cell($r, 4, 'Hours', $H); $sh->cell($r, 5, 'Overload computation', $H, 1, 2); $r++;
    for ($i = 0, $n = max(4, count($approved)); $i < $n; $i++) {
        $x = $approved[$i] ?? null; $cc = $cl[$i] ?? ['', ''];
        $sh->height($r, 17);
        $sh->cell($r, 1, $x ? (string)$x['day_of_week'] : '', $L);
        $sh->cell($r, 2, $x ? date('g:i A', strtotime($x['start_time'])) . ' - ' . date('g:i A', strtotime($x['end_time'])) : '', $C, 1, 2);
        $sh->cell($r, 4, $x ? round(session_hours($x), 1) : '', $C);
        $sh->cell($r, 5, $cc[0], $L); $sh->cell($r, 6, xl_num($cc[1]), $Rt); $r++;
    }
    $sh->height($r, 17); $sh->cell($r, 1, 'Total session hours (approved)', $Rb, 1, 3); $sh->cell($r, 4, round($FL['sessApproved'], 1), $Sub);
    $sh->cell($r, 5, $FL['sessPending'] > 0 ? nf($FL['sessPending']) . ' hr(s) waiting for the chair' : '', $L, 1, 2); $r += 2;

    $sh->height($r, 18);
    $sh->cell($r, 1, 'ACTION TAKEN', $H); $sh->cell($r, 2, 'NAME', $H, 1, 2); $sh->cell($r, 4, 'POSITION', $H, 1, 2); $sh->cell($r, 6, 'SIGNATURE', $H); $r++;
    foreach ([['Prepared by:', (string)$inst['faculty_name'], 'Faculty'], ['Reviewed by:', '', 'Chair'], ['Verified by:', '', 'Dean, COTE'], ['Approved by:', '', 'VP-Academic Affairs']] as $row) {
        $sh->height($r, 24);
        $sh->cell($r, 1, $row[0], $L); $sh->cell($r, 2, $row[1], $C, 1, 2); $sh->cell($r, 4, $row[2], $C, 1, 2); $sh->cell($r, 6, '', $C); $r++;
    }
    $sh->cell($r + 1, 1, 'Effective:', ['sz' => 10, 'ha' => 'left']);
    return $sh;
}

/* ====================== 2. STUDENT'S CLASS PROGRAM (per section) ====================== */
function xl_section(XlsxBook $book, string $section, array $entries, array $courses): XlsxSheet {
    $c = pcfg();
    $sh = $book->addSheet('Sec - ' . section_label($section));
    foreach ([5, 22, 17, 17, 17, 17, 17] as $i => $wd) $sh->width($i + 1, $wd);

    $r = xl_header($sh, 1, 1, 5, 2, "STUDENT'S CLASS PROGRAM S.Y. " . $c['sy'], 'section') + 1;
    xl_info_row($sh, $r,     2, 'COURSE:', $c['program'], 'YEAR & SECTION:', section_label($section), true, true);
    xl_info_row($sh, $r + 1, 2, 'DEPARTMENT:', strtoupper($c['department']), 'TERM:', $c['term'], true, false);
    $r = xl_timetable($sh, $r + 3, 1, $entries, fn($e) => [$e['course_code'], short_name($e['faculty_name']), $e['room_name']], true, '8FBC7A');

    $hrs = [];
    foreach ($entries as $e) $hrs[$e['course_id']] = ($hrs[$e['course_id']] ?? 0) + (timeToMin($e['end_time']) - timeToMin($e['start_time'])) / 60;

    $sh->cell($r + 1, 2, 'SUBJECTS FOR LOADING:', ['sz' => 10, 'b' => 1, 'ha' => 'left'], 1, 3);
    $r += 2;
    $H  = ['sz' => 10, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'D9EAD3'];
    $L  = ['sz' => 10, 'ha' => 'left', 'bd' => XL_BORDER];
    $Lb = ['sz' => 10, 'b' => 1, 'ha' => 'left', 'bd' => XL_BORDER];
    $C  = ['sz' => 10, 'ha' => 'center', 'bd' => XL_BORDER];
    $sh->height($r, 18);
    $sh->cell($r, 2, 'Subject Code', $H); $sh->cell($r, 3, 'Subject Description', $H, 1, 3); $sh->cell($r, 6, 'Units', $H); $sh->cell($r, 7, 'Hours / week', $H);
    $r++;
    foreach ($courses as $co) {
        $sh->height($r, 17);
        $sh->cell($r, 2, (string)$co['course_code'], $Lb); $sh->cell($r, 3, (string)$co['course_name'], $L, 1, 3); $sh->cell($r, 6, xl_num((string)$co['units']), $C);
        $sh->cell($r, 7, isset($hrs[$co['course_id']]) ? round($hrs[$co['course_id']], 1) : 'not yet scheduled', $C);
        $r++;
    }
    if (!$courses) $sh->cell($r, 2, 'No open courses for this year level.', ['sz' => 10, 'i' => 1, 'ha' => 'left', 'bd' => XL_BORDER], 1, 6);
    return $sh;
}

/* ====================== 3. ROOM UTILIZATION (landscape long bond, two rooms per page) ====================== */
function xl_rooms(XlsxBook $book, array $rooms, array $allEntries): XlsxSheet {
    $c = pcfg();
    $sh = $book->addSheet('Room Utilization');
    $sh->landscape = true; $sh->fitHeight = 0;
    foreach ([14, 14, 14, 14, 14, 14, 3, 14, 14, 14, 14, 14, 14] as $i => $wd) $sh->width($i + 1, $wd);

    $r = xl_header($sh, 1, 1, 10, 3, 'ROOM UTILIZATION', 'room', strtoupper($c['term']) . ' ' . $c['sy']) + 1;
    if (!$rooms) { $sh->cell($r, 1, 'No rooms found.', ['sz' => 10, 'i' => 1, 'ha' => 'left']); return $sh; }
    $pairs = array_chunk($rooms, 2);
    foreach ($pairs as $pi => $pair) {
        $end = $r;
        foreach ($pair as $k => $room) {
            $c0 = 1 + $k * 7;
            $mine = array_values(array_filter($allEntries, fn($e) => (int)$e['room_id'] === (int)$room['room_id']));
            $hours = 0; foreach ($mine as $e) $hours += (timeToMin($e['end_time']) - timeToMin($e['start_time'])) / 60;
            $pct = min(100, round($hours / 60 * 100));
            $sh->height($r, 22); $sh->height($r + 1, 14);
            $sh->cell($r, $c0, strtoupper((string)$room['room_name']), ['sz' => 14, 'b' => 1, 'ha' => 'center'], 1, 6);
            $sh->cell($r + 1, $c0, ($room['room_code'] !== '' ? 'Room ' . $room['room_code'] . ' · ' : '') . 'Capacity ' . $room['capacity'] . ' · ' . nf($hours) . ' of 60 hrs used (' . $pct . '%)',
                      ['sz' => 8, 'ha' => 'center'], 1, 6);
            $end = xl_timetable($sh, $r + 2, $c0, $mine, fn($e) => [$e['course_code'], $e['section'], short_name($e['faculty_name'])], false, '8FBC7A', 16, 7);
        }
        $r = $end;
        if ($pi < count($pairs) - 1) { $sh->breaks[] = $r - 1; $r++; }          // next pair starts on a new page
    }
    return $sh;
}

/* ====================== 4. FACULTY WORKLOAD MATRIX ====================== */
function xl_matrix(XlsxBook $book, array $faculty, array $entries): XlsxSheet {
    $c = pcfg();
    $sh = $book->addSheet('Faculty Workload Matrix'); $sh->fitHeight = 0;
    foreach ([30, 13, 13, 11, 10, 10, 10, 10, 10] as $i => $wd) $sh->width($i + 1, $wd);

    $r = xl_header($sh, 1, 1, 6, 3, 'FACULTY WORKLOAD MATRIX', 'matrix', strtoupper($c['term']) . ' ' . $c['sy']) + 1;
    $by = []; foreach ($entries as $e) $by[(int)($e['faculty_id'] ?? 0)][] = $e;
    $blocks = [];
    foreach ($faculty as $f) $blocks[] = ['name' => $f['faculty_name'], 'w' => workload_summary($by[(int)$f['faculty_id']] ?? [])];
    if (!empty($by[0])) $blocks[] = ['name' => 'UNASSIGNED CLASSES', 'w' => workload_summary($by[0])];

    $H    = ['sz' => 9, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'EEF1F8', 'wrap' => 1];
    $L    = ['sz' => 9, 'ha' => 'left', 'bd' => XL_BORDER];
    $C    = ['sz' => 9, 'ha' => 'center', 'bd' => XL_BORDER];
    $Red  = ['sz' => 9, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'col' => 'CC0000'];
    $Tot  = ['sz' => 9, 'b' => 1, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'F7F8FC'];
    $TotL = ['sz' => 9, 'b' => 1, 'ha' => 'left', 'bd' => XL_BORDER, 'fill' => 'F7F8FC'];
    $FH   = ['sz' => 9, 'b' => 1, 'ha' => 'left', 'bd' => XL_BORDER, 'fill' => 'FFF7E0'];
    $FC   = ['sz' => 9, 'ha' => 'center', 'bd' => XL_BORDER, 'fill' => 'FFF7E0'];

    $sh->cell($r, 1, 'SUMMARY', ['sz' => 10, 'b' => 1, 'ha' => 'left']); $r++;
    $sh->height($r, 40);
    foreach (['Faculty', 'No. of Prep.', 'Total Units', 'Lec Hrs', 'Lab Hrs', 'Comp: lec+lab (×0.7)', 'Regular load', 'Overload'] as $i => $t) $sh->cell($r, 1 + $i, $t, $H);
    $r++;
    foreach ($blocks as $b) {
        $w = $b['w'];
        $sh->height($r, 16); $sh->cell($r, 1, (string)$b['name'], $L);
        $vals = [$w['preps'], (float)$w['units'], round($w['lec'], 1), round($w['lab'], 1), $w['comp'], $w['preps'] ? $w['allowed'] : '—', $w['preps'] ? $w['overload'] : '—'];
        foreach ($vals as $i => $v) $sh->cell($r, 2 + $i, $v, ($i === 6 && $w['overload'] > 0 && $w['preps']) ? $Red : $C);
        $r++;
    }
    $sh->cell($r, 1, 'Regular load: 2 preparations = 21 · 3–4 preparations = 18 · 5 or more = 15. Lab hours count at 0.7.', ['sz' => 8, 'i' => 1, 'ha' => 'left'], 1, 9);
    $r += 2;

    foreach ($blocks as $b) {
        $w = $b['w'];
        $sh->height($r, 17);
        $sh->cell($r, 1, 'Faculty: ' . $b['name'], $FH, 1, 4); $sh->cell($r, 5, 'Unit / subject', $FC, 1, 2); $sh->cell($r, 7, 'No. of hrs. / subject', $FC, 1, 3); $r++;
        $sh->height($r, 28);
        foreach (['Subject', 'Room (Lab)', 'Room (Lec)', '# of Sections', 'Lec', 'Lab', 'Lec', 'Lab', 'Lab EQ'] as $i => $t) $sh->cell($r, 1 + $i, $t, $H);
        $r++;
        foreach ($w['rows'] as $i => $x) {
            $sh->height($r, 16);
            $sh->cell($r, 1, ($i + 1) . '. ' . $x['code'] . ' (' . $x['section'] . ')', $L);
            $sh->cell($r, 2, $x['lab'] ? $x['room'] : '', $C); $sh->cell($r, 3, $x['lab'] ? '' : $x['room'], $C); $sh->cell($r, 4, 1, $C);
            $sh->cell($r, 5, $x['lab'] ? '' : (float)$x['units'], $C); $sh->cell($r, 6, $x['lab'] ? (float)$x['units'] : '', $C);
            $sh->cell($r, 7, $x['lab'] ? '' : round($x['hrs'], 1), $C); $sh->cell($r, 8, $x['lab'] ? round($x['hrs'], 1) : '', $C); $sh->cell($r, 9, $x['lab'] ? (float)$x['eq'] : '', $C);
            $r++;
        }
        if (!$w['rows']) { $sh->cell($r, 1, 'No classes assigned.', ['sz' => 9, 'i' => 1, 'ha' => 'left', 'bd' => XL_BORDER], 1, 9); $r++; }
        $sh->height($r, 16);
        $sh->cell($r, 1, 'No. of Prep. ' . $w['preps'], $TotL); $sh->cell($r, 2, '', $Tot); $sh->cell($r, 3, '', $Tot); $sh->cell($r, 4, count($w['rows']), $Tot);
        $sh->cell($r, 5, (float)$w['unitsLec'], $Tot); $sh->cell($r, 6, (float)$w['unitsLab'], $Tot);
        $sh->cell($r, 7, round($w['lec'], 1), $Tot); $sh->cell($r, 8, round($w['lab'], 1), $Tot); $sh->cell($r, 9, round($w['lab'] * 0.7, 1), $Tot);
        $r++;
        $sh->height($r, 16);
        $sh->cell($r, 1, 'Total Units: ' . nf($w['units']) . '  ·  Total Hrs.: ' . nf($w['lec'] + $w['lab']), $TotL, 1, 4);
        $sh->cell($r, 5, 'Comp: lec+lab (×0.7) = ' . nf($w['comp']), $Tot, 1, 3);
        $sh->cell($r, 8, 'Overload: ' . ($w['preps'] ? nf($w['overload']) : '—'), ($w['overload'] > 0 && $w['preps']) ? $Red : $Tot, 1, 2);
        $r += 2;
    }
    return $sh;
}

/* ====================== whole workbook ====================== */

/** Build one workbook holding the requested forms. $types: any of 'section', 'instructor', 'room', 'matrix'. */
function xl_build_workbook(mysqli $conn, array $types): XlsxBook {
    $book = new XlsxBook();
    $faculty = $conn->query("SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);

    if (in_array('section', $types, true)) {
        $sections = array_column($conn->query("SELECT DISTINCT section FROM schedules WHERE section IS NOT NULL AND section <> '' ORDER BY section")->fetch_all(MYSQLI_ASSOC), 'section');
        foreach ($sections as $section) {
            $entries = load_program_entries($conn, 'section', $section);
            $yr = $entries ? (int)$entries[0]['year_level'] : (preg_match('/(\d)[A-Za-z]?$/', $section, $m) ? (int)$m[1] : 0);
            $st = $conn->prepare("SELECT course_id, course_code, course_name, units FROM courses WHERE year_level = ? AND status = 'Open' ORDER BY course_code");
            $st->bind_param('i', $yr); $st->execute();
            xl_section($book, $section, $entries, $st->get_result()->fetch_all(MYSQLI_ASSOC));
        }
    }
    if (in_array('instructor', $types, true)) {
        foreach ($faculty as $f) {
            $id = (int)$f['faculty_id'];
            xl_instructor($book, $f, load_program_entries($conn, 'faculty', $id), load_designations($conn, $id), load_overload_sessions($conn, $id));
        }
    }
    if (in_array('room', $types, true)) {
        $rooms = $conn->query("SELECT room_id, room_code, room_name, capacity FROM rooms ORDER BY room_name")->fetch_all(MYSQLI_ASSOC);
        xl_rooms($book, $rooms, load_program_entries($conn));
    }
    if (in_array('matrix', $types, true)) {
        xl_matrix($book, $faculty, load_program_entries($conn));
    }
    return $book;
}
