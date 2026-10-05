<?php
$page = 'schedule';
$title = 'Class Schedule';

require 'includes/db.php';
require 'includes/auth.php';
requireRole('faculty');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$db = new database();
$conn = $db->connect();

// --- SAVE NEW WORKLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_workload') {
    $stmt = $conn->prepare(
        "INSERT INTO schedules (course_id, faculty_id, room_id, section, day_of_week, start_time, end_time, cell_color)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iiissss",
        $_POST['course_id'],
        $_POST['faculty_id'],
        $_POST['room_id'],
        $_POST['section'],
        $_POST['day_of_week'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['cell_color']
    );
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

    @media print {
        body.printing-single .schedule-tools,
        body.printing-single #openCreateWorkload,
        body.printing-single .print-btn,
        body.printing-single .grid-modal,
        body.printing-single .schedule-grid {
            display: none !important;
        }
        body.printing-single #detailModal {
            display: block !important;
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            box-shadow: none !important;
        }
        body.printing-single #detailModal .modal-buttons,
        body.printing-single #detailModal .modal-close {
            display: none !important;
        }
    }
</style>

<?php
// --- Load all scheduled workloads, grouped by day ---
$sql = "SELECT s.*, c.course_code, c.course_name, c.year_level,
               f.faculty_name, r.room_name
        FROM schedules s
        JOIN courses c  ON c.course_id  = s.course_id
        JOIN faculty f  ON f.faculty_id = s.faculty_id
        JOIN rooms r    ON r.room_id    = s.room_id
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
                 data-year="<?= h($e['year_level']) ?>"
                 data-course="<?= h($e['course_code']) ?>"
                 data-name="<?= h($e['course_name']) ?>"
                 data-schedule="<?= h($e['day_of_week'].' '.$e['start_time'].' - '.$e['end_time']) ?>"
                 data-room="<?= h($e['room_name']) ?>"
                 data-faculty="<?= h($e['faculty_name']) ?>"
                 data-section="<?= h($e['section']) ?>"
                 data-yearlabel="<?= h($e['year_level']) ?>"
                 style="border-left:4px solid <?= h($e['cell_color']) ?>">
                <span class="code"><?= h($e['course_code']) ?></span>
                <strong><?= h($e['course_name']) ?></strong>
                <small><?= h($e['start_time'].' - '.$e['end_time']) ?></small>
                <small>⌂ <?= h($e['room_name']) ?></small>
                <small>♙ <?= h($e['faculty_name']) ?></small>
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
<!-- CREATE WORKLOAD MODAL (full weekly grid) -->
<!-- ============================================= -->
<div class="grid-modal" id="gridModal">
    <div style="background:#fff; border-radius:10px; padding:20px; width:150vw; max-width:1300px; max-height:92vh; overflow:auto;">

        <div class="schedule-toolbar" style="justify-content:flex-end;">
            <button type="button" class="cancel-btn" id="closeGridModal">Close</button>
        </div>

        <div class="schedule-container">
            <div class="schedule" id="schedule">

                <div class="cell header" style="grid-column: 1 / 3;">Time</div>
                <div class="cell header">Monday</div>
                <div class="cell header">Tuesday</div>
                <div class="cell header">Wednesday</div>
                <div class="cell header">Thursday</div>
                <div class="cell header">Friday</div>

                <div class="cell period" style="grid-column: 1; grid-row: 2 / 12;">AM</div>

                <div class="cell time" style="grid-column: 2; grid-row: 2;">7:00 - 7:30</div>
                <div class="cell time" style="grid-column: 2; grid-row: 3;">7:30 - 8:00</div>
                <div class="cell time" style="grid-column: 2; grid-row: 4;">8:00 - 8:30</div>
                <div class="cell time" style="grid-column: 2; grid-row: 5;">8:30 - 9:00</div>
                <div class="cell time" style="grid-column: 2; grid-row: 6;">9:00 - 9:30</div>
                <div class="cell time" style="grid-column: 2; grid-row: 7;">9:30 - 10:00</div>
                <div class="cell time" style="grid-column: 2; grid-row: 8;">10:00 - 10:30</div>
                <div class="cell time" style="grid-column: 2; grid-row: 9;">10:30 - 11:00</div>
                <div class="cell time" style="grid-column: 2; grid-row: 10;">11:00 - 11:30</div>
                <div class="cell time" style="grid-column: 2; grid-row: 11;">11:30 - 12:00</div>

                <div class="cell schedule-slot" data-day="Monday" data-time="7:00 - 7:30" style="grid-column:3; grid-row:2;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="7:30 - 8:00" style="grid-column:3; grid-row:3;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="8:00 - 8:30" style="grid-column:3; grid-row:4;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="8:30 - 9:00" style="grid-column:3; grid-row:5;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="9:00 - 9:30" style="grid-column:3; grid-row:6;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="9:30 - 10:00" style="grid-column:3; grid-row:7;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="10:00 - 10:30" style="grid-column:3; grid-row:8;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="10:30 - 11:00" style="grid-column:3; grid-row:9;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="11:00 - 11:30" style="grid-column:3; grid-row:10;"></div>
                <div class="cell schedule-slot" data-day="Monday" data-time="11:30 - 12:00" style="grid-column:3; grid-row:11;"></div>

                <!-- Tuesday-Friday AM + all PM slots are built by JS -->

                <div class="cell lunch" style="grid-column: 1 / 8; grid-row: 12;">LUNCH BREAK</div>

                <div class="cell period" style="grid-column: 1; grid-row: 13 / 25;">PM</div>

                <div class="cell time" style="grid-column:2;grid-row:13;">1:00 - 1:30</div>
                <div class="cell time" style="grid-column:2;grid-row:14;">1:30 - 2:00</div>
                <div class="cell time" style="grid-column:2;grid-row:15;">2:00 - 2:30</div>
                <div class="cell time" style="grid-column:2;grid-row:16;">2:30 - 3:00</div>
                <div class="cell time" style="grid-column:2;grid-row:17;">3:00 - 3:30</div>
                <div class="cell time" style="grid-column:2;grid-row:18;">3:30 - 4:00</div>
                <div class="cell time" style="grid-column:2;grid-row:19;">4:00 - 4:30</div>
                <div class="cell time" style="grid-column:2;grid-row:20;">4:30 - 5:00</div>
                <div class="cell time" style="grid-column:2;grid-row:21;">5:00 - 5:30</div>
                <div class="cell time" style="grid-column:2;grid-row:22;">5:30 - 6:00</div>
                <div class="cell time" style="grid-column:2;grid-row:23;">6:00 - 6:30</div>
                <div class="cell time" style="grid-column:2;grid-row:24;">6:30 - 7:00</div>

            </div>
        </div>

    </div>
