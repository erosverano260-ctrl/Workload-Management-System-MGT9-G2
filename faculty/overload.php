<?php
$page = 'overload'; $title = 'Overload Sessions';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/approvals.php';       // workload_summary(), load_program_entries(), load_overload_sessions(), nf(), faculty_load()
requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
$inst = loadInstructor($conn, currentUserId());
$fid  = (int)$inst['faculty_id'];                       // always the signed-in instructor, never taken from the form
ensure_approval_schema($conn);

$entries = load_program_entries($conn, 'faculty', $fid);
$w       = workload_summary($entries);
$claim   = (int)$w['claim'];                            // whole overload hours = how many 1-hour sessions can be designated

/* ---------- POST (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_session') {
            $day  = $_POST['day_of_week'] ?? '';
            $hour = (int)($_POST['start_hour'] ?? 0);
            $s = $hour * 60; $e = $s + 60;
            $mine = array_filter(load_overload_sessions($conn, $fid), fn($x) => $x['status'] !== 'Rejected');

            $clash = false;                              // an instructor can't be in two places at once
            foreach ($entries as $c) if ($c['day_of_week'] === $day && $s < timeToMin($c['end_time']) && $e > timeToMin($c['start_time'])) $clash = true;
            foreach ($mine as $x)    if ($x['day_of_week'] === $day && $s < timeToMin($x['end_time']) && $e > timeToMin($x['start_time'])) $clash = true;

            if (!in_array($day, FAC_DAYS, true) || $hour < 7 || $hour > 18) flash_set('error', 'Pick a weekday and a start time between 7 AM and 6 PM.');
            elseif ($hour === 12)           flash_set('error', 'A session cannot be held during lunch (12 to 1 PM).');
            elseif (count($mine) >= $claim) flash_set('error', 'Your overload hours are already fully covered.');
            elseif ($clash)                 flash_set('error', 'That hour overlaps one of your classes or another overload session.');
            else {
                $start = sprintf('%02d:00:00', $hour); $end = sprintf('%02d:00:00', $hour + 1);
                $st = $conn->prepare("INSERT INTO overload_sessions (faculty_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                $st->bind_param('isss', $fid, $day, $start, $end);
                $st->execute();
                flash_set('success', 'Overload session added. It is waiting for the chair.');
                notify_admins($conn, 'request', $inst['faculty_name'] . ' added an overload session: ' . $day . ' ' . date('g:i A', mktime($hour, 0)) . ' - ' . date('g:i A', mktime($hour + 1, 0)) . '.', 'approvals.php?tab=sessions');
            }
        } elseif ($action === 'delete_session') {
            $id = (int)($_POST['session_id'] ?? 0);
            $st = $conn->prepare("DELETE FROM overload_sessions WHERE session_id = ? AND faculty_id = ? AND status = 'Pending'");   // only their own, only while pending
            $st->bind_param('ii', $id, $fid);
            $st->execute();
            flash_set('success', $st->affected_rows ? 'Session removed.' : 'That session can no longer be removed.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', 'Could not save your changes. Please try again.');
    }
    redirect('overload.php');
}

$sessions = load_overload_sessions($conn, $fid);
$used = count(array_filter($sessions, fn($x) => $x['status'] !== 'Rejected'));
$left = max(0, $claim - $used);
$busy = [];                                   // hours already taken per day, e.g. ['Monday' => [7, 15, 16], ...]
$taken = array_merge($entries, array_filter($sessions, fn($x) => $x['status'] !== 'Rejected'));
foreach (FAC_DAYS as $d) {
    $busy[$d] = [];
    for ($hr = 7; $hr <= 18; $hr++) {
        foreach ($taken as $b) {
            if ($b['day_of_week'] === $d && $hr * 60 < timeToMin($b['end_time']) && $hr * 60 + 60 > timeToMin($b['start_time'])) { $busy[$d][] = $hr; break; }
        }
    }
}
$pct = $claim > 0 ? min(100, round($used / $claim * 100)) : 0;
$t12  = fn(string $t) => date('g:i A', strtotime($t));

require '../includes/faculty_header.php';
$flash = flash_get();
?>



<style>
.ov-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
.ov-stat { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; }
.ov-stat small { display: block; color: var(--muted); font-size: 12px; margin-bottom: 4px; }
.ov-stat strong { font-size: 22px; color: var(--navy); }
.ov-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.ov-table th, .ov-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); }
.f-field select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; font: inherit; background: #fff; }
.ov-progress { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; margin-bottom: 18px; }
.ov-progress-top { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: var(--muted); margin-bottom: 8px; }
.ov-progress-top strong { color: var(--navy); }
.ov-bar { height: 12px; background: var(--bg); border-radius: 999px; overflow: hidden; }
.ov-bar span { display: block; height: 100%; background: var(--amber); border-radius: 999px; transition: width .4s ease; }
.ov-bar.done span { background: #2e9e5b; }
</style>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">TEACHING OVERLOAD</span><h2>Overload sessions</h2></div>
    <a href="print.php" class="primary-btn" style="text-decoration:none">📄 Official class program</a>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="ov-stats">
  <div class="ov-stat"><small>Computed load (lec + 0.7 × lab)</small><strong><?= nf($w['comp']) ?></strong></div>
  <div class="ov-stat"><small>Regular load (<?= (int)$w['preps'] ?> preps)</small><strong><?= nf($w['allowed']) ?></strong></div>
  <div class="ov-stat"><small>Teaching overload</small><strong><?= nf($w['overload']) ?></strong></div>
  <div class="ov-stat"><small>Hours to claim</small><strong><?= $claim ?></strong></div>
  <div class="ov-stat"><small>Teaching service credit</small><strong><?= nf($w['credit']) ?></strong></div>
  <div class="ov-stat"><small>Sessions designated</small><strong><?= $used ?> / <?= $claim ?></strong></div>
</div>

<?php if ($claim > 0): ?>
<div class="ov-progress">
  <div class="ov-progress-top"><span>Overload coverage</span><strong><?= $used ?> of <?= $claim ?> hours designated (<?= $pct ?>%)</strong></div>
  <div class="ov-bar<?= $left < 1 ? ' done' : '' ?>" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $claim ?>" aria-valuenow="<?= $used ?>"><span style="width:<?= $pct ?>%"></span></div>
</div>
<?php endif; ?>

<div class="profile-grid">
  <form method="post" class="f-card f-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_session">
    <div><h3>Add a session</h3><p class="f-sub">Each session is exactly 1 hour (only 1-hour sessions are accepted). Add one for every overload hour you want to claim; the chair approves or rejects each one and you get a notification.</p></div>
    <?php if ($claim < 1): ?>
      <p class="f-hint">You have no whole overload hour to claim right now.</p>
    <?php elseif ($left < 1): ?>
      <p class="f-hint">Your overload hours are fully covered.</p>
    <?php else: ?>
    <div class="f-grid2">
      <div class="f-field"><label for="day">Day</label>
        <select id="day" name="day_of_week" required>
          <?php foreach (FAC_DAYS as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?>
        </select></div>
      <div class="f-field"><label for="hr">Time</label>
        <select id="hr" name="start_hour" required>
          <?php for ($hr = 7; $hr <= 18; $hr++): if ($hr === 12) continue; ?>
          <option value="<?= $hr ?>"><?= date('g:i A', mktime($hr, 0)) ?> – <?= date('g:i A', mktime($hr + 1, 0)) ?></option>
          <?php endfor; ?>
        </select></div>
    </div>
     <p class="f-hint" id="noHour" hidden>No free hour is left on this day. Pick another day.</p>
    <div class="f-actions"><button type="submit" class="primary-btn" id="addBtn">Add session</button></div>
    <?php endif; ?>
    
  </form>

  <div class="f-card">
    <h3>My designated sessions</h3>
    <p class="f-sub">Total: <?= $used ?> hour<?= $used === 1 ? '' : 's' ?> designated, <?= $left ?> still to cover.</p>
    <?php if (!$sessions): ?>
      <p class="f-hint">No sessions yet.</p>
    <?php else: ?>
    <table class="ov-table">
      <tr><th>Day</th><th>Time</th><th>Status</th><th></th></tr>
      <?php foreach ($sessions as $x): ?>
      <tr>
        <td><?= h($x['day_of_week']) ?></td>
        <td><?= h($t12($x['start_time'])) ?> – <?= h($t12($x['end_time'])) ?></td>
        <td><span class="ds-badge <?= h(strtolower($x['status'])) ?>"><?= h($x['status']) ?></span><?php if (!empty($x['decision_note'])): ?><br><small class="muted">Chair: <?= h($x['decision_note']) ?></small><?php endif; ?></td>
        <td><?php if ($x['status'] === 'Pending'): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_session">
            <input type="hidden" name="session_id" value="<?= (int)$x['session_id'] ?>">
            <button type="submit" class="danger-btn">Remove</button></form>
        <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
</section>


<script>
const BUSY = <?= json_encode($busy) ?>;
const daySel = document.getElementById('day'), hrSel = document.getElementById('hr');
const addBtn = document.getElementById('addBtn'), noHour = document.getElementById('noHour');

function filterHours() {
    const taken = BUSY[daySel.value] || [];
    let firstFree = null;
    [...hrSel.options].forEach(o => {
        const isBusy = taken.includes(+o.value);
        o.hidden = isBusy; o.disabled = isBusy;           // both, so it also works in Safari
        if (!isBusy && firstFree === null) firstFree = o;
    });
    if (hrSel.selectedOptions[0] && hrSel.selectedOptions[0].disabled) hrSel.value = firstFree ? firstFree.value : '';
    addBtn.disabled = firstFree === null;
    noHour.hidden = firstFree !== null;
}
if (daySel && hrSel) { daySel.addEventListener('change', filterHours); filterHours(); }
</script>
<?php require '../includes/footer.php'; ?>