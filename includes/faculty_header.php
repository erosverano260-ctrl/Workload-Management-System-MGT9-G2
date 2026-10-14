<?php
/* FACULTY layout: three pages only. Forces a password change after an admin-issued temporary password. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
requireRole('faculty');
if (!empty($_SESSION['must_change']) && ($page ?? '') !== 'profile') {
    header('Location: profile.php'); exit;
}
$facName = currentUserName() ?: 'Instructor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(($title ?? 'Faculty Portal') . ' · EE Faculty') ?></title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/ui.css">
<link rel="stylesheet" href="../assets/portal.css">
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
    <div class="brand"><img src="../assets/ee-logo.svg" alt=""><span>EE Faculty Portal <small style="display:block;font-weight:600;color:var(--amber);font-size:11px">INSTRUCTOR</small></span></div>
    <nav>
        <a href="index.php" class="<?= $page==='dashboard'?'active':'' ?>"><b>▦</b> My Dashboard</a>
        <a href="schedule.php" class="<?= $page==='schedule'?'active':'' ?>"><b>◷</b> My Schedule</a>
        <a href="profile.php" class="<?= $page==='profile'?'active':'' ?>"><b>♙</b> My Profile</a>
    </nav>
    <div class="sidebar-foot">
        <div class="avatar small"><?= h(strtoupper(mb_substr(preg_replace('/^(engr|dr|prof|mr|ms|mrs)\.?\s+/i', '', $facName), 0, 1))) ?></div>
        <div><strong><?= h($facName) ?></strong><small>Faculty</small></div>
        <a href="../logout.php" class="logout-link" title="Log out" aria-label="Log out">
            <svg class="logout-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</aside>

<main class="main-area">