</div>


<!-- ============================================= -->
<!-- WORKLOAD DETAILS FORM (year/course/faculty/room) -->
<!-- ============================================= -->
<div class="grid-modal" id="scheduleModal">
    <div class="modal-box">

        <h2 id="modalTitle">Create Workload</h2>

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
            <label>Faculty</label>
            <select id="faculty">
                <option value="">Loading...</option>
            </select>
        </div>

        <div class="form-group">
            <label>Section</label>
            <select id="section">
                <option value="">Select Section</option>
                <option value="BSIT-1A">BSIT-1A</option>
                <option value="BSIT-1B">BSIT-1B</option>
                <option value="BSIT-2A">BSIT-2A</option>
                <option value="BSIT-2B">BSIT-2B</option>
                <option value="BSIT-3A">BSIT-3A</option>
                <option value="BSIT-3B">BSIT-3B</option>
                <option value="BSIT-4A">BSIT-4A</option>
                <option value="BSIT-4B">BSIT-4B</option>
            </select>
        </div>

        <div class="form-group">
            <label>Room</label>
            <select id="room">
                <option value="">Loading...</option>
            </select>
            <label for="cellColor">Cell Color</label>
            <input type="color" id="cellColor" value="#67e0a3">
        </div>

        <div class="modal-buttons">
            <button type="button" class="cancel-btn" id="cancelBtn">Cancel</button>
            <button type="button" class="save-btn" id="saveBtn">Save</button>
        </div>

    </div>
</div>


<!-- ============================================= -->
<!-- CARD DETAIL / SINGLE-PRINT MODAL -->
<!-- ============================================= -->
<div class="modal-backdrop" id="detailBackdrop"></div>
<div class="modal" id="detailModal">
    <button class="modal-close" id="closeDetailBtn">×</button>
    <span class="pill" id="detailCourseCode"></span>
    <h2 id="detailCourseName"></h2>
    <div class="confirm-box">
        <div><span>Schedule</span><b id="detailSchedule"></b></div>
        <div><span>Room</span><b id="detailRoom"></b></div>
        <div><span>Faculty</span><b id="detailFaculty"></b></div>
        <div><span>Year / Section</span><b id="detailYearSection"></b></div>
    </div>
    <div class="modal-buttons">
        <button type="button" class="save-btn full" id="printDetailBtn">🖨 Print This Schedule</button>
    </div>
