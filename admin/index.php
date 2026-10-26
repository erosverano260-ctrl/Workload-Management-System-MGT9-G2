<?php
$page='dashboard';$title='Electrical Engineering Enrollment';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
requireRole('admin');

date_default_timezone_set('Asia/Manila');

$db = new database();
$conn = $db->connect();

const WEEK_HOURS_PER_ROOM = 60;   // 7am-7pm, 5 days (same figure the Classrooms page uses)
$DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

function one(mysqli $c, string $sql): array { return $c->query($sql)->fetch_assoc() ?: []; }
function all(mysqli $c, string $sql): array { return $c->query($sql)->fetch_all(MYSQLI_ASSOC); }

/* ---------- Statistics ---------- */
$courseStats = one($conn, "SELECT COUNT(*) AS total, COALESCE(SUM(status='Open'),0) AS open_count FROM courses");
$schedStats  = one($conn, "SELECT COUNT(*) AS total,
                                  COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(end_time,start_time)))/3600,0) AS hours,
                                  COALESCE(SUM(faculty_id IS NULL),0) AS unassigned
                           FROM schedules");
$roomCount   = (int)one($conn, "SELECT COUNT(*) AS n FROM rooms")['n'];
$unscheduled = (int)one($conn, "SELECT COUNT(*) AS n FROM courses c
                                LEFT JOIN schedules s ON s.course_id = c.course_id
                                WHERE s.schedule_id IS NULL")['n'];

try { $instructorCount = (int)one($conn, "SELECT COUNT(*) AS n FROM faculty")['n']; }
catch (mysqli_sql_exception $e) { $instructorCount = 0; }

$byYearRaw = all($conn, "SELECT year_level, COUNT(*) AS n FROM courses GROUP BY year_level");
$byYear = array_fill(1, 4, 0);
foreach ($byYearRaw as $r) if (isset($byYear[(int)$r['year_level']])) $byYear[(int)$r['year_level']] = (int)$r['n'];

$byDayRaw = all($conn, "SELECT day_of_week, COUNT(*) AS n FROM schedules GROUP BY day_of_week");
$byDay = array_fill_keys($DAYS, 0);
foreach ($byDayRaw as $r) if (isset($byDay[$r['day_of_week']])) $byDay[$r['day_of_week']] = (int)$r['n'];

$roomUse = all($conn, "SELECT r.room_name,
                              COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time)))/3600,0) AS hrs
                       FROM rooms r LEFT JOIN schedules s ON s.room_id = r.room_id
                       GROUP BY r.room_id ORDER BY hrs DESC, r.room_name LIMIT 5");

