<?php
$page = 'schedule'; $title = 'Print My Class Program';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/print_helpers.php';
requireRole('faculty');

$conn = (new database())->connect();
$inst = loadInstructor($conn, currentUserId());                         // always the signed-in instructor, never a URL parameter
$entries = load_program_entries($conn, 'faculty', (int)$inst['faculty_id']);

require '../includes/faculty_header.php';
?>
<link rel="stylesheet" href="<?= asset('print.css') ?>">
<style>@page { size: 8.5in 13in; margin: 8mm; }</style>

<section class="page-section">
<div class="section-heading no-print">
    <div><span class="eyebrow">OFFICIAL FORM</span><h2>My class program</h2></div>
    <div style="display:flex;gap:10px">
        <a href="schedule.php" class="outline-btn" style="display:inline-flex;align-items:center;height:42px;padding:0 16px">← Back</a>
        <button class="primary-btn" type="button" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
</div>
<?php render_instructor_program($inst, $entries); ?>
</section>

<?php require '../includes/footer.php'; ?>