</div>


<script>
document.getElementById('printStudentName')?.addEventListener('input', function(){ document.getElementById('printNameOutput').textContent = this.value || '____________________________'; });
document.getElementById('printStudentId')?.addEventListener('input', function(){ document.getElementById('printIdOutput').textContent = this.value || '________________'; });
document.getElementById('printYear')?.addEventListener('change', function(){
    const y = this.value;
    document.querySelectorAll('.schedule-item').forEach(el => { el.style.display = (y==='0' || el.dataset.year===y) ? '' : 'none'; });
});

/* ================= GRID MODAL open/close ================= */
const openCreateWorkload = document.getElementById('openCreateWorkload');
const gridModal = document.getElementById('gridModal');
const closeGridModal = document.getElementById('closeGridModal');

openCreateWorkload.addEventListener('click', () => gridModal.classList.add('active'));
closeGridModal.addEventListener('click', () => gridModal.classList.remove('active'));

/* ================= DETAILS FORM MODAL ================= */
const modal = document.getElementById("scheduleModal");
const saveButton = document.getElementById("saveBtn");
const cancelButton = document.getElementById("cancelBtn");

/* ================= Populate Faculty + Room dropdowns once ================= */
fetch('data/get_faculty.php')
    .then(res => res.json())
    .then(list => {
        const sel = document.getElementById('faculty');
        sel.innerHTML = '<option value="">Select Faculty</option>';
        list.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.faculty_id;
            opt.textContent = f.faculty_name;
            sel.appendChild(opt);
        });
    })
    .catch(() => { document.getElementById('faculty').innerHTML = '<option value="">Failed to load</option>'; });

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

/* ================= Year Level -> Course dropdown ================= */
const yearLevelSelect = document.getElementById("yearLevel");
const courseSelect = document.getElementById("courseSelect");

yearLevelSelect.addEventListener("change", function () {
    const year = this.value;
    if (!year) {
        courseSelect.innerHTML = '<option value="">Select year level first</option>';
        return;
    }
    courseSelect.innerHTML = '<option value="">Loading...</option>';
    fetch(`data/get_courses.php?year_level=${year}`)
        .then(res => res.json())
        .then(courses => {
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            courses.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.course_id;
                opt.textContent = `${c.course_code} - ${c.course_name}`;
                courseSelect.appendChild(opt);
            });
        })
        .catch(() => { courseSelect.innerHTML = '<option value="">Failed to load courses</option>'; });
});

/* ================= Build the weekly grid (Tue-Fri AM clones + all PM slots) ================= */
let mergedSlot = null;
let selectedSlots = [];

const schedule = document.getElementById("schedule");
const mondaySlots = [...schedule.querySelectorAll('.schedule-slot[data-day="Monday"]')];

const days = ["Tuesday", "Wednesday", "Thursday", "Friday"];
days.forEach(function (day, index) {
    mondaySlots.forEach(function (mondaySlot) {
        const newSlot = mondaySlot.cloneNode(true);
        newSlot.dataset.day = day;
        newSlot.style.gridColumn = index + 4;
        newSlot.classList.remove("selected");
        newSlot.classList.remove("merged");
        newSlot.style.display = "";
        newSlot.style.backgroundColor = "";
        newSlot.innerHTML = "";
        schedule.appendChild(newSlot);
    });
});

const PM_TIMES = [
    "1:00 - 1:30", "1:30 - 2:00", "2:00 - 2:30", "2:30 - 3:00",
    "3:00 - 3:30", "3:30 - 4:00", "4:00 - 4:30", "4:30 - 5:00",
    "5:00 - 5:30", "5:30 - 6:00", "6:00 - 6:30", "6:30 - 7:00"
];
const pmStartRow = 13;
const pmEndRow = 24;
const pmDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

pmDays.forEach(function (day, dayIndex) {
    for (let row = pmStartRow; row <= pmEndRow; row++) {
        const pmSlot = document.createElement("div");
        pmSlot.classList.add("cell", "schedule-slot");
        pmSlot.dataset.day = day;
        pmSlot.dataset.time = PM_TIMES[row - pmStartRow];
        pmSlot.style.gridColumn = dayIndex + 3;
        pmSlot.style.gridRow = row;
        schedule.appendChild(pmSlot);
    }
});

