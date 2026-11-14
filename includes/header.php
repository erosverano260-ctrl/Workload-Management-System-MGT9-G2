<?php
/* ADMIN layout. Faculty pages use includes/faculty_header.php instead. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
requireRole('admin');
$adminName = currentUserName() ?: 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(($title ?? 'Admin') . ' · EE Admin') ?></title>
<link rel="stylesheet" href="<?= asset('style.css') ?>">
<link rel="stylesheet" href="<?= asset('layout.css') ?>">
<script>try{if(localStorage.getItem('navCollapsed')==='1')document.documentElement.classList.add('nav-collapsed')}catch(e){}</script>
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
    <div class="brand"><img src="../assets/ee-logo.svg" alt=""><span>EE Workload Management <small style="display:block;font-weight:600;color:var(--amber);font-size:11px">ADMIN</small></span><button type="button" class="nav-toggle" id="navToggle" aria-label="Collapse menu" aria-expanded="true" title="Collapse menu">‹</button></div>
    <nav>
        <a href="index.php" class="<?= $page==='dashboard'?'active':'' ?>"><b>▦</b> Dashboard</a>
        <a href="courses.php" class="<?= $page==='courses'?'active':'' ?>"><b>▤</b> Course Catalog</a>
        <a href="classrooms.php" class="<?= $page==='rooms'?'active':'' ?>"><b>⌂</b> Classrooms</a>
        <a href="instructors.php" class="<?= $page==='instructors'?'active':'' ?>"><b>♙</b> Instructors</a>
        <a href="schedule.php" class="<?= $page==='schedule'?'active':'' ?>"><b>◷</b> Print/Create Schedules</a>
        <a href="print.php" class="<?= $page==='print'?'active':'' ?>"><b>⎙</b> Print Forms</a>
        <a href="account.php" class="<?= $page==='account'?'active':'' ?>"><b>⚙</b> My Account</a>
    </nav>
    <div class="sidebar-foot">
        <div class="avatar small"><?= h(strtoupper(mb_substr($adminName, 0, 1))) ?></div>
        <div><strong><?= h($adminName) ?></strong><small>Administrator</small></div>
        <a href="../logout.php" class="logout-link" title="Log out" aria-label="Log out">
            <svg class="logout-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</aside>

<main class="main-area">
