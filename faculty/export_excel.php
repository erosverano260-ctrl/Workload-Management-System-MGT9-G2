<?php
/** Download the signed-in instructor's own class program as an Excel file on LONG bond paper (8.5 x 13 in). */
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/xlsx_forms.php';
requireRole('faculty');
if (!empty($_SESSION['must_change'])) redirect('profile.php');            // same rule as the rest of the portal: choose a new password first

$conn = (new database())->connect();
$GLOBALS['conn'] = $conn;                                              // print helpers read the shared connection
if (function_exists('ensure_other_workload_schema')) ensure_other_workload_schema($conn);
date_default_timezone_set('Asia/Manila');
$inst = loadInstructor($conn, currentUserId());                         // always the signed-in instructor, never a URL parameter
$id   = (int)$inst['faculty_id'];

$book = new XlsxBook();
xl_instructor($book, $inst, load_program_entries($conn, 'faculty', $id), load_designations($conn, $id), load_overload_sessions($conn, $id));
$slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string)$inst['faculty_name']), '-');
$book->stream('Class-Program-' . ($slug !== '' ? $slug : 'Instructor') . '-LongBond-' . date('Y-m-d') . '.xlsx');
