<?php
/** Helpers used by the faculty portal and schedule change requests. */

require_once __DIR__ . '/notify.php';

if (!defined('FAC_DAYS')) {
    define('FAC_DAYS', ['Monday','Tuesday','Wednesday','Thursday','Friday']);
}
if (!defined('FAC_DAY_KEYS')) {
    define('FAC_DAY_KEYS', ['Mon','Tue','Wed','Thu','Fri']);
}

if (!function_exists('timeToMin')) {
    function timeToMin(string $t): int {
        [$h, $m] = array_map('intval', explode(':', $t));
        return $h * 60 + $m;
    }
}

if (!function_exists('minToLabel')) {
    function minToLabel(int $m): string {
        $h = intdiv($m, 60); $mm = $m % 60;
        return (($h % 12) ?: 12) . ':' . str_pad((string)$mm, 2, '0', STR_PAD_LEFT) . ' ' . ($h >= 12 ? 'PM' : 'AM');
    }
}

if (!function_exists('rangeLabel')) {
    function rangeLabel(string $start, string $end): string {
        return minToLabel(timeToMin($start)) . ' – ' . minToLabel(timeToMin($end));
    }
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

            if (
                $p['day_of_week'] === $r['day_of_week'] &&
                $p['course_id'] == $r['course_id'] &&
                $p['room_id'] == $r['room_id'] &&
                $p['section'] === $r['section'] &&
                $p['end_time'] === $r['start_time']
            ) {
                $merged[$last]['end_time'] = $r['end_time'];
                $merged[$last]['schedule_ids'][] = (int)$r['schedule_id'];
                continue;
            }
        }

        $r['schedule_ids'] = [(int)$r['schedule_id']];
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


/** Adds faculty.photo (file name of the instructor's uploaded face photo) the first time it is needed. */
function ensure_faculty_photo_column(mysqli $c): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if ($c->query("SHOW COLUMNS FROM faculty LIKE 'photo'")->num_rows === 0) {
            $c->query("ALTER TABLE faculty ADD COLUMN photo varchar(120) DEFAULT NULL");
        }
    } catch (Throwable $e) { /* leave it; the page simply shows initials */ }
}

/** Folder (on disk) and URL prefix for instructor photos. */
function faculty_photo_dir(): string { return dirname(__DIR__) . '/uploads/faculty'; }

/** URL (relative to a page one folder deep, e.g. /faculty/) of an instructor's photo, or '' when none. */
function faculty_photo_url(?string $file): string {
    $file = basename((string)$file);
    if ($file === '' || !is_file(faculty_photo_dir() . '/' . $file)) return '';
    return '../uploads/faculty/' . rawurlencode($file) . '?v=' . filemtime(faculty_photo_dir() . '/' . $file);
}

/**
 * Validates and stores an uploaded face photo (JPG/PNG/WEBP, max 2 MB). The image is re-encoded as a square JPEG
 * (so nothing but pixels is ever stored), named randomly. Returns the new file name; throws RuntimeException with a user message.
 */