$today = date('l');
$todayClasses = in_array($today, $DAYS, true)
    ? (function () use ($conn, $today) {
        $stmt = $conn->prepare("SELECT s.start_time, s.end_time, s.section, s.cell_color, c.course_code, c.course_name, r.room_name
                                FROM schedules s JOIN courses c ON c.course_id = s.course_id JOIN rooms r ON r.room_id = s.room_id
                                WHERE s.day_of_week = ? ORDER BY s.start_time");
        $stmt->bind_param('s', $today);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    })()
    : [];

$totalCourses = (int)$courseStats['total'];
$openCourses  = (int)$courseStats['open_count'];
$classCount   = (int)$schedStats['total'];
$hours        = round((float)$schedStats['hours'], 1);
$unassigned   = (int)$schedStats['unassigned'];
$avgUtil      = $roomCount ? min(100, round(($hours / ($roomCount * WEEK_HOURS_PER_ROOM)) * 100)) : 0;

$maxDay  = max(1, max($byDay));
$maxYear = max(1, max($byYear));
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$who = currentUserName() ?: 'Admin';

require '../includes/header.php';
?>
<link rel="stylesheet" href="<?= asset('ui.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
  <div class="dash-welcome">
    <div>
      <h2><?= h($greeting) ?>, <?= h($who) ?></h2>
      <p><?= h(date('l, F j, Y')) ?> · <?= $classCount ?> class<?= $classCount === 1 ? '' : 'es' ?> on the weekly schedule</p>
    </div>
    <div class="dash-actions">
      <a href="schedule.php" class="primary-btn">+ Create Workload</a>
      <a href="courses.php" class="primary-btn">Manage Courses</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">▦</span><div><strong><?= $totalCourses ?></strong><small>Courses</small><em class="stat-sub"><?= $openCourses ?> open · <?= $totalCourses - $openCourses ?> closed</em></div></div>
    <div class="stat-card"><span class="stat-icon">◷</span><div><strong><?= $classCount ?></strong><small>Scheduled classes</small><em class="stat-sub"><?= $hours ?> hrs per week</em></div></div>
    <div class="stat-card"><span class="stat-icon">♙</span><div><strong><?= $instructorCount ?></strong><small>Instructors</small><em class="stat-sub"><?= $unassigned ?> class<?= $unassigned === 1 ? '' : 'es' ?> unassigned</em></div></div>
    <div class="stat-card"><span class="stat-icon">⌂</span><div><strong><?= $roomCount ?></strong><small>Rooms</small><em class="stat-sub"><?= $avgUtil ?>% average use</em></div></div>
  </div>

  <div class="dash-grid">
    <div class="dash-col">
      <div class="dash-panel">
        <h3>Classes per day</h3>
        <p class="panel-sub">How the week is loaded. The busiest day is highlighted.</p>
        <div class="bars">
          <?php $i = 0; foreach ($byDay as $day => $n): ?>
          <div class="bar <?= ($n === max($byDay) && $n > 0) ? 'is-peak' : '' ?>">
            <b><?= $n ?></b>
            <div class="bar-fill" style="--h:<?= round(($n / $maxDay) * 100) ?>%; --i:<?= $i++ ?>"></div>
            <small><?= h(substr($day, 0, 3)) ?></small>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="dash-panel">
        <h3>Courses by year level</h3>
        <p class="panel-sub"><?= $unscheduled ?> course<?= $unscheduled === 1 ? ' has' : 's have' ?> no scheduled class yet.</p>
        <?php foreach ($byYear as $y => $n): ?>
        <div class="hrow">
          <span><?= $y ?><?= $y===1?'st':($y===2?'nd':($y===3?'rd':'th')) ?> year</span>
          <div class="htrack"><span style="--w:<?= round(($n / $maxYear) * 100) ?>%; --i:<?= $y ?>"></span></div>
          <b><?= $n ?></b>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dash-col">
      <div class="dash-panel">
        <h3>Today's classes</h3>
        <p class="panel-sub"><?= h($today) ?></p>
        <?php if ($todayClasses): ?>
        <ul class="today-list">
          <?php foreach ($todayClasses as $c): ?>
          <li style="--dot:<?= h($c['cell_color'] ?: '#2457f5') ?>">
            <div><strong><?= h($c['course_code']) ?> · <?= h($c['course_name']) ?></strong><span><?= h($c['section']) ?> · <?= h($c['room_name']) ?></span></div>
            <span class="today-time"><?= h(date('g:i', strtotime($c['start_time']))) ?>–<?= h(date('g:i A', strtotime($c['end_time']))) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="dash-empty"><?= in_array($today, $DAYS, true) ? 'No classes scheduled today.' : 'No classes on weekends.' ?></div>
        <?php endif; ?>
      </div>

      <div class="dash-panel">
        <h3>Busiest rooms</h3>
        <p class="panel-sub">Booked hours out of <?= WEEK_HOURS_PER_ROOM ?> available per week.</p>
        <?php foreach ($roomUse as $r):
            $pct = min(100, round(($r['hrs'] / WEEK_HOURS_PER_ROOM) * 100)); ?>
        <div class="room-row">
          <div class="room-row-top"><?= h($r['room_name']) ?><span><?= round($r['hrs'], 1) ?> hrs · <?= $pct ?>%</span></div>
          <div class="progress wide"><span data-width="<?= $pct ?>"></span></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$roomUse): ?><div class="dash-empty">No rooms yet.</div><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="quick-grid">
<a href="courses.php" class="quick-card"><b>▦</b><div><strong>Course Catalog</strong><small>Add, edit and close courses</small></div><span>→</span></a>
<a href="schedule.php" class="quick-card"><b>◷</b><div><strong>Class Schedule</strong><small>Build and print the weekly workload</small></div><span>→</span></a>
<a href="classrooms.php" class="quick-card"><b>⌂</b><div><strong>Classrooms</strong><small>Check room capacity and use</small></div><span>→</span></a>
<a href="instructors.php" class="quick-card"><b>♙</b><div><strong>Instructors</strong><small>Edit faculty availability</small></div><span>→</span></a>
</section>

<?php require '../includes/footer.php'; ?>
