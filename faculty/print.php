<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/print_helpers.php';

requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
$GLOBALS['conn'] = $conn;
ensure_other_workload_schema($conn);

$inst          = loadInstructor($conn, currentUserId());
$facId         = (int)$inst['faculty_id'];
$entries       = load_program_entries($conn, 'faculty', $facId);
$designations  = load_designations($conn, $facId);
$sessions      = load_overload_sessions($conn, $facId);
$otherWorkload = load_other_workload($conn, $facId);
$c             = pcfg();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instructor's Class Program — <?= h($inst['faculty_name']) ?></title>
<link rel="stylesheet" href="<?= asset('print.css') ?>">
<style>
  .print-toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #cbd5e1;
  }
  .print-toolbar a,
  .print-toolbar button {
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 7px;
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
  }
  .print-toolbar a.secondary {
    background: #fff;
    color: #0f172a;
  }
  @media print {
    .print-toolbar { display: none !important; }
  }
</style>
</head>
<body>
<div class="print-toolbar">
  <button type="button" onclick="window.print()">🖨 Print Class Program</button>
  <a href="export_excel.php" class="secondary">⬇ Download Excel</a>
  <a href="other_workload.php" class="secondary">✏️ Edit Section B &amp; C Hours</a>
  <a href="schedule.php" class="secondary">← Back to Schedule</a>
</div>
<?php render_instructor_program($inst, $entries, $designations, $sessions, $otherWorkload); ?>
</body>
</html>