/* ================= Cell selection ================= */
document.addEventListener("click", function (event) {
    const slot = event.target.closest(".schedule-slot");
    if (!slot) return;

    slot.classList.toggle("selected");
    if (slot.classList.contains("selected")) {
        selectedSlots.push(slot);
    } else {
        selectedSlots = selectedSlots.filter(item => item !== slot);
    }
});

/* ================= After selecting time slots, open the details form ================= */
schedule.addEventListener("dblclick", function (event) {
    const slot = event.target.closest(".schedule-slot");
    if (!slot) return;
    openWorkloadForm();
});

// Simplest trigger: a small "Create Workload" button pinned inside the grid modal toolbar
const gridToolbar = gridModal.querySelector('.schedule-toolbar');
const createInGridBtn = document.createElement('button');
createInGridBtn.type = 'button';
createInGridBtn.textContent = 'Create Workload';
createInGridBtn.className = 'primary-btn';
gridToolbar.prepend(createInGridBtn);
createInGridBtn.addEventListener('click', openWorkloadForm);

function openWorkloadForm() {
    if (selectedSlots.length === 0) {
        alert("Please select a time period first.");
        return;
    }

    selectedSlots.sort((a, b) => parseInt(a.style.gridRow) - parseInt(b.style.gridRow));

    const firstSlot = selectedSlots[0];
    const lastSlot = selectedSlots[selectedSlots.length - 1];
    mergedSlot = firstSlot;

    // Capture actual start/end clock times + day before we lose track of the other slots
    const startTime = firstSlot.dataset.time.split(' - ')[0];
    const endTime = lastSlot.dataset.time.split(' - ')[1];
    mergedSlot.dataset.startTime = startTime;
    mergedSlot.dataset.endTime = endTime;

    for (let i = 1; i < selectedSlots.length; i++) {
        selectedSlots[i].style.display = "none";
    }

    firstSlot.classList.remove("selected");
    firstSlot.classList.add("merged");

    const startingRow = parseInt(firstSlot.style.gridRow);
    firstSlot.style.gridRow = startingRow + " / span " + selectedSlots.length;

    selectedSlots = [];
    modal.classList.add("active");
}

/* ================= Save: POST to this page, then reload to show the new card ================= */
saveButton.addEventListener("click", function () {
    const courseId = courseSelect.value;
    const facultyId = document.getElementById("faculty").value;
    const roomId = document.getElementById("room").value;
    const section = document.getElementById("section").value;
    const cellColor = document.getElementById("cellColor").value;
    const day = mergedSlot ? mergedSlot.dataset.day : "";
    const startTime = mergedSlot ? mergedSlot.dataset.startTime : "";
    const endTime = mergedSlot ? mergedSlot.dataset.endTime : "";

    if (!courseId || !facultyId || !roomId || !section || !day) {
        alert("Please fill in all fields.");
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="save_workload">
        <input type="hidden" name="course_id" value="${courseId}">
        <input type="hidden" name="faculty_id" value="${facultyId}">
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

cancelButton.addEventListener("click", function () {
    modal.classList.remove("active");
});

/* ================= Card click -> detail/print modal ================= */
const detailModal = document.getElementById('detailModal');
const detailBackdrop = document.getElementById('detailBackdrop');

document.querySelectorAll('.schedule-item').forEach(card => {
    card.addEventListener('click', function () {
        document.getElementById('detailCourseCode').textContent = this.dataset.course;
        document.getElementById('detailCourseName').textContent = this.dataset.name;
        document.getElementById('detailSchedule').textContent = this.dataset.schedule;
        document.getElementById('detailRoom').textContent = this.dataset.room;
        document.getElementById('detailFaculty').textContent = this.dataset.faculty;
        document.getElementById('detailYearSection').textContent = 'Year ' + this.dataset.yearlabel + ' · ' + this.dataset.section;
        detailModal.classList.add('open');
        detailBackdrop.classList.add('open');
    });
});

document.getElementById('closeDetailBtn').addEventListener('click', () => {
    detailModal.classList.remove('open');
    detailBackdrop.classList.remove('open');
});
detailBackdrop.addEventListener('click', () => {
    detailModal.classList.remove('open');
    detailBackdrop.classList.remove('open');
});

document.getElementById('printDetailBtn').addEventListener('click', function () {
    document.body.classList.add('printing-single');
    window.print();
});
window.addEventListener('afterprint', () => document.body.classList.remove('printing-single'));
</script>

<?php require 'includes/footer.php'; ?>