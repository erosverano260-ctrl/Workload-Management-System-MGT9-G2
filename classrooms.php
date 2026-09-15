<?php
$page='rooms';$title='Classroom Availability';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');
require 'includes/header.php';
?>
<section class="page-section"><div class="section-heading"><div><span class="eyebrow">SPACE MANAGEMENT</span><h2>Classroom availability</h2></div><span class="date-chip">Live Planning View</span></div>
<div class="room-grid"><?php foreach($rooms as $r):$p=round($r['occupied']/$r['capacity']*100);?><div class="room-card"><div class="room-icon">⌂</div><div class="room-head"><div><h3><?=h($r['name'])?></h3><small><?=h($r['type'])?></small></div><span class="status <?=strtolower($r['status'])?>"><?=h($r['status'])?></span></div><div class="room-numbers"><span><b><?=$r['occupied']?></b> Occupied</span><span><b><?=$r['capacity']-$r['occupied']?></b> Seats Left</span><span><b><?=$r['capacity']?></b> Capacity</span></div><div class="progress wide"><span data-width="<?=$p?>"></span></div><small class="muted"><?=$p?>% utilization</small></div><?php endforeach;?></div>
<div class="table-card"><table><thead><tr><th>ROOM</th><th>TYPE</th><th>CAPACITY</th><th>OCCUPIED</th><th>STATUS</th></tr></thead><tbody><?php foreach($rooms as $r):?><tr><td><strong><?=h($r['name'])?></strong></td><td><?=h($r['type'])?></td><td><?=$r['capacity']?></td><td><?=$r['occupied']?></td><td><span class="status <?=strtolower($r['status'])?>"><?=h($r['status'])?></span></td></tr><?php endforeach;?></tbody></table></div>
</section><?php require 'includes/footer.php'; ?>
