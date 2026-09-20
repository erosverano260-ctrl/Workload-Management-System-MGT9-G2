<?php
$page = 'schedule';
$title = 'Class Schedule';

require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');
require 'includes/header.php';
require 'includes/db.php';


?>

<link rel="stylesheet" href="assets/faculty_schedule.css">

</head>

<body>

<<<<<<< Updated upstream
<button id="createWorkloadBtn" type="button">
    Create Workload
</button>
=======
    .save-schedule-btn:hover {
        background: #17603c;
    }

    .print-header {
        display: none;
    }

@media print {
    .schedule-toolbar,
    #scheduleModal {
        display: none !important;
    }

    .schedule,
    .schedule * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ...rest of your existing print rules... */



    .schedule-slot.merged {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

        .print-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0 0 16px;
        }

        .print-header img {
            height: 48px;
        }

        .print-header .print-school {
            font-size: 11px;
            letter-spacing: .05em;
            color: #555;
            text-transform: uppercase;
        }

        .print-header h3 {
            margin: 2px 0;
        }

        .print-header p {
            margin: 0;
            font-size: 12px;
            color: #555;
        }

        .schedule-container {
            overflow: visible;
        }
    }
</style>

<div class="schedule-toolbar">
    <button id="createWorkloadBtn" type="button">
        Create Workload
    </button>

    <button id="saveScheduleBtn" type="button" class="save-schedule-btn" title="Save the workload schedule and open the print dialog">
        🖨 Save &amp; Print
    </button>
</div>

<div class="print-header" id="workloadPrintHeader">
    <img src="assets/ee-logo.svg" alt="logo">
    <div>
        <div class="print-school">Institute of Integrated Electrical Engineers</div>
        <h3>Faculty Workload Schedule</h3>
        <p>Academic Year 2026–2027 · Generated <span id="printGeneratedDate"></span></p>
    </div>
</div>
>>>>>>> Stashed changes

<div class="schedule-container">

    <div class="schedule" id="schedule">

        <!-- HEADER -->

        <div class="cell header" style="grid-column: 1 / 3;">
            Time
        </div>

        <div class="cell header">Monday</div>
        <div class="cell header">Tuesday</div>
        <div class="cell header">Wednesday</div>
        <div class="cell header">Thursday</div>
        <div class="cell header">Friday</div>


        <!-- AM -->

        <div class="cell period" style="grid-column: 1; grid-row: 2 / 12;">
            AM
        </div>


        <!-- TIME -->

        <div class="cell time" style="grid-column: 2; grid-row: 2;">
            7:00 - 7:30
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 3;">
            7:30 - 8:00
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 4;">
            8:00 - 8:30
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 5;">
            8:30 - 9:00
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 6;">
            9:00 - 9:30
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 7;">
            9:30 - 10:00
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 8;">
            10:00 - 10:30
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 9;">
            10:30 - 11:00
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 10;">
            11:00 - 11:30
        </div>

        <div class="cell time" style="grid-column: 2; grid-row: 11;">
            11:30 - 12:00
        </div>


        <!-- MONDAY AM -->

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="7:00 - 7:30"
             style="grid-column:3; grid-row:2;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="7:30 - 8:00"
             style="grid-column:3; grid-row:3;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="8:00 - 8:30"
             style="grid-column:3; grid-row:4;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="8:30 - 9:00"
             style="grid-column:3; grid-row:5;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="9:00 - 9:30"
             style="grid-column:3; grid-row:6;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="9:30 - 10:00"
             style="grid-column:3; grid-row:7;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="10:00 - 10:30"
             style="grid-column:3; grid-row:8;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="10:30 - 11:00"
             style="grid-column:3; grid-row:9;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="11:00 - 11:30"
             style="grid-column:3; grid-row:10;">
        </div>

        <div class="cell schedule-slot"
             data-day="Monday"
             data-time="11:30 - 12:00"
             style="grid-column:3; grid-row:11;">
        </div>


        
        <!-- TUESDAY-FRIDAY AM ARE CREATED BY JAVASCRIPT -->


        <!-- LUNCH -->

        <div class="cell lunch" 
             style="grid-column: 1 / 8; grid-row: 12;">
            LUNCH BREAK
        </div>


        <!-- PM -->

        <div class="cell period"
             style="grid-column: 1; grid-row: 13 / 25;">
            PM
        </div>


        <!-- PM TIME -->

        <div class="cell time" style="grid-column:2;grid-row:13;">
            1:00 - 1:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:14;">
            1:30 - 2:00
        </div>

        <div class="cell time" style="grid-column:2;grid-row:15;">
            2:00 - 2:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:16;">
            2:30 - 3:00
        </div>

        <div class="cell time" style="grid-column:2;grid-row:17;">
            3:00 - 3:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:18;">
            3:30 - 4:00
        </div>

        <div class="cell time" style="grid-column:2;grid-row:19;">
            4:00 - 4:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:20;">
            4:30 - 5:00
        </div>

        <div class="cell time" style="grid-column:2;grid-row:21;">
            5:00 - 5:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:22;">
            5:30 - 6:00
        </div>

        <div class="cell time" style="grid-column:2;grid-row:23;">
            6:00 - 6:30
        </div>

        <div class="cell time" style="grid-column:2;grid-row:24;">
            6:30 - 7:00
        </div>

    </div>

