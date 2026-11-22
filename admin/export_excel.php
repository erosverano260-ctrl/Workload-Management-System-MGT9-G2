<?php
/**
 * Download the printed forms as one Excel workbook on LONG bond paper (8.5 x 13 in).
 *   export_excel.php            -> every form (all sections, all instructors, all rooms, the workload matrix)
 *   export_excel.php?type=room  -> just one kind: section | instructor | room | matrix
 */
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/xlsx_forms.php';
requireRole('admin');

$names = ['section' => "Students-Class-Program", 'instructor' => "Instructors-Class-Program", 'room' => 'Room-Utilization', 'matrix' => 'Faculty-Workload-Matrix'];
$type  = $_GET['type'] ?? 'all';
$types = isset($names[$type]) ? [$type] : array_keys($names);
$label = isset($names[$type]) ? $names[$type] : 'All-Printable-Forms';

$conn = (new database())->connect();
xl_build_workbook($conn, $types)->stream('EE-' . $label . '-LongBond-' . date('Y-m-d') . '.xlsx');
