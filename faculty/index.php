<?php
$page = 'dashboard'; $title = 'My Dashboard';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/schedule_helpers.php';
require '../includes/approvals.php';          // faculty_load_for(), nf()
requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn    = (new database())->connect();
$inst    = loadInstructor($conn, currentUserId());
$entries = loadFacultySchedule($conn, (int)$inst['faculty_id']);

$hours    = scheduleHours($entries);
$dayCount = count(array_unique(array_column($entries, 'day_of_week')));
$sections = count(array_unique(array_column($entries, 'section')));
$courses  = count(array_unique(array_column($entries, 'course_id')));
$outside  = array_values(array_filter($entries, fn($e) => outsideAvailability($e, $inst)));

$FL     = faculty_load_for($conn, (int)$inst['faculty_id']);       // lecture, lab, total hours, preps, overload, claim, credits, 40-hr week
$unread = unread_count($conn, 'faculty', (int)$inst['faculty_id']);

$today  = date('l');
$nowMin = (int)date('G') * 60 + (int)date('i');
$next   = null;
foreach ($entries as $e) {
    if ($e['day_of_week'] === $today && timeToMin($e['end_time']) > $nowMin) { $next = $e; break; }
}
$todayCount = count(array_filter($entries, fn($e) => $e['day_of_week'] === $today));

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