</div>


<!-- ========================= -->
<!-- WORKLOAD MODAL -->
<!-- ========================= -->

<div class="modal" id="scheduleModal">

    <div class="modal-box">

        <h2 id="modalTitle">
            Create Workload
        </h2>

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
            <select id="courseSelect" name="course_id">
            <option value="">Select year level first</option>
            </select>
        </div>

        <div class="form-group">

            <label>Faculty</label>

            <select id="faculty">

                <option value="">
                    Select Faculty
                </option>

                <option value="Faculty 1">
                    Faculty 1
                </option>

                <option value="Faculty 2">
                    Faculty 2
                </option>

                <option value="Faculty 3">
                    Faculty 3
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>Section</label>

            <select id="section">

                <option value="">
                    Select Section
                </option>

                <option value="BSIT-1A">
                    BSIT-1A
                </option>

                <option value="BSIT-1B">
                    BSIT-1B
                </option>

                <option value="BSIT-2A">
                    BSIT-2A
                </option>

                <option value="BSIT-2B">
                    BSIT-2B
                </option>

                <option value="BSIT-3A">
                    BSIT-3A
                </option>

                <option value="BSIT-3B">
                    BSIT-3B
                </option>

                <option value="BSIT-4A">
                    BSIT-4A
                </option>

                <option value="BSIT-4B">
                    BSIT-4B
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>Room</label>

            <select id="room">

                <option value="">
                    Select Room
                </option>

                <option value="Room 101">
                    Room 101
                </option>

                <option value="Room 102">
                    Room 102
                </option>

                <option value="Laboratory 1">
                    Laboratory 1
                </option>

                <option value="Laboratory 2">
                    Laboratory 2
                </option>

            </select>

         <label for="cellColor">Cell Color</label>
                <input
                    type="color"
                    id="cellColor"
                    value="#67e0a3"
                >
        </div>


        <div class="modal-buttons">

            <button
                type="button"
                class="cancel-btn"
                id="cancelBtn">
                Cancel
            </button>

            <button
                type="button"
                class="delete-btn"
                id="deleteBtn">
                Delete
            </button>

            <button
                type="button"
                class="save-btn"
                id="saveBtn">
                Save
            </button>

        </div>

    </div>

</div>


<script>
const createButton = document.getElementById("createWorkloadBtn");
const modal = document.getElementById("scheduleModal");
const saveButton = document.getElementById("saveBtn");
<<<<<<< Updated upstream
const cancelButton = document.getElementById("cancelBtn");
=======
const saveScheduleBtn = document.getElementById("saveScheduleBtn");
const cancelButton = document.getElementById("cancelBtn");

saveScheduleBtn.addEventListener("click", function () {
    const dateSpan = document.getElementById("printGeneratedDate");
    if (dateSpan) {
        dateSpan.textContent = new Date().toLocaleDateString();
    }
    window.print();
});

/* ---------- Year Level → Course dropdown ---------- */
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
                opt.dataset.code = c.course_code;
                opt.dataset.name = c.course_name;
                opt.textContent = `${c.course_code} - ${c.course_name}`;
                courseSelect.appendChild(opt);
            });
        })
        .catch(err => {
            courseSelect.innerHTML = '<option value="">Failed to load courses</option>';
            console.error("get_courses fetch failed:", err);
        });
});

    let mergedSlot = null;
    let selectedSlots = [];


