<?php
$page = 'schedule';
$title = 'Class Schedule';

require 'includes/db.php';
require 'includes/auth.php';
requireRole('faculty');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$db = new database();
$conn = $db->connect();

// --- SAVE (create or update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_workload') {
    $scheduleId = $_POST['schedule_id'] ?? '';

    if ($scheduleId !== '') {
        // EDIT: update the existing row
        $stmt = $conn->prepare(
            "UPDATE schedules SET course_id=?, room_id=?, section=?, day_of_week=?, start_time=?, end_time=?, cell_color=? WHERE schedule_id=?"
        );
        $stmt->bind_param(
            "iisssssi",
            $_POST['course_id'],
            $_POST['room_id'],
            $_POST['section'],
            $_POST['day_of_week'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['cell_color'],
            $scheduleId
        );
    } else {
        // CREATE: insert a new row
        $stmt = $conn->prepare(
            "INSERT INTO schedules (course_id, room_id, section, day_of_week, start_time, end_time, cell_color)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "iisssss",
            $_POST['course_id'],
            $_POST['room_id'],
            $_POST['section'],
            $_POST['day_of_week'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['cell_color']
        );
    }
    $stmt->execute();
    header('Location: schedule.php');
    exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    header('Location: schedule.php');
    exit;
}

require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/faculty_schedule.css">

<style>
    .schedule-toolbar-row {
        display: flex;
        gap: 10px;
    }
</style>

<?php
// --- Load all scheduled workloads, grouped by day ---
$sql = "SELECT s.*, c.course_code, c.course_name, c.year_level,
               r.room_name
        FROM schedules s
        JOIN courses c ON c.course_id = s.course_id
        JOIN rooms r   ON r.room_id   = s.room_id
        ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.start_time";
$result = $conn->query($sql);
$allEntries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$byDay = ['Monday'=>[], 'Tuesday'=>[], 'Wednesday'=>[], 'Thursday'=>[], 'Friday'=>[]];
foreach ($allEntries as $e) {
    if (isset($byDay[$e['day_of_week']])) $byDay[$e['day_of_week']][] = $e;
}
?>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">FACULTY PRINT CENTER</span><h2>Ready-to-print class schedule</h2></div>
    <div class="schedule-toolbar-row">
        <button class="primary-btn" id="openCreateWorkload" type="button">+ Create Workload</button>
        <button class="primary-btn print-btn" type="button" onclick="window.print()">🖨 Print Schedule</button>
    </div>
</div>

<div class="printable-schedule" id="printableSchedule">
    <div class="schedule-grid">
    <?php foreach ($byDay as $day => $entries): ?>
        <div class="day-column">
            <div class="day-title"><?= h($day) ?></div>
            <?php foreach ($entries as $e): ?>
            <div class="schedule-item"
                 data-schedule-id="<?= h($e['schedule_id']) ?>"
                 data-course-id="<?= h($e['course_id']) ?>"
                 data-room-id="<?= h($e['room_id']) ?>"
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
                <small><?= h($e['start_time'].' - '.$e['end_time']) ?></small>
                <small>⌂ <?= h($e['room_name']) ?></small>
                <small>Yr <?= h($e['year_level']) ?> · <?= h($e['section']) ?></small>
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
<div class="grid-modal" id="scheduleModal">
    <div class="modal-box">

        <h2 id="modalTitle">Create Workload</h2>

        <div class="form-group">
            <label>Day</label>
            <select id="dayPicker">
                <option value="">Select Day</option>
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
            </select>
        </div>

        <div class="form-group">
            <label>Start Time</label>
            <select id="startTime">
                <option value="">Select Start Time</option>
            </select>
        </div>

        <div class="form-group">
            <label>End Time</label>
            <select id="endTime">
                <option value="">Select start time first</option>
            </select>
        </div>

        <div class="form-group">
            <label>Year Level</label>
            <select id="yearLevel">
                <option value="">Select Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
        </div>

        <div class="form-group">
            <label>Course</label>
            <select id="courseSelect">
                <option value="">Select year level first</option>
            </select>
        </div>

        <div class="form-group">
            <label>Section</label>
            <select id="section">
                <option value="">Select year level first</option>
                <option value="BSIT-1A" data-year="1">BSIT-1A</option>
                <option value="BSIT-1B" data-year="1">BSIT-1B</option>
                <option value="BSIT-2A" data-year="2">BSIT-2A</option>
                <option value="BSIT-2B" data-year="2">BSIT-2B</option>
                <option value="BSIT-3A" data-year="3">BSIT-3A</option>
                <option value="BSIT-3B" data-year="3">BSIT-3B</option>
                <option value="BSIT-4A" data-year="4">BSIT-4A</option>
                <option value="BSIT-4B" data-year="4">BSIT-4B</option>
            </select>
        </div>

        <div class="form-group">
            <label>Room</label>
            <select id="room">
                <option value="">Loading...</option>
            </select>
        </div>

        <div class="form-group">
            <label for="cellColor">Cell Color</label>
            <input type="color" id="cellColor" value="#67e0a3">
        </div>

        <div class="modal-buttons">
            <button type="button" class="cancel-btn" id="cancelBtn">Cancel</button>
            <button type="button" class="delete-btn" id="deleteBtn" style="display:none">Delete</button>
            <button type="button" class="save-btn" id="saveBtn">Save</button>
        </div>

    </div>
</div>


<script>
/* ================= Modal open/close ================= */
const openCreateWorkload = document.getElementById('openCreateWorkload');
const modal = document.getElementById("scheduleModal");
const modalTitle = document.getElementById("modalTitle");
const saveButton = document.getElementById("saveBtn");
const cancelButton = document.getElementById("cancelBtn");
const deleteButton = document.getElementById("deleteBtn");
const dayPicker = document.getElementById("dayPicker");

let editingId = null; // null = creating a new workload; a value = editing that schedule_id

function resetModalForCreate() {
    editingId = null;
    modalTitle.textContent = "Create Workload";
    deleteButton.style.display = "none";
    dayPicker.value = "";
    document.getElementById("yearLevel").value = "";
    document.getElementById("room").value = "";
    document.getElementById("cellColor").value = "#67e0a3";
    courseSelect.innerHTML = '<option value="">Select year level first</option>';
    sectionSelect.value = "";
    rebuildTimeOptions();
}

openCreateWorkload.addEventListener('click', () => {
    resetModalForCreate();
    modal.classList.add('active');
});
cancelButton.addEventListener('click', () => modal.classList.remove('active'));

/* ================= Time dropdowns ================= */
const TIME_SLOTS = [
    { label: "7:00 AM",  db: "07:00:00" },
    { label: "7:30 AM",  db: "07:30:00" },
    { label: "8:00 AM",  db: "08:00:00" },
    { label: "8:30 AM",  db: "08:30:00" },
    { label: "9:00 AM",  db: "09:00:00" },
    { label: "9:30 AM",  db: "09:30:00" },
    { label: "10:00 AM", db: "10:00:00" },
    { label: "10:30 AM", db: "10:30:00" },
    { label: "11:00 AM", db: "11:00:00" },
    { label: "11:30 AM", db: "11:30:00" },
    { label: "12:00 PM", db: "12:00:00" },
    { label: "1:00 PM",  db: "13:00:00" },
    { label: "1:30 PM",  db: "13:30:00" },
    { label: "2:00 PM",  db: "14:00:00" },
    { label: "2:30 PM",  db: "14:30:00" },
    { label: "3:00 PM",  db: "15:00:00" },
    { label: "3:30 PM",  db: "15:30:00" },
    { label: "4:00 PM",  db: "16:00:00" },
    { label: "4:30 PM",  db: "16:30:00" },
    { label: "5:00 PM",  db: "17:00:00" },
    { label: "5:30 PM",  db: "17:30:00" },
    { label: "6:00 PM",  db: "18:00:00" },
    { label: "6:30 PM",  db: "18:30:00" },
    { label: "7:00 PM",  db: "19:00:00" }
];

const startTimeSelect = document.getElementById("startTime");
const endTimeSelect = document.getElementById("endTime");

startTimeSelect.addEventListener("change", function () {
    const checkedDays = dayPicker.value ? [dayPicker.value] : [];
    const startIndex = TIME_SLOTS.findIndex(s => s.db === this.value);
    endTimeSelect.innerHTML = '<option value="">Select End Time</option>';

    if (startIndex < 0) return;

    for (let i = startIndex + 1; i < TIME_SLOTS.length; i++) {
        if (isSlotBlocked(i, checkedDays)) break;
        const opt = document.createElement("option");
        opt.value = TIME_SLOTS[i].db;
        opt.textContent = TIME_SLOTS[i].label;
        endTimeSelect.appendChild(opt);
    }
});

/* ================= Populate Room dropdown ================= */
fetch('data/get_rooms.php')
    .then(res => res.json())
    .then(list => {
        const sel = document.getElementById('room');
        sel.innerHTML = '<option value="">Select Room</option>';
        list.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.room_id;
            opt.textContent = r.room_name;
            sel.appendChild(opt);
        });
    })
    .catch(() => { document.getElementById('room').innerHTML = '<option value="">Failed to load</option>'; });

