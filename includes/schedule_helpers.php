<?php
/** Helpers used by the faculty portal. */

const FAC_DAYS     = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
const FAC_DAY_KEYS = ['Mon','Tue','Wed','Thu','Fri'];

function timeToMin(string $t): int {
    [$h, $m] = array_map('intval', explode(':', $t));
    return $h * 60 + $m;
}
function minToLabel(int $m): string {
    $h = intdiv($m, 60); $mm = $m % 60;
    return (($h % 12) ?: 12) . ':' . str_pad((string)$mm, 2, '0', STR_PAD_LEFT) . ' ' . ($h >= 12 ? 'PM' : 'AM');
}
function rangeLabel(string $start, string $end): string {
    return minToLabel(timeToMin($start)) . ' – ' . minToLabel(timeToMin($end));
}

/** ['Mon','Tue','Wed','Fri'] -> "Mon–Wed, Fri" */
function fmtDaysCsv(?string $csv): string {
    $picked = array_values(array_intersect(FAC_DAY_KEYS, array_filter(explode(',', (string)$csv))));
    if (!$picked) return 'Not set';
    $idx = array_map(fn($d) => array_search($d, FAC_DAY_KEYS), $picked);
    $runs = []; $start = $prev = $idx[0];
    foreach (array_slice($idx, 1) as $i) {
        if ($i === $prev + 1) { $prev = $i; continue; }
        $runs[] = [$start, $prev]; $start = $prev = $i;
    }
    $runs[] = [$start, $prev];
    return implode(', ', array_map(function ($r) {
        [$a, $b] = $r;
        if ($a === $b) return FAC_DAY_KEYS[$a];
        return $b - $a === 1 ? FAC_DAY_KEYS[$a] . ', ' . FAC_DAY_KEYS[$b] : FAC_DAY_KEYS[$a] . '–' . FAC_DAY_KEYS[$b];
    }, $runs));
}

/** One instructor's classes. The DB stores 30-minute chunks; back-to-back chunks of the same class are merged. */
function loadFacultySchedule(mysqli $c, int $facultyId): array {
    $stmt = $c->prepare(
        "SELECT s.schedule_id, s.course_id, s.room_id, s.section, s.day_of_week, s.start_time, s.end_time, s.cell_color,
                c.course_code, c.course_name, c.units, r.room_name
         FROM schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN rooms r   ON r.room_id   = s.room_id
         WHERE s.faculty_id = ?
         ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.start_time");
    $stmt->bind_param('i', $facultyId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $merged = [];
    foreach ($rows as $r) {
        $last = count($merged) - 1;
        if ($last >= 0) {
            $p = $merged[$last];
            if ($p['day_of_week'] === $r['day_of_week'] && $p['course_id'] === $r['course_id']
                && $p['room_id'] === $r['room_id'] && $p['section'] === $r['section']
                && $p['end_time'] === $r['start_time']) {
                $merged[$last]['end_time'] = $r['end_time'];
                continue;
            }
        }
        $merged[] = $r;
    }
    return $merged;
}

function scheduleHours(array $entries): float {
    $min = 0;
    foreach ($entries as $e) $min += timeToMin($e['end_time']) - timeToMin($e['start_time']);
    return round($min / 60, 1);
}

/** True when a class sits outside the days / hours the instructor said they can teach. */
function outsideAvailability(array $e, array $inst): bool {
    $days = array_filter(explode(',', (string)($inst['available_days'] ?? '')));
    if ($days && !in_array(substr($e['day_of_week'], 0, 3), $days, true)) return true;
    if (!empty($inst['available_from']) && !empty($inst['available_to'])) {
        if (timeToMin($e['start_time']) < timeToMin($inst['available_from'])) return true;
        if (timeToMin($e['end_time'])   > timeToMin($inst['available_to']))   return true;
    }
    return false;
}

function ordinal(int $n): string { return $n . ($n === 1 ? 'st' : ($n === 2 ? 'nd' : ($n === 3 ? 'rd' : 'th'))); }

/** The signed-in instructor's own row. If an admin deleted them mid-session, sign them out. */
function loadInstructor(mysqli $c, int $id): array {
    $stmt = $c->prepare("SELECT faculty_id, username, faculty_name, specialty, available_days, available_from, available_to FROM faculty WHERE faculty_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { logoutUser(); header('Location: ' . appUrl('login.php')); exit; }
    return $row;
}