// Top-right "Save & Print" button: makes the workload schedule print-ready
saveScheduleBtn.addEventListener("click", function () {
    const dateSpan = document.getElementById("printGeneratedDate");
    if (dateSpan) {
        dateSpan.textContent = new Date().toLocaleDateString();
    }
    window.print();
});
>>>>>>> Stashed changes



const schedule = document.getElementById("schedule");

const mondaySlots = [
    ...schedule.querySelectorAll('.schedule-slot[data-day="Monday"]')
];


//AM
const days = ["Tuesday", "Wednesday", "Thursday", "Friday"];

days.forEach(function (day, index) {

    mondaySlots.forEach(function (mondaySlot) {

        const newSlot = mondaySlot.cloneNode(true);

        // Change the day
        newSlot.dataset.day = day;  

        // Put the cell in the correct day column
        newSlot.style.gridColumn = index + 4;

        // Reset copied states
        newSlot.classList.remove("selected");
        newSlot.classList.remove("merged");
        newSlot.style.display = "";
        newSlot.style.backgroundColor = "";

        // Make the new cell empty
        newSlot.innerHTML = "";

        schedule.appendChild(newSlot);
    });

});

const pmStartRow = 13;
const pmEndRow = 24;

const pmDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

//PM
pmDays.forEach(function (day, dayIndex) {

    for (let row = pmStartRow; row <= pmEndRow; row++) {

        const pmSlot = document.createElement("div");

        pmSlot.classList.add("cell", "schedule-slot");

        pmSlot.dataset.day = day;
        pmSlot.dataset.time = "PM";

        pmSlot.style.gridColumn = dayIndex + 3;
        pmSlot.style.gridRow = row;

        schedule.appendChild(pmSlot);
    }

});


// Create workload
createButton.addEventListener("click", function () {
 if (selectedSlots.length === 0) {
        alert("Please select a time period first.");
        return;
    }

    // Sort the selected cells from top to bottom
    selectedSlots.sort(function (a, b) {
        return parseInt(a.style.gridRow) - parseInt(b.style.gridRow);
    });

    const firstSlot = selectedSlots[0];
    mergedSlot = firstSlot;

    // Hide the other selected cells
    for (let i = 1; i < selectedSlots.length; i++) {
        selectedSlots[i].style.display = "none";
    }

    // Turn the first cell into the large block
    firstSlot.classList.remove("selected");
    firstSlot.classList.add("merged");

    const startingRow = parseInt(firstSlot.style.gridRow);

    firstSlot.style.gridRow =
        startingRow + " / span " + selectedSlots.length;

    firstSlot.innerHTML = `
        <div class="entry">
            <strong>New Workload</strong>
        </div>
    `;

    // Clear selection
    selectedSlots = [];
modal.classList.add("active");
});


// SELECT SCHEDULE CELLS
document.addEventListener("click", function (event) {

    const slot = event.target.closest(".schedule-slot");

    if (!slot) {
        return;
    }

    // Select the cell
    slot.classList.toggle("selected");

    // Add/remove from selectedSlots
    if (slot.classList.contains("selected")) {
        selectedSlots.push(slot);
    } else {
        selectedSlots = selectedSlots.filter(function (item) {
            return item !== slot;
        });
    }

    console.log("Selected slots:", selectedSlots);
});

saveButton.addEventListener("click", function () {

    const selectedOption = courseSelect.options[courseSelect.selectedIndex];
    const courseCode = selectedOption ? selectedOption.dataset.code : "";
    const courseName = selectedOption ? selectedOption.dataset.name : "";
    const faculty = document.getElementById("faculty").value;
    const section = document.getElementById("section").value;
    const room = document.getElementById("room").value;
    const cellColor = document.getElementById("cellColor").value;

    if (!courseCode || !courseName || !faculty || !section || !room) {
        alert("Please fill in all fields.");
        return;
    }

    mergedSlot.innerHTML = `
        <div class="entry">
            <strong>${courseCode}</strong>
            <span>${courseName}</span>
            <span>${faculty}</span>
            <span>${section}</span>
            <span>${room}</span>
        </div>
    `;
    mergedSlot.style.setProperty("background-color", cellColor, "important");
    modal.classList.remove("active");
});

cancelButton.addEventListener("click", function () {
    modal.classList.remove("active");
});




</script>


</body>
</html>