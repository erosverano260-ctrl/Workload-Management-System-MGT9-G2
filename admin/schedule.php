<?php
$page = 'schedule';
$title = 'Class Schedule';

require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

$db = new database();
$conn = $db->connect();

$DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

/** True if another class already uses $col ('room_id' or 'faculty_id') = $val on that day, overlapping [start,end). */
function hasOverlap(mysqli $c, string $col, int $val, string $day, string $start, string $end, int $ignoreId): bool {
    if (!in_array($col, ['room_id', 'faculty_id'], true)) return false;   // $col is never user input
    $stmt = $c->prepare("SELECT 1 FROM schedules WHERE $col = ? AND day_of_week = ? AND start_time < ? AND end_time > ? AND schedule_id <> ? LIMIT 1");
    $stmt->bind_param('isssi', $val, $day, $end, $start, $ignoreId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

/** True if this SECTION already has a class overlapping [start,end) that day (a section can't be in two places). */
function hasSectionOverlap(mysqli $c, ?string $section, string $day, string $start, string $end, int $ignoreId): bool {
    if ($section === null || $section === '') return false;
    $stmt = $c->prepare("SELECT 1 FROM schedules WHERE section = ? AND day_of_week = ? AND start_time < ? AND end_time > ? AND schedule_id <> ? LIMIT 1");
    $stmt->bind_param('ssssi', $section, $day, $end, $start, $ignoreId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

/* ---------- AJAX: drag-and-drop move / resize. Always answers JSON, never redirects. ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_workload') {
    header('Content-Type: application/json');
    $fail = function (string $m) { echo json_encode(['ok' => false, 'error' => $m]); exit; };
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) $fail('Your session expired. Refresh the page and try again.');

    $id = (int)($_POST['schedule_id'] ?? 0);
    $day = $_POST['day_of_week'] ?? ''; $start = $_POST['start_time'] ?? ''; $end = $_POST['end_time'] ?? '';
    $okTime = preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)
              && $start < $end && $start >= '07:00:00' && $end <= '19:00:00';
    if (!in_array($day, $DAYS, true) || !$okTime) $fail('That time is not allowed.');

    try {
        $stmt = $conn->prepare("SELECT room_id, faculty_id, section FROM schedules WHERE schedule_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) $fail('That class no longer exists. Refresh the page.');

        if (hasOverlap($conn, 'room_id', (int)$row['room_id'], $day, $start, $end, $id)) $fail('That room is already booked during this time.');
        if ($row['faculty_id'] !== null && hasOverlap($conn, 'faculty_id', (int)$row['faculty_id'], $day, $start, $end, $id)) $fail('That instructor already has a class during this time.');
        if (hasSectionOverlap($conn, $row['section'], $day, $start, $end, $id)) $fail('That section already has a class during this time.');

        $stmt = $conn->prepare("UPDATE schedules SET day_of_week=?, start_time=?, end_time=? WHERE schedule_id=?");
        $stmt->bind_param('sssi', $day, $start, $end, $id);
        $stmt->execute();
        echo json_encode(['ok' => true]);
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Could not save that change.']);
    }
    exit;
}

/* ---------- POST actions: save / delete (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_workload') {
            $scheduleId = (int)($_POST['schedule_id'] ?? 0);                    // 0 = new
            $courseId   = (int)($_POST['course_id'] ?? 0);
            $roomId     = (int)($_POST['room_id'] ?? 0);
            $facultyId  = ($_POST['faculty_id'] ?? '') !== '' ? (int)$_POST['faculty_id'] : null;   // optional instructor
            $section    = post_str('section');
            $day        = $_POST['day_of_week'] ?? '';
            $start      = $_POST['start_time'] ?? '';
            $end        = $_POST['end_time'] ?? '';
            $color      = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cell_color'] ?? '') ? $_POST['cell_color'] : '#67e0a3';

            $timeOk = preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end) && $start < $end;
            if (!in_array($day, $DAYS, true) || !$timeOk) {
                flash_set('error', 'Pick a weekday and an end time that is after the start time.'); redirect('schedule.php');
            }
            if ($courseId < 1 || $roomId < 1 || $section === '' || strlen($section) > 20) {
                flash_set('error', 'Please fill in course, section and room.'); redirect('schedule.php');
            }
            // Server-side conflict checks (the browser checks too, but this is the real guard)
            if (hasOverlap($conn, 'room_id', $roomId, $day, $start, $end, $scheduleId)) {
                flash_set('error', 'That room is already booked during this time.'); redirect('schedule.php');
            }
            if ($facultyId !== null && hasOverlap($conn, 'faculty_id', $facultyId, $day, $start, $end, $scheduleId)) {
                flash_set('error', 'That instructor already has a class during this time.'); redirect('schedule.php');
            }
            if (hasSectionOverlap($conn, $section, $day, $start, $end, $scheduleId)) {
                flash_set('error', 'That section already has a class during this time.'); redirect('schedule.php');
            }

            if ($scheduleId > 0) {
                $stmt = $conn->prepare("UPDATE schedules SET course_id=?, faculty_id=?, room_id=?, section=?, day_of_week=?, start_time=?, end_time=?, cell_color=? WHERE schedule_id=?");
                $stmt->bind_param("iiisssssi", $courseId, $facultyId, $roomId, $section, $day, $start, $end, $color, $scheduleId);
                $msg = 'Workload updated.';
            } else {
                $stmt = $conn->prepare("INSERT INTO schedules (course_id, faculty_id, room_id, section, day_of_week, start_time, end_time, cell_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisssss", $courseId, $facultyId, $roomId, $section, $day, $start, $end, $color);
                $msg = 'Workload created.';
            }
            $stmt->execute();
            flash_set('success', $msg);
        } elseif ($action === 'rename_sections') {
            $conn->query("UPDATE schedules SET section = REPLACE(section, 'BSIT-', 'BSEE-') WHERE section LIKE 'BSIT-%'");
            flash_set('success', 'Renamed ' . $conn->affected_rows . ' saved class(es) from BSIT to BSEE sections.');
        } elseif ($action === 'delete_workload') {
            $id = (int)($_POST['schedule_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            flash_set('success', 'Workload deleted.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'workload'));
    }
    redirect('schedule.php');
}

require '../includes/header.php';
$flash = flash_get();

/* ---------- Instructors for the dropdown (empty list if the migration hasn't run) ---------- */
try {
    $faculty = $conn->query("SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) { $faculty = []; }

/* ---------- Load all scheduled workloads, grouped by day ---------- */
$sql = "SELECT s.*, c.course_code, c.course_name, c.year_level, r.room_name, f.faculty_name
        FROM schedules s
        JOIN courses c ON c.course_id = s.course_id
        JOIN rooms r   ON r.room_id   = s.room_id
        LEFT JOIN faculty f ON f.faculty_id = s.faculty_id
        ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.start_time";
$allEntries = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$coursesOpen = $conn->query("SELECT course_id, course_code, course_name, year_level, units FROM courses WHERE status='Open' ORDER BY course_code")->fetch_all(MYSQLI_ASSOC);
$roomsList   = $conn->query("SELECT room_id, room_name FROM rooms ORDER BY room_name")->fetch_all(MYSQLI_ASSOC);

$byDay = array_fill_keys($DAYS, []);
foreach ($allEntries as $e) {
    if (isset($byDay[$e['day_of_week']])) $byDay[$e['day_of_week']][] = $e;
}
?>

<link rel="stylesheet" href="<?= asset('ui.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<link rel="stylesheet" href="<?= asset('scheduler.css') ?>">
<style>.schedule-toolbar-row { display: flex; gap: 10px; }</style>

<section class="page-section">
<div class="section-heading no-print">
    <div><span class="eyebrow">SCHEDULE MANAGER</span><h2>Drag-and-drop class schedule</h2></div>
    <div class="schedule-toolbar-row">
        <button class="primary-btn" id="openCreateWorkload" type="button">+ Create Workload</button>
        <button class="primary-btn print-btn" type="button" onclick="window.print()">🖨 Print this view</button>
    </div>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?> no-print"><?= h($flash['msg']) ?></div><?php endif; ?>

<?php $oldSections = (int)$conn->query("SELECT COUNT(*) AS n FROM schedules WHERE section LIKE 'BSIT-%'")->fetch_assoc()['n']; ?>
<?php if ($oldSections): ?>
<form method="post" class="ui-notice no-print" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <?= csrf_field() ?><input type="hidden" name="action" value="rename_sections">
    <span><strong><?= $oldSections ?> saved class<?= $oldSections === 1 ? '' : 'es' ?></strong> still use the old <code>BSIT-</code> section names, so they print as BSIT.</span>
    <button type="submit" class="primary-btn">Rename them to BSEE</button>
</form>
<?php endif; ?>

<div class="view-bar no-print">
    <label>View by
        <select id="viewBy"><option value="section">Section</option><option value="faculty">Instructor</option><option value="room">Room</option></select>
    </label>
    <select id="viewVal" aria-label="Which one"></select>
    <button type="button" class="view-toggle" id="poolToggle" aria-expanded="true">☰ Class pool</button>
    <span class="hint">Drag a class to move it · drag its bottom edge to resize · click to edit</span>
</div>

<div class="sched-layout">
  <aside class="pool no-print" id="pool"></aside>

  <div class="board-wrap" id="printableSchedule">
    <div class="print-title" id="printTitle"></div>
    <div class="board" id="board">
      <div class="b-head"></div>
      <?php foreach ($DAYS as $d): ?><div class="b-head"><?= h($d) ?></div><?php endforeach; ?>

      <div class="gutter">
        <?php for ($hr = 7; $hr <= 19; $hr++): ?><span style="top:<?= ($hr - 7) * 56 ?>px"><?= ($hr % 12 ?: 12) . ($hr >= 12 ? ' PM' : ' AM') ?></span><?php endfor; ?>
      </div>

      <?php foreach ($byDay as $day => $entries): ?>
      <div class="day-col" data-day="<?= h($day) ?>">
        <div class="lunch">LUNCH</div>
        <?php foreach ($entries as $e): ?>
        <div class="blk" tabindex="0" role="button" title="Drag to move · drag bottom edge to resize · click to edit"
             data-schedule-id="<?= h($e['schedule_id']) ?>" data-course-id="<?= h($e['course_id']) ?>" data-room-id="<?= h($e['room_id']) ?>"
             data-faculty-id="<?= h($e['faculty_id']) ?>" data-day="<?= h($e['day_of_week']) ?>"
             data-start="<?= h($e['start_time']) ?>" data-end="<?= h($e['end_time']) ?>" data-color="<?= h($e['cell_color']) ?>"
             data-year="<?= h($e['year_level']) ?>" data-section="<?= h($e['section']) ?>"
             data-course="<?= h($e['course_code']) ?>" data-name="<?= h($e['course_name']) ?>" data-room="<?= h($e['room_name']) ?>"
             style="--c:<?= h($e['cell_color'] ?: '#67e0a3') ?>">
          <strong><?= h($e['course_code']) ?></strong>
          <small class="t"></small>
          <small><?= h($e['course_name']) ?></small>
          <small>⌂ <?= h($e['room_name']) ?><?php if ($e['faculty_name']): ?> · ♙ <?= h($e['faculty_name']) ?><?php endif; ?></small>
          <small><?= h($e['section']) ?></small>
          <span class="rz"></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</section>
<div class="toast" id="toast" role="status"></div>


<!-- ============================================= -->
<!-- CREATE / EDIT WORKLOAD MODAL -->
<!-- ============================================= -->
<div class="ui-modal" id="scheduleModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="ui-modal-card" id="modalCard">
    <header class="ui-modal-head">
      <span class="ui-modal-icon">◷</span>
      <div>
        <h2 id="modalTitle">Create workload</h2>
        <p id="modalSub">Pick a day and time, then the class details.</p>
      </div>
      <button type="button" class="ui-modal-x" data-close-modal aria-label="Close">×</button>
    </header>

    <!-- live preview: shows the class the way it will look on the schedule -->
    <div class="ui-ticket" id="ticket">
        <div><strong id="tkTitle">New workload</strong><span id="tkWhen">Choose a day and time</span><span id="tkWhere"></span></div>
    </div>

    <div class="ui-form">
      <div class="ui-grid">
        <div class="ui-field span2">
            <label class="ui-label" for="dayPicker">Day</label>
            <select id="dayPicker">
                <option value="">Select day</option>
                <?php foreach ($DAYS as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
            </select>
        </div>

        <div class="ui-field">
            <label class="ui-label" for="startTime">Start time</label>
            <select id="startTime"><option value="">Select start time</option></select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="endTime">End time</label>
            <select id="endTime"><option value="">Select start time first</option></select>
        </div>

        <div class="ui-field">
            <label class="ui-label" for="yearLevel">Year level</label>
            <select id="yearLevel">
                <option value="">Select year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="section">Section</label>
            <select id="section">
                <option value="">Select year level first</option>
                <?php for ($y = 1; $y <= 4; $y++): foreach (['A','B'] as $L): ?>
                <option value="BSEE-<?= $y.$L ?>" data-year="<?= $y ?>">BSEE-<?= $y.$L ?></option>
                <?php endforeach; endfor; ?>
            </select>
        </div>

        <div class="ui-field span2">
            <label class="ui-label" for="courseSelect">Course</label>
            <select id="courseSelect"><option value="">Select year level first</option></select>
        </div>

        <div class="ui-field">
            <label class="ui-label" for="room">Room</label>
            <select id="room"><option value="">Loading...</option></select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="instructor">Instructor</label>
            <select id="instructor">
                <option value="">Unassigned</option>
                <?php foreach ($faculty as $f): ?><option value="<?= (int)$f['faculty_id'] ?>"><?= h($f['faculty_name']) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div class="ui-field span2">
            <label class="ui-label" for="cellColor">Cell color</label>
            <input type="color" id="cellColor" value="#67e0a3">
        </div>
      </div>

      <div class="ui-error" id="formError" role="alert"></div>

      <div class="ui-actions">
        <button type="button" class="ui-btn danger push-left" id="deleteBtn" style="display:none">Delete</button>
        <button type="button" class="ui-btn" id="cancelBtn">Cancel</button>
        <button type="button" class="ui-btn primary" id="saveBtn">Save workload</button>
      </div>
    </div>
  </div>
</div>


<script>
/* ================= Elements ================= */
const openCreateWorkload = document.getElementById('openCreateWorkload');
const modal = document.getElementById("scheduleModal");
const modalCard = document.getElementById("modalCard");
const modalTitle = document.getElementById("modalTitle");
const modalSub = document.getElementById("modalSub");
const saveButton = document.getElementById("saveBtn");
const cancelButton = document.getElementById("cancelBtn");
const deleteButton = document.getElementById("deleteBtn");
const dayPicker = document.getElementById("dayPicker");
const startTimeSelect = document.getElementById("startTime");
const endTimeSelect = document.getElementById("endTime");
const yearLevelSelect = document.getElementById("yearLevel");
const courseSelect = document.getElementById("courseSelect");
const sectionSelect = document.getElementById("section");
const roomSelect = document.getElementById("room");
const instructorSelect = document.getElementById("instructor");
const colorInput = document.getElementById("cellColor");
const formError = document.getElementById("formError");

const CSRF = <?= json_encode(csrf_token()) ?>;
let editingId = null; // null = creating a new workload; a value = editing that schedule_id
let roomBookings = [];

/* ================= Inline error (replaces alert) ================= */
function showError(msg) {
    formError.textContent = msg;
    formError.classList.remove('show');
    void formError.offsetWidth;          // restart the shake animation
    formError.classList.add('show');
}
function clearError() { formError.classList.remove('show'); }

/* ================= Live preview ================= */
function optText(sel) { const o = sel.options[sel.selectedIndex]; return o && o.value ? o.textContent : ''; }
function slotLabel(db) { const s = TIME_SLOTS.find(t => t.db === db); return s ? s.label : ''; }

function refreshTicket() {
    const stripe = colorInput.value;
    modalCard.style.setProperty('--stripe', stripe);
    document.getElementById('tkTitle').textContent = optText(courseSelect) || 'New workload';
    const when = [dayPicker.value, startTimeSelect.value && endTimeSelect.value ? slotLabel(startTimeSelect.value) + ' – ' + slotLabel(endTimeSelect.value) : '']
        .filter(Boolean).join(' · ');
    document.getElementById('tkWhen').textContent = when || 'Choose a day and time';
    document.getElementById('tkWhere').textContent =
        [sectionSelect.value, optText(roomSelect), instructorSelect.value ? optText(instructorSelect) : ''].filter(Boolean).join(' · ');
}
[dayPicker, startTimeSelect, endTimeSelect, courseSelect, sectionSelect, roomSelect, instructorSelect, colorInput].forEach(el => {
    el.addEventListener('change', () => { clearError(); refreshTicket(); });
    el.addEventListener('input', refreshTicket);
});

/* ================= Modal open/close ================= */
function resetModalForCreate() {
    editingId = null;
    roomBookings = [];
    modalTitle.textContent = "Create workload";
    modalSub.textContent = "Pick a day and time, then the class details.";
    saveButton.textContent = "Save workload";
    deleteButton.style.display = "none";
    dayPicker.value = "";
    yearLevelSelect.value = "";
    roomSelect.value = "";
    instructorSelect.value = "";
    colorInput.value = "#67e0a3";
    courseSelect.innerHTML = '<option value="">Select year level first</option>';
    filterSections("");
    sectionSelect.querySelector('option[value=""]').textContent = 'Select year level first';
    sectionSelect.value = "";
    rebuildTimeOptions();
    clearError();
    refreshTicket();
}

openCreateWorkload.addEventListener('click', () => {
    resetModalForCreate();
    UI.open(modal);
});
cancelButton.addEventListener('click', () => UI.close(modal));

/* ================= Time dropdowns ================= */
const TIME_SLOTS = [
    { label: "7:00 AM",  db: "07:00:00" }, { label: "7:30 AM",  db: "07:30:00" },
    { label: "8:00 AM",  db: "08:00:00" }, { label: "8:30 AM",  db: "08:30:00" },
    { label: "9:00 AM",  db: "09:00:00" }, { label: "9:30 AM",  db: "09:30:00" },
    { label: "10:00 AM", db: "10:00:00" }, { label: "10:30 AM", db: "10:30:00" },
    { label: "11:00 AM", db: "11:00:00" }, { label: "11:30 AM", db: "11:30:00" },
    { label: "12:00 PM", db: "12:00:00" }, { label: "1:00 PM",  db: "13:00:00" },
    { label: "1:30 PM",  db: "13:30:00" }, { label: "2:00 PM",  db: "14:00:00" },
    { label: "2:30 PM",  db: "14:30:00" }, { label: "3:00 PM",  db: "15:00:00" },
    { label: "3:30 PM",  db: "15:30:00" }, { label: "4:00 PM",  db: "16:00:00" },
    { label: "4:30 PM",  db: "16:30:00" }, { label: "5:00 PM",  db: "17:00:00" },
    { label: "5:30 PM",  db: "17:30:00" }, { label: "6:00 PM",  db: "18:00:00" },
    { label: "6:30 PM",  db: "18:30:00" }, { label: "7:00 PM",  db: "19:00:00" }
];

startTimeSelect.addEventListener("change", function () {
    const checkedDays = dayPicker.value ? [dayPicker.value] : [];
    const startIndex = TIME_SLOTS.findIndex(s => s.db === this.value);
    endTimeSelect.innerHTML = '<option value="">Select End Time</option>';
    if (startIndex < 0) { endTimeSelect.innerHTML = '<option value="">Select start time first</option>'; return; }

    for (let i = startIndex + 1; i < TIME_SLOTS.length; i++) {
        if (isSlotBlocked(i, checkedDays)) break;
        const opt = document.createElement("option");
        opt.value = TIME_SLOTS[i].db;
        opt.textContent = TIME_SLOTS[i].label;
        endTimeSelect.appendChild(opt);
    }
});

/* ================= Populate Room dropdown ================= */
const roomsLoaded = fetch('../data/get_rooms.php')
    .then(res => res.json())
    .then(list => {
        roomSelect.innerHTML = '<option value="">Select Room</option>';
        list.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.room_id;
            opt.textContent = r.room_name;
            roomSelect.appendChild(opt);
        });
    })
    .catch(() => { roomSelect.innerHTML = '<option value="">Failed to load rooms</option>'; });

/* ================= Fetch existing bookings when Room changes ================= */
roomSelect.addEventListener('change', function () {
    const roomId = this.value;
    if (!roomId) { roomBookings = []; rebuildTimeOptions(); return; }

    fetch(`../data/get_booked_times.php?room_id=${roomId}`)
        .then(res => res.json())
        .then(data => { roomBookings = data; rebuildTimeOptions(); })
        .catch(() => { roomBookings = []; });
});

// While editing, the class's own existing booking must not block itself.
function isSlotBlocked(index, checkedDays) {
    return roomBookings.some(b => {
        if (editingId !== null && String(b.schedule_id) === String(editingId)) return false;
        if (!checkedDays.includes(b.day_of_week)) return false;
        const bookedStart = TIME_SLOTS.findIndex(s => s.db === b.start_time);
        const bookedEnd = TIME_SLOTS.findIndex(s => s.db === b.end_time);
        if (bookedStart === -1 || bookedEnd === -1) return false;
        return index >= bookedStart && index < bookedEnd;
    });
}

function rebuildTimeOptions() {
    const checkedDays = dayPicker.value ? [dayPicker.value] : [];
    const previousValue = startTimeSelect.value;
    const previousEnd = endTimeSelect.value;

    startTimeSelect.innerHTML = '<option value="">Select Start Time</option>';
    TIME_SLOTS.forEach((slot, i) => {
        if (i === TIME_SLOTS.length - 1) return;
        if (isSlotBlocked(i, checkedDays)) return;
        const opt = document.createElement("option");
        opt.value = slot.db;
        opt.textContent = slot.label;
        startTimeSelect.appendChild(opt);
    });

    if ([...startTimeSelect.options].some(o => o.value === previousValue && o.value)) {
        startTimeSelect.value = previousValue;
        startTimeSelect.dispatchEvent(new Event('change'));
        if (previousEnd && [...endTimeSelect.options].some(o => o.value === previousEnd)) endTimeSelect.value = previousEnd;
    } else {
        endTimeSelect.innerHTML = '<option value="">Select start time first</option>';
    }
}

dayPicker.addEventListener('change', rebuildTimeOptions);

/* ================= Year Level -> Course + Section filtering ================= */
function filterSections(year) {
    // hidden + disabled so it also works in Safari, which ignores display:none on <option>
    sectionSelect.querySelectorAll('option').forEach(opt => {
        if (!opt.value) return;
        const show = opt.dataset.year === year;
        opt.hidden = !show;
        opt.disabled = !show;
    });
}

function loadCoursesForYear(year, preselectCourseId) {
    if (!year) {
        courseSelect.innerHTML = '<option value="">Select year level first</option>';
        return Promise.resolve();
    }
    courseSelect.innerHTML = '<option value="">Loading...</option>';
    return fetch(`../data/get_courses.php?year_level=${year}`)
        .then(res => res.json())
        .then(courses => {
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            courses.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.course_id;
                opt.textContent = `${c.course_code} - ${c.course_name}`;
                courseSelect.appendChild(opt);
            });
            if (preselectCourseId) courseSelect.value = preselectCourseId;
        })
        .catch(() => { courseSelect.innerHTML = '<option value="">Failed to load courses</option>'; });
}

yearLevelSelect.addEventListener("change", function () {
    const year = this.value;
    filterSections(year);
    sectionSelect.value = '';
    sectionSelect.querySelector('option[value=""]').textContent = year ? 'Select Section' : 'Select year level first';
    loadCoursesForYear(year).then(refreshTicket);
});

/* ================= Save (create or edit) ================= */
saveButton.addEventListener("click", function () {
    const day = dayPicker.value;
    const startTime = startTimeSelect.value;
    const endTime = endTimeSelect.value;
    const courseId = courseSelect.value;
    const section = sectionSelect.value;
    const roomId = roomSelect.value;
    const facultyId = instructorSelect.value;       // optional
    const cellColor = colorInput.value;

    if (!day || !startTime || !endTime || !courseId || !section || !roomId) {
        showError("Please fill in day, times, course, section and room.");
        return;
    }

    const startIndex = TIME_SLOTS.findIndex(s => s.db === startTime);
    const endIndex = TIME_SLOTS.findIndex(s => s.db === endTime);
    const conflict = roomBookings.some(b => {
        if (editingId !== null && String(b.schedule_id) === String(editingId)) return false;
        if (b.day_of_week !== day) return false;
        const bookedStart = TIME_SLOTS.findIndex(s => s.db === b.start_time);
        const bookedEnd = TIME_SLOTS.findIndex(s => s.db === b.end_time);
        return startIndex < bookedEnd && endIndex > bookedStart; // any overlap at all
    });

    if (conflict) {
        showError("That room is already booked during this time. Choose another time or room.");
        return;
    }

    saveButton.disabled = true;
    saveButton.textContent = "Saving…";

    const form = document.createElement('form');
    form.method = 'POST';
    const fields = {
        csrf_token: CSRF, action: 'save_workload', schedule_id: editingId ?? '', course_id: courseId, room_id: roomId,
        faculty_id: facultyId, section: section, day_of_week: day, start_time: startTime,
        end_time: endTime, cell_color: cellColor
    };
    Object.entries(fields).forEach(([k, v]) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = k; input.value = v;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
});

/* ================= Delete ================= */
deleteButton.addEventListener("click", function () {
    if (!editingId) return;
    UI.confirm("Delete this class from the schedule? This can't be undone.").then(ok => {
        if (!ok) return;
        const f = document.createElement('form');
        f.method = 'POST';
        [['csrf_token', CSRF], ['action', 'delete_workload'], ['schedule_id', editingId]].forEach(([k, v]) => {
            const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i);
        });
        document.body.appendChild(f);
        f.submit();
    });
});

/* ================= Card click -> open modal pre-filled for editing ================= */
function openEdit(card) {
    const d = card.dataset;
    resetModalForCreate();                       // start from a clean slate
    editingId = d.scheduleId;
    modalTitle.textContent = "Edit workload";
    modalSub.textContent = d.course + " · " + d.section;
    saveButton.textContent = "Save changes";
    deleteButton.style.display = "";

    dayPicker.value = d.day;
    colorInput.value = d.color || "#67e0a3";
    instructorSelect.value = d.facultyId || "";
    yearLevelSelect.value = d.year;
    filterSections(d.year);
    sectionSelect.querySelector('option[value=""]').textContent = 'Select Section';
    sectionSelect.value = d.section;
    refreshTicket();
    UI.open(modal);

    // The room list and its bookings must be loaded before the time dropdowns can be built correctly.
    roomsLoaded
        .then(() => { roomSelect.value = d.roomId; return fetch(`../data/get_booked_times.php?room_id=${d.roomId}`); })
        .then(res => res.json())
        .then(data => {
            roomBookings = data;
            rebuildTimeOptions();
            startTimeSelect.value = d.start;
            startTimeSelect.dispatchEvent(new Event('change'));
            endTimeSelect.value = d.end;
        })
        .catch(() => { roomBookings = []; })
        .then(() => loadCoursesForYear(d.year, d.courseId))
        .then(() => {
            // get_courses.php only returns *Open* courses; keep a Closed course selectable while editing
            if (d.courseId && courseSelect.value !== String(d.courseId)) {
                courseSelect.add(new Option(`${d.course} - ${d.name}`, d.courseId));
                courseSelect.value = d.courseId;
            }
            refreshTicket();
        });
}

/* ================= Drag-and-drop board ================= */
const DATA = <?= json_encode(['courses' => $coursesOpen, 'rooms' => $roomsList, 'faculty' => $faculty], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const board = document.getElementById('board');
const cols = [...board.querySelectorAll('.day-col')];
const viewBy = document.getElementById('viewBy'), viewVal = document.getElementById('viewVal');
const pool = document.getElementById('pool'), toastEl = document.getElementById('toast');
const ROWH = 28, T0 = 420, TEND = 1140, LUNCH_A = 720, LUNCH_B = 780;   // 7:00 AM … 7:00 PM, 28px per 30 min, lunch 12–1

const blocks  = () => [...board.querySelectorAll('.blk')];
const fromDb  = t => +t.slice(0, 2) * 60 + +t.slice(3, 5);
const toDb    = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0') + ':00';
const fmt     = m => { const h = Math.floor(m / 60); return ((h % 12) || 12) + ':' + String(m % 60).padStart(2, '0') + (h >= 12 ? ' PM' : ' AM'); };
const yOf     = m => (m - T0) / 30 * ROWH;
const rangeOk = (s, e) => e > s && s >= T0 && e <= TEND && (e <= LUNCH_A || s >= LUNCH_B);

function toast(msg, bad) {
    toastEl.textContent = msg; toastEl.className = 'toast show' + (bad ? ' err' : '');
    clearTimeout(toast.t); toast.t = setTimeout(() => toastEl.className = 'toast', 2800);
}
function place(b) {
    const s = fromDb(b.dataset.start), e = fromDb(b.dataset.end);
    b.style.top = yOf(s) + 'px'; b.style.height = (yOf(e) - yOf(s) - 2) + 'px';
    b.querySelector('.t').textContent = fmt(s) + ' – ' + fmt(e);
    b.classList.toggle('s1', e - s <= 30);
    b.classList.toggle('s2', e - s > 30 && e - s <= 60);
    const d = b.dataset;
    b.title = [d.course + ' · ' + fmt(s) + ' – ' + fmt(e), d.name, d.room, d.section].filter(Boolean).join('\n') + '\n(drag to move · drag bottom edge to resize · click to edit)';
}
blocks().forEach(place);

/* any other class using the same room / instructor / section at that time? (server re-checks everything) */
function clash(day, s, e, ignoreId, meta) {
    for (const b of blocks()) {
        const d = b.dataset;
        if (d.scheduleId === String(ignoreId) || d.day !== day) continue;
        if (!(s < fromDb(d.end) && e > fromDb(d.start))) continue;
        if (meta.room    && d.roomId === String(meta.room))       return 'That room is already booked at that time.';
        if (meta.faculty && d.facultyId === String(meta.faculty)) return 'That instructor already has a class then.';
        if (meta.section && d.section === meta.section)           return 'This section already has a class then.';
    }
    return '';
}

/* ---------- "View by" filter + class pool ---------- */
const sectionOpts = [...sectionSelect.options].filter(o => o.value).map(o => ({ v: o.value, t: o.textContent, year: o.dataset.year }));
const sectionYear = s => (sectionOpts.find(o => o.v === s) || {}).year;
const keyOf = b => viewBy.value === 'section' ? b.dataset.section : viewBy.value === 'faculty' ? (b.dataset.facultyId || 'none') : b.dataset.roomId;

function viewOptions() {
    if (viewBy.value === 'section') {
        const extra = [...new Set(blocks().map(b => b.dataset.section))].filter(x => x && !sectionOpts.some(o => o.v === x));
        return sectionOpts.map(o => [o.v, o.t]).concat(extra.map(x => [x, x]));
    }
    if (viewBy.value === 'faculty') return DATA.faculty.map(f => [String(f.faculty_id), f.faculty_name]).concat([['none', 'Unassigned']]);
    return DATA.rooms.map(r => [String(r.room_id), r.room_name]);
}
function applyView(keep) {
    if (!keep) {
        const opts = viewOptions();
        viewVal.innerHTML = '';
        opts.forEach(([v, t]) => viewVal.add(new Option(t, v)));
        const first = opts.find(([v]) => blocks().some(b => keyOf(b) === v));
        if (first) viewVal.value = first[0];
    }
    blocks().forEach(b => b.hidden = keyOf(b) !== viewVal.value);
    const sel = viewVal.options[viewVal.selectedIndex];
    document.getElementById('printTitle').textContent = (sel ? sel.text : '') + ' — Weekly class schedule';
    renderPool();
    try { localStorage.setItem('schedView', JSON.stringify([viewBy.value, viewVal.value])); } catch (e) {}
}
function renderPool() {
    pool.innerHTML = '';
    const h = document.createElement('h3'), p = document.createElement('p');
    pool.append(h, p);
    if (viewBy.value !== 'section') {
        h.textContent = 'Class pool';
        p.textContent = 'Switch "View by" to Section to drag new classes onto the week. You can still move and resize classes in this view.';
        return;
    }
    const sec = viewVal.value, yr = sectionYear(sec);
    h.textContent = (sec || 'Section') + ' · class pool';
    const list = DATA.courses.filter(c => String(c.year_level) === String(yr));
    p.textContent = list.length ? 'Drag a course onto the week.' : 'No open courses for this year level.';
    list.forEach(c => {
        const mins = blocks().filter(b => b.dataset.section === sec && b.dataset.courseId === String(c.course_id))
                             .reduce((a, b) => a + fromDb(b.dataset.end) - fromDb(b.dataset.start), 0);
        const el = document.createElement('div'); el.className = 'chip';
        const a = document.createElement('strong'), n = document.createElement('span'), m = document.createElement('span');
        a.textContent = c.course_code; n.textContent = c.course_name;
        m.textContent = c.units + ' units · ' + (mins / 60) + ' h scheduled';
        el.append(a, n, m);
        el.addEventListener('pointerdown', e => startDrag(e, { type: 'new', course: c, section: sec, el }));
        pool.appendChild(el);
    });
}
viewBy.addEventListener('change', () => applyView());
viewVal.addEventListener('change', () => applyView(true));

/* ---------- Pointer-based drag engine (mouse + touch) ---------- */
let drag = null;
const ghost = document.createElement('div');

function startDrag(e, d) {
    if (e.button !== undefined && e.button !== 0) return;
    e.preventDefault();
    drag = Object.assign({ sx: e.clientX, sy: e.clientY, moved: false, tgt: null, offY: ROWH / 2 }, d);
    if (d.type !== 'new') {
        drag.offY = e.clientY - d.el.getBoundingClientRect().top;
        drag.s = fromDb(d.el.dataset.start); drag.e = fromDb(d.el.dataset.end); drag.dur = drag.e - drag.s;
    }
    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', endDrag);
}
function endDrag() {
    ghost.remove();
    if (drag) { if (drag.float) drag.float.remove(); if (drag.type === 'move') drag.el.classList.remove('is-dragging'); }
    document.body.classList.remove('dragging');
    document.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerup', onUp);
    document.removeEventListener('pointercancel', endDrag);
    drag = null;
}
function colAt(x) { return cols.find(c => { const r = c.getBoundingClientRect(); return x >= r.left && x < r.right; }); }

function target(e) {
    if (drag.type === 'resize') {
        const col = drag.el.parentElement, r = col.getBoundingClientRect();
        let end = T0 + Math.round((e.clientY - r.top) / ROWH) * 30;
        end = Math.max(drag.s + 30, Math.min(end, TEND));
        return { col, day: col.dataset.day, s: drag.s, e: end };
    }
    const col = colAt(e.clientX); if (!col) return null;
    const r = col.getBoundingClientRect();
    let s = T0 + Math.round((e.clientY - r.top - drag.offY) / ROWH) * 30;
    let dur = drag.type === 'new' ? 90 : drag.dur;
    s = Math.max(T0, Math.min(s, TEND - (drag.type === 'new' ? 30 : dur)));
    if (drag.type === 'new') { if (s < LUNCH_A && s + dur > LUNCH_A) dur = LUNCH_A - s; if (s + dur > TEND) dur = TEND - s; }
    return { col, day: col.dataset.day, s, e: s + dur };
}
function verdict(t) {
    if (!t) return 'Drop it on a day column.';
    if (!rangeOk(t.s, t.e)) return 'Classes can\u2019t overlap lunch (12\u20131 PM) or run outside 7 AM\u20137 PM.';
    if (drag.type === 'new') return clash(t.day, t.s, t.e, '', { section: drag.section });
    const d = drag.el.dataset;
    return clash(t.day, t.s, t.e, d.scheduleId, { room: d.roomId, faculty: d.facultyId, section: d.section });
}
function onMove(e) {
    if (!drag) return;
    if (!drag.moved) {
        if (Math.hypot(e.clientX - drag.sx, e.clientY - drag.sy) < 5) return;
        drag.moved = true; document.body.classList.add('dragging');
        if (drag.type === 'move') drag.el.classList.add('is-dragging');
        if (drag.type === 'new') { drag.float = document.createElement('div'); drag.float.className = 'float'; drag.float.textContent = drag.course.course_code; document.body.appendChild(drag.float); }
    }
    if (drag.float) { drag.float.style.left = (e.clientX + 12) + 'px'; drag.float.style.top = (e.clientY + 12) + 'px'; }
    const t = target(e); drag.tgt = t;
    if (!t) { ghost.remove(); return; }
    t.msg = verdict(t);
    t.col.appendChild(ghost);
    ghost.className = 'ghost' + (t.msg ? ' bad' : '');
    ghost.style.top = yOf(t.s) + 'px'; ghost.style.height = (yOf(t.e) - yOf(t.s) - 2) + 'px';
    ghost.textContent = fmt(t.s) + ' \u2013 ' + fmt(t.e);
}
function onUp() {
    const d = drag; endDrag();
    if (!d) return;
    if (!d.moved) { if (d.type === 'move') openEdit(d.el); return; }       // a plain click opens the edit form
    const t = d.tgt;
    if (!t) return;
    if (t.msg) { toast(t.msg, true); return; }
    if (d.type === 'new') { openCreateFromDrop(t, d); return; }
    if (t.day === d.el.dataset.day && t.s === d.s && t.e === d.e) return;
    saveMove(d.el, t);
}

function saveMove(el, t) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF); fd.append('action', 'move_workload'); fd.append('schedule_id', el.dataset.scheduleId);
    fd.append('day_of_week', t.day); fd.append('start_time', toDb(t.s)); fd.append('end_time', toDb(t.e));
    el.classList.add('saving');
    fetch('schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            el.classList.remove('saving');
            if (!res.ok) { toast(res.error || 'Could not move that class.', true); return; }
            el.dataset.day = t.day; el.dataset.start = toDb(t.s); el.dataset.end = toDb(t.e);
            t.col.appendChild(el); place(el); renderPool(); toast('Saved');
        })
        .catch(() => { el.classList.remove('saving'); toast('Network error \u2014 nothing was changed.', true); });
}

/* Dropping a course from the pool opens the normal form, pre-filled; room + instructor are picked there. */
function openCreateFromDrop(t, d) {
    resetModalForCreate();
    const year = sectionYear(d.section) || '';
    dayPicker.value = t.day;
    yearLevelSelect.value = year;
    filterSections(String(year));
    sectionSelect.querySelector('option[value=""]').textContent = 'Select Section';
    sectionSelect.value = d.section;
    rebuildTimeOptions();
    startTimeSelect.value = toDb(t.s);
    startTimeSelect.dispatchEvent(new Event('change'));
    endTimeSelect.value = toDb(t.e);
    refreshTicket();
    UI.open(modal);
    loadCoursesForYear(year, d.course.course_id).then(refreshTicket);
}

blocks().forEach(b => {
    b.addEventListener('pointerdown', e => startDrag(e, { type: 'move', el: b }));
    b.querySelector('.rz').addEventListener('pointerdown', e => { e.stopPropagation(); startDrag(e, { type: 'resize', el: b }); });
    b.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openEdit(b); } });
});

/* class pool: show / hide (remembered) */
const layoutEl = document.querySelector('.sched-layout'), poolBtn = document.getElementById('poolToggle');
function setPool(hidden) { layoutEl.classList.toggle('pool-hidden', hidden); poolBtn.setAttribute('aria-expanded', String(!hidden)); try { localStorage.setItem('poolHidden', hidden ? '1' : '0'); } catch (e) {} }
poolBtn.addEventListener('click', () => setPool(!layoutEl.classList.contains('pool-hidden')));
try { if (localStorage.getItem('poolHidden') === '1') setPool(true); } catch (e) {}

/* restore the last view */
let saved = null; try { saved = JSON.parse(localStorage.getItem('schedView')); } catch (e) {}
if (saved && ['section', 'faculty', 'room'].includes(saved[0])) viewBy.value = saved[0];
applyView();
if (saved && [...viewVal.options].some(o => o.value === saved[1])) { viewVal.value = saved[1]; applyView(true); }

refreshTicket();
</script>

<?php require '../includes/footer.php'; ?>
