<?php
/**
 * Download the printed forms as one Excel workbook on LONG bond paper (8.5 x 13 in).
 *   export_excel.php            -> every form (all sections, all instructors, all rooms, the workload matrix)
 *   export_excel.php?type=room  -> just one kind: section | instructor | room | matrix
 */
$root = dirname(__DIR__);
require_once $root . '/includes/db.php';
require_once $root . '/includes/auth.php';
require_once $root . '/includes/ui.php';
require_once $root . '/includes/schedule_helpers.php';
require_once $root . '/includes/print_helpers.php';
require_once $root . '/includes/xlsx_forms.php';

date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
$GLOBALS['conn'] = $conn;
if (function_exists('ensure_other_workload_schema')) {
    ensure_other_workload_schema($conn);
}

$role = function_exists('currentRole') ? currentRole() : '';
if ($role === 'faculty') {
    requireRole('faculty');
    $inst = loadInstructor($conn, currentUserId());
    $facId = (int)($inst['faculty_id'] ?? 0);
    if ($facId > 0) {
        $GLOBALS['faculty_export_filter_id'] = $facId;
    }
} else {
    requireRole('admin');
}

$names = [
    'section'    => 'Students-Class-Program',
    'instructor' => 'Instructors-Class-Program',
    'room'       => 'Room-Utilization',
    'matrix'     => 'Faculty-Workload-Matrix',
];
$type  = $_GET['type'] ?? 'all';
$types = isset($names[$type]) ? [$type] : array_keys($names);
$label = isset($names[$type]) ? $names[$type] : 'All-Printable-Forms';

xl_build_workbook($conn, $types)->stream('EE-' . $label . '-LongBond-' . date('Y-m-d') . '.xlsx');