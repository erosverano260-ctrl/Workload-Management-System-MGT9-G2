<?php
/**
 * Sample data + tiny JSON-file "database" for enrollments.
 * Swap this whole file for PDO/MySQL later (see README).
 */

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------- Students (would be a `students` table) ---------- */
$students = [
    ['id'=>'2026-0001','name'=>'Juan Andres','year'=>3],
    ['id'=>'2026-0002','name'=>'Maria Santos','year'=>2],
    ['id'=>'2026-0003','name'=>'Carlo Reyes','year'=>1],
    ['id'=>'2026-0004','name'=>'Bea Villanueva','year'=>4],
    ['id'=>'2026-0005','name'=>'Paolo Cruz','year'=>3],
];

/* ---------- Instructors ---------- */
$instructors = [
    ['name'=>'Engr. Ramon Torres','specialty'=>'Power Systems','available'=>'Mon–Wed, 8AM–12NN','classes'=>3],
    ['name'=>'Engr. Liza Fernandez','specialty'=>'Control Systems','available'=>'Tue–Thu, 1PM–5PM','classes'=>2],
    ['name'=>'Engr. Michael Ong','specialty'=>'Electronics','available'=>'Mon, Wed, Fri, 9AM–3PM','classes'=>4],
    ['name'=>'Engr. Grace Del Rosario','specialty'=>'Circuits & Electromagnetics','available'=>'Thu–Fri, 8AM–4PM','classes'=>2],
];

/* ---------- Rooms ---------- */
$rooms = [
    ['name'=>'EE Lab 1','type'=>'Laboratory','capacity'=>30,'occupied'=>24,'status'=>'Available'],
    ['name'=>'EE Lab 2','type'=>'Laboratory','capacity'=>30,'occupied'=>30,'status'=>'Full'],
    ['name'=>'Room 201','type'=>'Lecture Room','capacity'=>45,'occupied'=>20,'status'=>'Available'],
    ['name'=>'Room 202','type'=>'Lecture Room','capacity'=>45,'occupied'=>38,'status'=>'Available'],
    ['name'=>'Power Systems Lab','type'=>'Laboratory','capacity'=>25,'occupied'=>25,'status'=>'Full'],
    ['name'=>'Room 305','type'=>'Lecture Room','capacity'=>40,'occupied'=>10,'status'=>'Available'],
];

/* ---------- Course sections (would be `class_sections`) ---------- */
$courses = [
    ['code'=>'EE101','name'=>'Circuit Analysis 1','instructor'=>'Engr. Grace Del Rosario','schedule'=>'Mon/Wed 8:00–9:30AM','room'=>'Room 201','units'=>3,'year'=>1,'status'=>'Open','capacity'=>45],
    ['code'=>'EE102','name'=>'Electromagnetics','instructor'=>'Engr. Grace Del Rosario','schedule'=>'Tue/Thu 9:30–11:00AM','room'=>'Room 202','units'=>3,'year'=>1,'status'=>'Open','capacity'=>45],
    ['code'=>'EE201','name'=>'Electronics 1','instructor'=>'Engr. Michael Ong','schedule'=>'Mon/Wed 1:00–2:30PM','room'=>'EE Lab 1','units'=>4,'year'=>2,'status'=>'Open','capacity'=>30],
    ['code'=>'EE202','name'=>'Logic Circuits & Design','instructor'=>'Engr. Michael Ong','schedule'=>'Fri 8:00–11:00AM','room'=>'EE Lab 2','units'=>3,'year'=>2,'status'=>'Closed','capacity'=>30],
    ['code'=>'EE301','name'=>'Power Systems 1','instructor'=>'Engr. Ramon Torres','schedule'=>'Tue/Thu 1:00–2:30PM','room'=>'Power Systems Lab','units'=>4,'year'=>3,'status'=>'Open','capacity'=>25],
    ['code'=>'EE302','name'=>'Control Systems','instructor'=>'Engr. Liza Fernandez','schedule'=>'Wed 2:30–5:30PM','room'=>'Room 305','units'=>3,'year'=>3,'status'=>'Open','capacity'=>40],
    ['code'=>'EE401','name'=>'EE Design Project 1','instructor'=>'Engr. Ramon Torres','schedule'=>'Mon 1:00–4:00PM','room'=>'Room 305','units'=>3,'year'=>4,'status'=>'Open','capacity'=>40],
    ['code'=>'EE402','name'=>'Industrial Automation','instructor'=>'Engr. Liza Fernandez','schedule'=>'Thu 8:00–11:00AM','room'=>'EE Lab 1','units'=>3,'year'=>4,'status'=>'Open','capacity'=>30],
];

/* ---------- Enrollment records: tiny JSON-file store ---------- */
define('ENROLLMENTS_FILE', __DIR__ . '/../data/enrollments.json');

function loadEnrollments(): array {
    if (!file_exists(ENROLLMENTS_FILE)) return [];
    $raw = file_get_contents(ENROLLMENTS_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveEnrollments(array $enrollments): void {
    file_put_contents(ENROLLMENTS_FILE, json_encode($enrollments, JSON_PRETTY_PRINT));
}

/** Add an enrollment record. Returns [bool success, string message]. */
function enrollStudent(string $studentId, string $courseCode, array $courses): array {
    $enrollments = loadEnrollments();

    foreach ($enrollments as $e) {
        if ($e['student_id'] === $studentId && $e['course_code'] === $courseCode) {
            return [false, 'Student is already enrolled in this course.'];
        }
    }

    $course = null;
    foreach ($courses as $c) { if ($c['code'] === $courseCode) { $course = $c; break; } }
    if (!$course) return [false, 'Course not found.'];
    if ($course['status'] === 'Closed') return [false, 'This section is closed.'];

    $count = count(array_filter($enrollments, fn($e) => $e['course_code'] === $courseCode));
    if ($count >= $course['capacity']) return [false, 'This section is already full.'];

    $enrollments[] = [
        'id' => uniqid('enr_'),
        'student_id' => $studentId,
        'course_code' => $courseCode,
        'date_enrolled' => date('Y-m-d H:i'),
        'processed_by' => $_SESSION['faculty_name'] ?? 'Faculty',
    ];
    saveEnrollments($enrollments);
    return [true, 'Enrollment processed successfully.'];
}

function dropEnrollment(string $id): void {
    $enrollments = loadEnrollments();
    $enrollments = array_values(array_filter($enrollments, fn($e) => $e['id'] !== $id));
    saveEnrollments($enrollments);
}

function studentById(array $students, string $id): ?array {
    foreach ($students as $s) if ($s['id'] === $id) return $s;
    return null;
}

function courseByCode(array $courses, string $code): ?array {
    foreach ($courses as $c) if ($c['code'] === $code) return $c;
    return null;
}

function enrolledCountForCourse(array $enrollments, string $code): int {
    return count(array_filter($enrollments, fn($e) => $e['course_code'] === $code));
}

/* ---------- Dashboard stats (used by index.php) ---------- */
$enrollmentsAll   = loadEnrollments();
$totalCourses        = count($courses);
$availableRooms      = count(array_filter($rooms, fn($r) => $r['status'] === 'Available'));
$availableInstructors= count($instructors);
$openSections        = count(array_filter($courses, fn($c) => $c['status'] === 'Open'));
