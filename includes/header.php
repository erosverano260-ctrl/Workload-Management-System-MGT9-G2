<?php
require_once __DIR__ . '/auth.php';
if (!currentRole()) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'EE Enrollment Portal') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
    <div class="brand"><img src="assets/ee-logo.svg" alt=""><span>EE Enrollment</span></div>
    <nav>
        <a href="index.php" class="<?= $page==='dashboard'?'active':'' ?>"><b>▦</b> Dashboard</a>
        <a href="courses.php" class="<?= $page==='courses'?'active':'' ?>"><b>▤</b> Course Catalog</a>
        <a href="enrollment.php" class="<?= $page==='enroll'?'active':'' ?>"><b>✎</b> Process Enrollment</a>
        <a href="classrooms.php" class="<?= $page==='rooms'?'active':'' ?>"><b>⌂</b> Classrooms</a>
        <a href="instructors.php" class="<?= $page==='instructors'?'active':'' ?>"><b>♙</b> Instructors</a>
        <a href="schedule.php" class="<?= $page==='schedule'?'active':'' ?>"><b>◷</b> Print Schedules</a>
        <a href="Faculty_schedule.php" class="<?= $page==='Faculty_schedule'?'active':'' ?>"><b>◷</b> Faculty Schedules</a>
    </nav>
    <div class="sidebar-foot">
        <div class="avatar small"><?= strtoupper(substr($_SESSION['faculty_name'] ?? 'F', 0, 1)) ?></div>
        <div><strong><?= h($_SESSION['faculty_name'] ?? 'Faculty') ?></strong><small>Faculty</small></div>
        <a href="logout.php" class="logout-link" title="Log out">⏻</a>
    </div>
</aside>

<main class="main-area">
