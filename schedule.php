<?php
$page = 'schedule';
$title = 'Class Schedule';

require 'includes/db.php';
require 'includes/auth.php';
require 'includes/ui.php';
requireRole('faculty');

$db = new database();
$conn = $db->connect();

$DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

/* ---------- SAVE (create or update) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_workload') {
    $scheduleId = $_POST['schedule_id'] ?? '';
    $facultyId  = ($_POST['faculty_id'] ?? '') !== '' ? (int)$_POST['faculty_id'] : null; // optional instructor

    if (!in_array($_POST['day_of_week'] ?? '', $DAYS, true) || ($_POST['start_time'] ?? '') >= ($_POST['end_time'] ?? '')) {
        flash_set('error', 'Pick a weekday and an end time that is after the start time.');
        redirect('schedule.php');
    }

    try {
        if ($scheduleId !== '') {
            $stmt = $conn->prepare(
                "UPDATE schedules SET course_id=?, faculty_id=?, room_id=?, section=?, day_of_week=?, start_time=?, end_time=?, cell_color=? WHERE schedule_id=?"
            );
            $stmt->bind_param("iiisssssi",
                $_POST['course_id'], $facultyId, $_POST['room_id'], $_POST['section'], $_POST['day_of_week'],
                $_POST['start_time'], $_POST['end_time'], $_POST['cell_color'], $scheduleId);
            $msg = 'Workload updated.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO schedules (course_id, faculty_id, room_id, section, day_of_week, start_time, end_time, cell_color)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiisssss",
                $_POST['course_id'], $facultyId, $_POST['room_id'], $_POST['section'], $_POST['day_of_week'],
                $_POST['start_time'], $_POST['end_time'], $_POST['cell_color']);
            $msg = 'Workload created.';
        }
        $stmt->execute();
        flash_set('success', $msg);
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'workload'));
    }
    redirect('schedule.php');
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    flash_set('success', 'Workload deleted.');
    redirect('schedule.php');
}

require 'includes/header.php';
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

$byDay = array_fill_keys($DAYS, []);
foreach ($allEntries as $e) {
    if (isset($byDay[$e['day_of_week']])) $byDay[$e['day_of_week']][] = $e;
}
?>

<link rel="stylesheet" href="assets/ui.css">
<script src="assets/ui.js"></script>

<style>
    .schedule-toolbar-row { display: flex; gap: 10px; }
    @media print { .schedule-toolbar-row, .ui-modal { display: none !important; } }
</style>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">FACULTY PRINT CENTER</span><h2>Ready-to-print class schedule</h2></div>
    <div class="schedule-toolbar-row">
        <button class="primary-btn" id="openCreateWorkload" type="button">+ Create Workload</button>
        <button class="primary-btn print-btn" type="button" onclick="window.print()">🖨 Print Schedule</button>
    </div>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="printable-schedule" id="printableSchedule">
    <div class="schedule-grid">
    <?php foreach ($byDay as $day => $entries): ?>
        <div class="day-column">
            <div class="day-title"><?= h($day) ?></div>
            <?php foreach ($entries as $e): ?>
            <div class="schedule-item" tabindex="0" role="button" title="Click to edit"
                 data-schedule-id="<?= h($e['schedule_id']) ?>"
                 data-course-id="<?= h($e['course_id']) ?>"
                 data-room-id="<?= h($e['room_id']) ?>"
                 data-faculty-id="<?= h($e['faculty_id']) ?>"
                 data-day="<?= h($e['day_of_week']) ?>"
                 data-start="<?= h($e['start_time']) ?>"
                 data-end="<?= h($e['end_time']) ?>"
                 data-color="<?= h($e['cell_color']) ?>"
                 data-year="<?= h($e['year_level']) ?>"
                 data-section="<?= h($e['section']) ?>"
                 data-course="<?= h($e['course_code']) ?>"
                 data-name="<?= h($e['course_name']) ?>"
                 data-room="<?= h($e['room_name']) ?>"
                 style="border-left:4px solid <?= h($e['cell_color']) ?>">
                <span class="code"><?= h($e['course_code']) ?></span>
                <strong><?= h($e['course_name']) ?></strong>
                <small><?= h(substr($e['start_time'],0,5).' - '.substr($e['end_time'],0,5)) ?></small>
                <small>⌂ <?= h($e['room_name']) ?></small>
                <small>Yr <?= h($e['year_level']) ?> · <?= h($e['section']) ?></small>
                <?php if ($e['faculty_name']): ?><small>♙ <?= h($e['faculty_name']) ?></small><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$entries): ?><div class="no-class">No scheduled class</div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
</section>


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
                <option value="BSIT-<?= $y.$L ?>" data-year="<?= $y ?>">BSIT-<?= $y.$L ?></option>
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
const roomsLoaded = fetch('data/get_rooms.php')
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

    fetch(`data/get_booked_times.php?room_id=${roomId}`)
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
    return fetch(`data/get_courses.php?year_level=${year}`)
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
        action: 'save_workload', schedule_id: editingId ?? '', course_id: courseId, room_id: roomId,
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
        if (ok) window.location.href = `schedule.php?delete=${editingId}`;
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
        .then(() => { roomSelect.value = d.roomId; return fetch(`data/get_booked_times.php?room_id=${d.roomId}`); })
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

document.querySelectorAll('.schedule-item').forEach(card => {
    card.addEventListener('click', () => openEdit(card));
    card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openEdit(card); } });
});

refreshTicket();
</script>

<?php require 'includes/footer.php'; ?>
