<?php
$page='schedule';$title='Class Schedule';
require 'includes/data.php';
require 'includes/auth.php';
requireRole('faculty');
require 'includes/header.php';
?>
<section class="page-section">
<div class="section-heading"><div><span class="eyebrow">FACULTY PRINT CENTER</span><h2>Ready-to-print class schedule</h2></div><button class="primary-btn print-btn" onclick="window.print()">🖨 Print Schedule</button></div>
<div class="schedule-tools"><div><label>Student Name</label><input id="printStudentName" placeholder="Enter student name"></div><div><label>Student ID</label><input id="printStudentId" placeholder="Enter student ID"></div><div><label>Year Level</label><select id="printYear"><option value="0">All Year Levels</option><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option></select></div><span class="date-chip">AY 2026–2027</span></div>
<div class="printable-schedule" id="printableSchedule"><div class="print-header"><img src="assets/ee-logo.svg"><div><div class="print-school">INSTITUTE OF INTEGRATED ELECTRICAL ENGINEERS</div><h3>Electrical Engineering Student Class Schedule</h3><p>Academic Year 2026–2027</p></div></div><div class="student-print-info"><span><b>Student:</b> <span id="printNameOutput">____________________________</span></span><span><b>Student ID:</b> <span id="printIdOutput">________________</span></span></div>
<div class="schedule-grid"><?php foreach(["Monday","Tuesday","Wednesday","Thursday","Friday"] as $day):?><div class="day-column"><div class="day-title"><?=$day?></div><?php $dc=array_values(array_filter($courses,function($c)use($day){return stripos($c['schedule'],substr($day,0,3))!==false; }));foreach($dc as $c):?><div class="schedule-item" data-year="<?=$c['year']?>"><span class="code"><?=$c['code']?></span><strong><?=h($c['name'])?></strong><small><?=h($c['schedule'])?></small><small>⌂ <?=h($c['room'])?></small><small>♙ <?=h($c['instructor'])?></small></div><?php endforeach;if(!$dc):?><div class="no-class">No scheduled class</div><?php endif;?></div><?php endforeach;?></div></div>
</section>
<script>
document.getElementById('printStudentName')?.addEventListener('input', function(){ document.getElementById('printNameOutput').textContent = this.value || '____________________________'; });
document.getElementById('printStudentId')?.addEventListener('input', function(){ document.getElementById('printIdOutput').textContent = this.value || '________________'; });
document.getElementById('printYear')?.addEventListener('change', function(){
    const y = this.value;
    document.querySelectorAll('.schedule-item').forEach(el => { el.style.display = (y==='0' || el.dataset.year===y) ? '' : 'none'; });
});
</script>
<?php require 'includes/footer.php'; ?>