function save_faculty_photo(array $f, int $facultyId): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a photo first.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('The photo is too large. Maximum is 2 MB.');
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('The upload failed. Please try again.');
    $info = @getimagesize($f['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $exts    = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($exts[$info[2]])) throw new RuntimeException('Please upload a JPG, PNG or WEBP image.');
    $dir = faculty_photo_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the uploads folder.');
    $base = 'f' . $facultyId . '_' . bin2hex(random_bytes(6));

    /* Preferred: re-encode as a 400 px square JPEG with GD (strips anything that is not pixels). */
    $canResize = function_exists('imagecreatetruecolor') && function_exists('imagejpeg')
              && isset($loaders[$info[2]]) && function_exists($loaders[$info[2]]);
    if ($canResize) {
        $src = @($loaders[$info[2]])($f['tmp_name']);
        if ($src) {
            [$w, $h] = [imagesx($src), imagesy($src)];
            $side = min($w, $h);
            $dst = imagecreatetruecolor(400, 400);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 400, 400, $side, $side);
            $name = $base . '.jpg';
            if (imagejpeg($dst, $dir . '/' . $name, 88)) return $name;
        }
    }

    /* Fallback when PHP GD is not enabled: the file was already verified as a real image by getimagesize(),
       so keep it as-is under a random name (the browser crops it to a circle/square when displaying). */
    $name = $base . '.' . $exts[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the photo.');
    return $name;
}

function delete_faculty_photo_file(?string $file): void {
    $file = basename((string)$file);
    if ($file !== '' && is_file(faculty_photo_dir() . '/' . $file)) @unlink(faculty_photo_dir() . '/' . $file);
}

/** The signed-in instructor's own row. If an admin deleted them mid-session, sign them out. */
function loadInstructor(mysqli $c, int $id): array {
    ensure_faculty_photo_column($c);
    $stmt = $c->prepare("SELECT faculty_id, username, faculty_name, specialty, available_days, available_from, available_to, photo FROM faculty WHERE faculty_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { logoutUser(); header('Location: ' . appUrl('login.php')); exit; }
    return $row;
}

function ensure_schedule_change_schema(mysqli $c): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $c->query("CREATE TABLE IF NOT EXISTS schedule_change_requests (
        request_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        schedule_ids VARCHAR(255) NOT NULL,
        faculty_id INT NOT NULL,
        old_day VARCHAR(20) NOT NULL,
        old_start TIME NOT NULL,
        old_end TIME NOT NULL,
        new_day VARCHAR(20) NOT NULL,
        new_start TIME NOT NULL,
        new_end TIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        faculty_note VARCHAR(200) NULL DEFAULT NULL,
        decision_note VARCHAR(200) NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_scr_status (status, created_at),
        KEY idx_scr_faculty (faculty_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
}

function schedule_block_rows(mysqli $c, int $scheduleId, int $facultyId): array {
    $st = $c->prepare("SELECT * FROM schedules WHERE schedule_id = ? AND faculty_id = ? LIMIT 1");
    $st->bind_param('ii', $scheduleId, $facultyId);
    $st->execute();
    $base = $st->get_result()->fetch_assoc();
    if (!$base) return [];

    $day      = (string)$base['day_of_week'];
    $start    = (string)$base['start_time'];
    $end      = (string)$base['end_time'];
    $sec      = (string)($base['section'] ?? '');
    $courseId = (int)($base['course_id'] ?? 0);
    $roomId   = (int)($base['room_id'] ?? 0);

    $q = $c->prepare("SELECT * FROM schedules
                      WHERE faculty_id = ?
                        AND day_of_week = ?
                        AND section = ?
                        AND course_id = ?
                        AND room_id = ?
                        AND start_time >= ?
                        AND end_time <= ?
                      ORDER BY start_time ASC, schedule_id ASC");
    $q->bind_param('issiiss', $facultyId, $day, $sec, $courseId, $roomId, $start, $end);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    return $rows ?: [$base];
}

function schedule_change_conflict(mysqli $c, array $baseRow, string $newDay, string $newStart, string $newEnd, array $ignoreIds = []): ?string {
    $sMin = timeToMin($newStart);
    $eMin = timeToMin($newEnd);
    if ($eMin <= $sMin) {
        return 'Requested end time must be after start time.';
    }
    if ($sMin < 7 * 60 || $eMin > 19 * 60) {
        return 'Requested time must stay within 7:00 AM to 7:00 PM.';
    }
    if ($sMin < 12 * 60 && $eMin > 12 * 60) {
        return 'Requested time cannot cross the 12:00 PM - 1:00 PM lunch break.';
    }

    $facultyId = (int)($baseRow['faculty_id'] ?? 0);
    $roomId    = (int)($baseRow['room_id'] ?? 0);

    $st = $c->prepare("SELECT schedule_id, faculty_id, room_id, start_time, end_time FROM schedules WHERE day_of_week = ?");
    $st->bind_param('s', $newDay);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $r) {
        $rid = (int)$r['schedule_id'];
        if (in_array($rid, $ignoreIds, true)) continue;

        $rStart = timeToMin((string)$r['start_time']);
        $rEnd = timeToMin((string)$r['end_time']);
        if ($sMin < $rEnd && $eMin > $rStart) {
            if ((int)$r['faculty_id'] === $facultyId) {
                return 'You already have a class during ' . $newDay . ' ' . rangeLabel($newStart, $newEnd) . '.';
            }
            if ($roomId > 0 && (int)$r['room_id'] === $roomId) {
                return 'That room is already occupied during ' . $newDay . ' ' . rangeLabel($newStart, $newEnd) . '.';
            }
        }
    }

    return null;
}

function decide_schedule_change(mysqli $c, int $requestId, bool $approve, string $note = ''): ?string {
    ensure_schedule_change_schema($c);
    ensure_approval_schema($c);

    $c->begin_transaction();
    try {
        $st = $c->prepare("SELECT * FROM schedule_change_requests WHERE request_id = ? AND status = 'Pending' FOR UPDATE");
        $st->bind_param('i', $requestId);
        $st->execute();
        $req = $st->get_result()->fetch_assoc();
        if (!$req) {
            $c->rollback();
            return 'This schedule change request was already decided or does not exist.';
        }

        $ids = json_decode((string)$req['schedule_ids'], true);
        if (!is_array($ids) || !$ids) {
            $ids = [(int)$req['schedule_id']];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) {
            $c->rollback();
            return 'Schedule block IDs are missing.';
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $q = $c->prepare("SELECT * FROM schedules WHERE schedule_id IN ($placeholders) ORDER BY start_time ASC, schedule_id ASC");
        $q->bind_param($types, ...$ids);
        $q->execute();
        $blockRows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$blockRows) {
            $c->rollback();
            return 'Target schedule rows were not found.';
        }

        $status = $approve ? 'Approved' : 'Rejected';
        $noteVal = $note !== '' ? $note : null;

        if ($approve) {
            $newDayStr   = (string)$req['new_day'];
            $newStartStr = (string)$req['new_start'];
            $newEndStr   = (string)$req['new_end'];

            $conflict = schedule_change_conflict(
                $c,
                $blockRows[0],
                $newDayStr,
                $newStartStr,
                $newEndStr,
                $ids
            );
            if ($conflict !== null) {
                $c->rollback();
                return $conflict;
            }

            $newStartMin = timeToMin($newStartStr);
            $newEndMin   = timeToMin($newEndStr);
            $slots = [];
            for ($m = $newStartMin; $m < $newEndMin; $m += 30) {
                $slotEnd = min($newEndMin, $m + 30);
                $slots[] = [
                    sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60),
                    sprintf('%02d:%02d:00', intdiv($slotEnd, 60), $slotEnd % 60),
                ];
            }

            $base = $blockRows[0];
            $courseId  = (int)$base['course_id'];
            $facultyId = $base['faculty_id'] !== null ? (int)$base['faculty_id'] : null;
            $roomId    = (int)$base['room_id'];
            $section   = (string)$base['section'];
            $cellColor = (string)($base['cell_color'] ?? '#ffb100');
            $hasClassType = array_key_exists('class_type', $base);
            $classType = (string)($base['class_type'] ?? 'Lecture');

            $upd = $c->prepare("UPDATE schedules SET day_of_week = ?, start_time = ?, end_time = ? WHERE schedule_id = ?");
            $del = $c->prepare("DELETE FROM schedules WHERE schedule_id = ?");

            $sharedCount = min(count($blockRows), count($slots));
            for ($i = 0; $i < $sharedCount; $i++) {
                $bid = (int)$blockRows[$i]['schedule_id'];
                [$sTime, $eTime] = $slots[$i];
                $upd->bind_param('sssi', $newDayStr, $sTime, $eTime, $bid);
                $upd->execute();
            }

            if (count($blockRows) > count($slots)) {
                for ($i = count($slots); $i < count($blockRows); $i++) {
                    $bid = (int)$blockRows[$i]['schedule_id'];
                    $del->bind_param('i', $bid);
                    $del->execute();
                }
            } elseif (count($slots) > count($blockRows)) {
                if ($hasClassType) {
                    $ins = $c->prepare("INSERT INTO schedules (course_id, faculty_id, room_id, section, class_type, day_of_week, start_time, end_time, cell_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                } else {
                    $ins = $c->prepare("INSERT INTO schedules (course_id, faculty_id, room_id, section, day_of_week, start_time, end_time, cell_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                }
                for ($i = count($blockRows); $i < count($slots); $i++) {
                    [$sTime, $eTime] = $slots[$i];
                    if ($hasClassType) {
                        $ins->bind_param('iiissssss', $courseId, $facultyId, $roomId, $section, $classType, $newDayStr, $sTime, $eTime, $cellColor);
                    } else {
                        $ins->bind_param('iiisssss', $courseId, $facultyId, $roomId, $section, $newDayStr, $sTime, $eTime, $cellColor);
                    }
                    $ins->execute();
                }
            }
        }

        $doneSt = $c->prepare("UPDATE schedule_change_requests
                              SET status = ?, decision_note = ?, decided_at = CURRENT_TIMESTAMP
                              WHERE request_id = ? AND status = 'Pending'");
        $doneSt->bind_param('ssi', $status, $noteVal, $requestId);
        $doneSt->execute();

        $fromStr = $req['old_day'] . ' ' . rangeLabel((string)$req['old_start'], (string)$req['old_end']);
        $toStr = $req['new_day'] . ' ' . rangeLabel((string)$req['new_start'], (string)$req['new_end']);
        $noteTxt = $note !== '' ? ' Note from the chair: ' . $note : '';
        $msg = 'The chair ' . ($approve ? 'approved' : 'rejected') . ' moving your class from ' . $fromStr . ' to ' . $toStr . '.' . $noteTxt;
        notify($c, 'faculty', (int)$req['faculty_id'], $approve ? 'approved' : 'rejected', $msg, 'schedule.php');

        $c->commit();
        return null;
    } catch (Throwable $e) {
        $c->rollback();
        return 'Could not process the schedule change decision.';
    }
}

function ensure_other_workload_schema(mysqli $c): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $c->query("CREATE TABLE IF NOT EXISTS faculty_other_workload (
            faculty_id INT NOT NULL PRIMARY KEY,
            research_hrs DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            extension_hrs DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            imp_hrs DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            consultation_hrs DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            extra_designation_hrs DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            extra_designation_label VARCHAR(120) NOT NULL DEFAULT '',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    } catch (Throwable $e) {}
}

function load_other_workload(mysqli $c, int $facultyId): array {
    ensure_other_workload_schema($c);
    $defaults = [
        'research_hrs' => 0.0,
        'extension_hrs' => 0.0,
        'imp_hrs' => 0.0,
        'consultation_hrs' => 0.0,
        'extra_designation_hrs' => 0.0,
        'extra_designation_label' => '',
    ];
    if ($facultyId <= 0) return $defaults;

    try {
        $st = $c->prepare("SELECT research_hrs, extension_hrs, imp_hrs, consultation_hrs, extra_designation_hrs, extra_designation_label
                           FROM faculty_other_workload WHERE faculty_id = ? LIMIT 1");
        if (!$st) return $defaults;
        $st->bind_param('i', $facultyId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!$row) return $defaults;
        return [
            'research_hrs' => (float)$row['research_hrs'],
            'extension_hrs' => (float)$row['extension_hrs'],
            'imp_hrs' => (float)$row['imp_hrs'],
            'consultation_hrs' => (float)$row['consultation_hrs'],
            'extra_designation_hrs' => (float)$row['extra_designation_hrs'],
            'extra_designation_label' => (string)$row['extra_designation_label'],
        ];
    } catch (Throwable $e) {
        return $defaults;
    }
}