require '../includes/faculty_header.php';
?>
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
  <div class="dash-welcome">
    <div>
      <h2><?= h($greeting) ?>, <?= h($inst['faculty_name']) ?></h2>
      <p><?= h(date('l, F j, Y')) ?> · <?= $entries ? h($hours) . ' teaching hours a week' : 'No classes assigned to you yet' ?></p>
    </div>
    <div class="dash-actions">
      <a href="schedule.php" class="primary-btn">View my schedule</a>
      <a href="profile.php" class="primary-btn">Update availability</a>
    </div>
  </div>

  <?php if ($unread): ?>
  <a href="notifications.php" class="ui-notice" style="display:block;text-decoration:none"><strong>You have <?= $unread ?> new notification<?= $unread === 1 ? '' : 's' ?></strong> (requests and the chair's decisions). Open them →</a>
  <?php endif; ?>

  <div class="dash-panel" style="margin-bottom:18px">
    <h3>My teaching load</h3>
    <p class="panel-sub">Total hours = lecture hours + lab hours × 0.7. Overload is the part above your regular load (21 hrs for 1–2 preparations, 18 for 3–4, 15 for 5–6).</p>
    <div class="ld-grid">
      <div class="ld-item"><small>Lecture hours</small><strong><?= nf($FL['lec']) ?></strong></div>
      <div class="ld-item"><small>Lab hours</small><strong><?= nf($FL['lab']) ?></strong><em>× 0.7 = <?= nf($FL['lab'] * 0.7) ?></em></div>
      <div class="ld-item"><small>Total hours</small><strong><?= nf($FL['comp']) ?></strong><em>lecture + lab × 0.7</em></div>
      <div class="ld-item"><small>Preparations</small><strong><?= (int)$FL['preps'] ?></strong><em>each subject counted once</em></div>
      <div class="ld-item"><small>Regular load</small><strong><?= (int)$FL['allowed'] ?></strong><em>for <?= (int)$FL['preps'] ?> prep<?= $FL['preps'] === 1 ? '' : 's' ?></em></div>
      <div class="ld-item <?= $FL['overload'] > 0 ? 'warn' : '' ?>"><small>Teaching overload</small><strong><?= nf($FL['overload']) ?></strong><em>total − <?= (int)$FL['allowed'] ?></em></div>
      <div class="ld-item"><small>Overload hours to claim</small><strong><?= nf($FL['claim']) ?></strong><em>approved <?= nf($FL['sessApproved']) ?> · waiting <?= nf($FL['sessPending']) ?></em></div>
      <div class="ld-item"><small>Teaching service credit</small><strong><?= nf($FL['credit']) ?></strong><em>overload remainder under 1 hr</em></div>
      <div class="ld-item"><small>Service credits</small><strong><?= nf($FL['serviceCredit']) ?></strong><em>from designations · not in the <?= FACULTY_WEEK_HOURS ?> hrs</em></div>
    </div>
    <p class="panel-sub" style="margin:16px 0 0">Faculty time: <strong><?= nf($FL['weekTotal']) ?></strong> of <?= FACULTY_WEEK_HOURS ?> hours a week</p>
    <div class="wk-bar" title="Teaching / designation / other functions">
      <span style="width:<?= round($FL['regular'] / FACULTY_WEEK_HOURS * 100, 1) ?>%;background:var(--navy)"></span>
      <span style="width:<?= round($FL['desHrs'] / FACULTY_WEEK_HOURS * 100, 1) ?>%;background:var(--amber-dark)"></span>
      <span style="width:<?= round($FL['other'] / FACULTY_WEEK_HOURS * 100, 1) ?>%;background:#c9d1e4"></span>
    </div>
    <div class="wk-legend"><span><i style="background:var(--navy)"></i>Teaching <?= nf($FL['regular']) ?></span><span><i style="background:var(--amber-dark)"></i>Designation <?= nf($FL['desHrs']) ?></span><span><i style="background:#c9d1e4"></i>Other functions <?= nf($FL['other']) ?></span></div>
    <?php if ($FL['over40'] > 0): ?><div class="ui-notice" style="margin-top:10px">Teaching plus designation is <strong><?= nf($FL['over40']) ?> hr(s) over</strong> the <?= FACULTY_WEEK_HOURS ?>-hour week. Talk to the chair.</div><?php endif; ?>
    <?php if ($FL['claimLeft'] >= 1): ?><p style="margin:12px 0 0"><a href="overload.php" class="outline-btn" style="display:inline-block">Designate <?= nf($FL['claimLeft']) ?> more overload hour<?= $FL['claimLeft'] == 1 ? '' : 's' ?> →</a></p><?php endif; ?>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">◷</span><div><strong><?= count($entries) ?></strong><small>Classes a week</small><em class="stat-sub"><?= h($hours) ?> hrs total</em></div></div>
    <div class="stat-card"><span class="stat-icon">▦</span><div><strong><?= $courses ?></strong><small>Courses</small><em class="stat-sub">you are teaching</em></div></div>
    <div class="stat-card"><span class="stat-icon">♙</span><div><strong><?= $sections ?></strong><small>Sections</small><em class="stat-sub">handled</em></div></div>
    <div class="stat-card"><span class="stat-icon">⌂</span><div><strong><?= $dayCount ?></strong><small>Teaching days</small><em class="stat-sub">out of 5</em></div></div>
  </div>

  <div class="dash-grid">
    <div class="dash-col">
      <div class="dash-panel">
        <h3>Today · <?= h($today) ?></h3>
        <p class="panel-sub"><?= $todayCount ? $todayCount . ' class' . ($todayCount === 1 ? '' : 'es') . ' today' : (in_array($today, FAC_DAYS, true) ? 'No classes today.' : 'No classes on weekends.') ?></p>
        <?php if ($next): ?>
        <div class="next-up">
          <div>
            <small><?= timeToMin($next['start_time']) <= $nowMin ? 'HAPPENING NOW' : 'NEXT CLASS' ?></small>
            <strong><?= h($next['course_code']) ?> · <?= h($next['course_name']) ?></strong>
            <span><?= h(rangeLabel($next['start_time'], $next['end_time'])) ?> · <?= h($next['section']) ?> · <?= h($next['room_name']) ?></span>
          </div>
        </div>
        <?php elseif ($todayCount): ?>
        <div class="dash-empty">That's the last of today's classes.</div>
        <?php endif; ?>
      </div>

      <div class="dash-panel">
        <h3>My week</h3>
        <p class="panel-sub">Your assigned classes, day by day.</p>
        <?php foreach (FAC_DAYS as $d):
            $dayEntries = array_values(array_filter($entries, fn($e) => $e['day_of_week'] === $d)); ?>
        <div class="wk-day">
          <h4><?= h($d) ?><?php if ($d === $today): ?> <span class="now">TODAY</span><?php endif; ?></h4>
          <?php if ($dayEntries): ?>
          <ul>
            <?php foreach ($dayEntries as $e): ?>
            <li style="--dot:<?= h($e['cell_color'] ?: '#ffb100') ?>">
              <time><?= h(rangeLabel($e['start_time'], $e['end_time'])) ?></time>
              <span><strong><?= h($e['course_code']) ?></strong> · <?= h($e['section']) ?> · <?= h($e['room_name']) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?><div class="none">No classes</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dash-col">
      <div class="dash-panel">
        <h3>My availability</h3>
        <p class="panel-sub">What the scheduling office sees when assigning you classes.</p>
        <p class="avail-big"><?= h(fmtDaysCsv($inst['available_days'])) ?></p>
        <p class="muted" style="margin:0 0 12px"><?= $inst['available_from'] && $inst['available_to'] ? h(rangeLabel($inst['available_from'], $inst['available_to'])) : 'No hours set' ?></p>
        <a href="profile.php" class="outline-btn" style="display:inline-block">Edit availability</a>
      </div>

      <?php if ($outside): ?>
      <div class="ui-notice">
        <strong><?= count($outside) ?> class<?= count($outside) === 1 ? '' : 'es' ?> fall outside your availability:</strong>
        <?php foreach (array_slice($outside, 0, 4) as $e): ?>
          <div><?= h($e['course_code']) ?> · <?= h(substr($e['day_of_week'], 0, 3)) ?> <?= h(rangeLabel($e['start_time'], $e['end_time'])) ?></div>
        <?php endforeach; ?>
        <?php if (count($outside) > 4): ?><div>…and <?= count($outside) - 4 ?> more</div><?php endif; ?>
        <div style="margin-top:6px">Update your availability, or ask the department admin to adjust the schedule.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require '../includes/footer.php'; ?>