/* ================= Fetch existing bookings when Room changes ================= */
let roomBookings = [];

document.getElementById('room').addEventListener('change', function () {
    const roomId = this.value;
    if (!roomId) { roomBookings = []; return; }

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

    if ([...startTimeSelect.options].some(o => o.value === previousValue)) {
        startTimeSelect.value = previousValue;
        startTimeSelect.dispatchEvent(new Event('change'));
    } else {
        endTimeSelect.innerHTML = '<option value="">Select start time first</option>';
    }
}

dayPicker.addEventListener('change', rebuildTimeOptions);

/* ================= Year Level -> Course + Section filtering ================= */
const yearLevelSelect = document.getElementById("yearLevel");
const courseSelect = document.getElementById("courseSelect");
const sectionSelect = document.getElementById("section");

function filterSections(year) {
    const options = sectionSelect.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (opt.dataset.year === year) ? '' : 'none';
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
    loadCoursesForYear(year);
});

/* ================= Save (create or edit) ================= */
saveButton.addEventListener("click", function () {
    const day = dayPicker.value;
    const startTime = startTimeSelect.value;
    const endTime = endTimeSelect.value;
    const courseId = courseSelect.value;
    const section = sectionSelect.value;
    const roomId = document.getElementById("room").value;
    const cellColor = document.getElementById("cellColor").value;

    if (!day || !startTime || !endTime || !courseId || !section || !roomId) {
        alert("Please fill in all fields.");
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
        alert("This time slot is already taken.");
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="save_workload">
        <input type="hidden" name="schedule_id" value="${editingId ?? ''}">
        <input type="hidden" name="course_id" value="${courseId}">
        <input type="hidden" name="room_id" value="${roomId}">
        <input type="hidden" name="section" value="${section}">
        <input type="hidden" name="day_of_week" value="${day}">
        <input type="hidden" name="start_time" value="${startTime}">
        <input type="hidden" name="end_time" value="${endTime}">
        <input type="hidden" name="cell_color" value="${cellColor}">
    `;
    document.body.appendChild(form);
    form.submit();
});

/* ================= Delete ================= */
deleteButton.addEventListener("click", function () {
    if (!editingId) return;
    if (!confirm("Delete this class from the schedule?")) return;
    window.location.href = `schedule.php?delete=${editingId}`;
});

/* ================= Card click -> open modal pre-filled for editing ================= */
document.querySelectorAll('.schedule-item').forEach(card => {
    card.addEventListener('click', function () {
        editingId = this.dataset.scheduleId;
        modalTitle.textContent = "Edit Workload";
        deleteButton.style.display = "";

        dayPicker.value = this.dataset.day;
        document.getElementById("cellColor").value = this.dataset.color;

        const roomSelect = document.getElementById("room");
        roomSelect.value = this.dataset.roomId;

        const year = this.dataset.year;
        yearLevelSelect.value = year;
        filterSections(year);
        sectionSelect.querySelector('option[value=""]').textContent = 'Select Section';

        // Room bookings must load before we can correctly build the time dropdowns
        fetch(`data/get_booked_times.php?room_id=${this.dataset.roomId}`)
            .then(res => res.json())
            .then(data => {
                roomBookings = data;
                rebuildTimeOptions();
                startTimeSelect.value = this.dataset.start;
                startTimeSelect.dispatchEvent(new Event('change'));
                endTimeSelect.value = this.dataset.end;
            })
            .then(() => loadCoursesForYear(year, this.dataset.courseId))
            .then(() => { sectionSelect.value = this.dataset.section; });

        modal.classList.add('active');
    });
});
</script>

<?php require 'includes/footer.php'; ?>