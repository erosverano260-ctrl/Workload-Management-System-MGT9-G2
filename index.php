<?php
$page='dashboard';$title='Electrical Engineering Enrollment';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');
require 'includes/header.php';
?>
<section class="page-section">
<div class="hero"><div><span class="pill">⚡ SMART ENROLLMENT</span><h2>Plan your next semester<br><span>with confidence.</span></h2><p>Browse EE courses, process student enrollment, check classrooms, and find instructors who are available to teach.</p><a href="courses.php" class="primary-btn">Browse Courses <span>→</span></a></div><div class="hero-circuit"><span class="wire w1"></span><span class="wire w2"></span><span class="node n1"></span><span class="node n2"></span><div class="bolt">ϟ</div></div></div>
<div class="stats-grid">
<div class="stat-card"><span class="stat-icon">▦</span><div><strong><?= $totalCourses ?></strong><small>Course Sections</small></div></div>
<div class="stat-card"><span class="stat-icon">⌂</span><div><strong><?= $availableRooms ?></strong><small>Rooms Available</small></div></div>
<div class="stat-card"><span class="stat-icon">♙</span><div><strong><?= $availableInstructors ?></strong><small>Instructors</small></div></div>
<div class="stat-card"><span class="stat-icon">✓</span><div><strong><?= $openSections ?></strong><small>Open Sections</small></div></div>
</div>
</section>
<section class="quick-grid">
<a href="courses.php" class="quick-card"><b>▦</b><div><strong>Course Catalog</strong><small>Browse 1st–4th year subjects</small></div><span>→</span></a>
<a href="enrollment.php" class="quick-card"><b>✎</b><div><strong>Process Enrollment</strong><small>Enroll or drop students</small></div><span>→</span></a>
<a href="classrooms.php" class="quick-card"><b>⌂</b><div><strong>Classrooms</strong><small>Check room capacity and status</small></div><span>→</span></a>
<a href="instructors.php" class="quick-card"><b>♙</b><div><strong>Instructors</strong><small>See faculty teaching availability</small></div><span>→</span></a>
</section>
<?php require 'includes/footer.php'; ?>
