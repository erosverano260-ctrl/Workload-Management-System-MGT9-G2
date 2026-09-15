<?php
$page='instructors';$title='Instructor Availability';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');
require 'includes/header.php';
?>
<section class="page-section"><div class="section-heading"><div><span class="eyebrow">FACULTY AVAILABILITY</span><h2>Instructors available to conduct classes</h2></div></div>
<div class="instructor-grid"><?php foreach($instructors as $i):?><div class="instructor-card"><div class="faculty-avatar"><?=strtoupper(substr($i['name'],6,1))?></div><div class="faculty-info"><h3><?=h($i['name'])?></h3><p><?=h($i['specialty'])?></p><div class="faculty-row"><span>Available</span><b><?=h($i['available'])?></b></div><div class="faculty-row"><span>Assigned classes</span><b><?=$i['classes']?></b></div></div><span class="online-dot">● Available</span></div><?php endforeach;?></div>
</section><?php require 'includes/footer.php'; ?>
