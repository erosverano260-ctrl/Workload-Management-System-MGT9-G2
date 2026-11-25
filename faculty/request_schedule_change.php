<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('faculty');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$conn = (new database())->connect();
ensure_schedule_change_schema($conn);
ensure_approval_schema($conn);

/* Add new_room_id column if not yet present */
try {
    $colRes = $conn->query("SHOW COLUMNS FROM `schedule_change_requests` LIKE 'new_room_id'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE `schedule_change_requests` ADD COLUMN `new_room_id` INT NULL DEFAULT NULL AFTER `new_end`");
    }
} catch (Throwable $e) {}

$inst = loadInstructor($conn, currentUserId());
$facultyId = (int)($inst['faculty_id'] ?? 0);
if ($facultyId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Instructor profile not found.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$items = $payload['requests'] ?? [];
if (!is_array($items) || !$items) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No schedule changes were submitted.']);
    exit;
}

$conn->begin_transaction();
try {
    $hasNewRoomCol = false;
    if ($cCheck = $conn->query("SHOW COLUMNS FROM `schedule_change_requests` LIKE 'new_room_id'")) {
        $hasNewRoomCol = $cCheck->num_rows > 0;
    }

    if ($hasNewRoomCol) {
        $ins = $conn->prepare("
            INSERT INTO schedule_change_requests
                (schedule_id, schedule_ids, faculty_id, old_day, old_start, old_end, new_day, new_start, new_end, new_room_id, status, faculty_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ");
    } else {
        $ins = $conn->prepare("
            INSERT INTO schedule_change_requests
                (schedule_id, schedule_ids, faculty_id, old_day, old_start, old_end, new_day, new_start, new_end, status, faculty_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ");
    }

    $created = 0;
    foreach ($items as $item) {
        $scheduleId = (int)($item['schedule_id'] ?? 0);
        if ($scheduleId <= 0) continue;

        $blockRows = schedule_block_rows($conn, $scheduleId, $facultyId);
        if (!$blockRows) {
            throw new RuntimeException('Assigned class block could not be verified.');
        }

        $ids = array_values(array_unique(array_map(fn($r) => (int)$r['schedule_id'], $blockRows)));
        $idsJson = json_encode($ids);

        $oldDay    = (string)$blockRows[0]['day_of_week'];
        $oldStart  = (string)$blockRows[0]['start_time'];
        $oldEnd    = (string)$blockRows[count($blockRows) - 1]['end_time'];
        $oldRoomId = (int)($blockRows[0]['room_id'] ?? 0);

        $newDay      = trim((string)($item['new_day'] ?? ''));
        $newStart    = trim((string)($item['new_start'] ?? ''));
        $newEnd      = trim((string)($item['new_end'] ?? ''));
        $newRoomId   = isset($item['new_room_id']) && (int)$item['new_room_id'] > 0 ? (int)$item['new_room_id'] : $oldRoomId;
        $note        = trim((string)($item['faculty_note'] ?? ''));
        $noteParam   = $note !== '' ? mb_substr(preg_replace('/[^\x20-\x7E]/', '', $note), 0, 200) : null;
        $newRoomParam = $newRoomId > 0 ? $newRoomId : null;

        if (!in_array($newDay, FAC_DAYS, true) || $newStart === '' || $newEnd === '') {
            throw new RuntimeException('Invalid requested day or time.');
        }

        $roomChanged = ($newRoomId > 0 && $newRoomId !== $oldRoomId);
        if ($newDay === $oldDay && $newStart === $oldStart && $newEnd === $oldEnd && !$roomChanged) {
            continue;
        }

        if ($newRoomId > 0) {
            $rCheck = $conn->prepare("SELECT room_id FROM rooms WHERE room_id = ? LIMIT 1");
            $rCheck->bind_param('i', $newRoomId);
            $rCheck->execute();
            if (!$rCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('Selected room does not exist.');
            }
        }

        $sampleForConflict = $blockRows[0];
        if ($newRoomId > 0) {
            $sampleForConflict['room_id'] = $newRoomId;
        }

        $conflict = schedule_change_conflict($conn, $sampleForConflict, $newDay, $newStart, $newEnd, $ids);
        if ($conflict !== null) {
            throw new RuntimeException($conflict);
        }

        if ($hasNewRoomCol) {
            $ins->bind_param('isissssssis', $scheduleId, $idsJson, $facultyId, $oldDay, $oldStart, $oldEnd, $newDay, $newStart, $newEnd, $newRoomParam, $noteParam);
        } else {
            $ins->bind_param('isisssssss', $scheduleId, $idsJson, $facultyId, $oldDay, $oldStart, $oldEnd, $newDay, $newStart, $newEnd, $noteParam);
        }
        $ins->execute();
        $created++;
    }

    if ($created === 0) {
        throw new RuntimeException('No valid schedule changes were found.');
    }

    $msg = ($inst['faculty_name'] ?? 'An instructor') . ' requested ' . $created . ' schedule change(s).';
    if ($res = $conn->query("SELECT admin_id FROM admins")) {
        while ($row = $res->fetch_assoc()) {
            notify($conn, 'admin', (int)$row['admin_id'], 'request', $msg, 'requests.php');
        }
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'created' => $created]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}