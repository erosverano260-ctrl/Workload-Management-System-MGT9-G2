<?php
$page = 'approvals'; $title = 'Approvals';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/approvals.php';
requireRole('admin');
date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
ensure_approval_schema($conn);
$tab = ($_GET['tab'] ?? 'designations') === 'sessions' ? 'sessions' : 'designations';

/* ---------- POST: the chair approves or rejects (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = $_POST['action'] ?? '';
    $approve = ($_POST['decision'] ?? '') === 'approve';
    $note    = mb_substr(post_str('note'), 0, 200);
    $word    = $approve ? 'approved' : 'rejected';
    try {
        if ($action === 'design_decide') {
            $tab = 'designations';
            $err = decide_designation($conn, (int)($_POST['fd_id'] ?? 0), $approve, $note);
            $err ? flash_set('error', $err) : flash_set('success', 'Designation request ' . $word . '. The instructor was notified.');
        } elseif ($action === 'session_decide') {
            $tab = 'sessions';
            $err = decide_session($conn, (int)($_POST['session_id'] ?? 0), $approve, $note);
            $err ? flash_set('error', $err) : flash_set('success', 'Overload session ' . $word . '. The instructor was notified.');
        } elseif ($action === 'session_bulk') {                      // every pending session of one instructor, in order
            $tab = 'sessions';
            $fid = (int)($_POST['faculty_id'] ?? 0);
            $st = $conn->prepare("SELECT session_id FROM overload_sessions WHERE faculty_id = ? AND status = 'Pending' ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), start_time");
            $st->bind_param('i', $fid); $st->execute();
            $ok = 0; $errs = [];
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $e = decide_session($conn, (int)$row['session_id'], $approve, $note);
                $e ? $errs[] = $e : $ok++;
            }
            if ($errs) flash_set($ok ? 'success' : 'error', ucfirst($word) . " $ok session(s). Not done: " . implode(' | ', array_unique($errs)));
            else       flash_set('success', ucfirst($word) . " $ok session(s). The instructor was notified.");
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', 'Could not save that decision. Please try again.');
    }
    redirect('approvals.php?tab=' . $tab);
}

/* ---------- data ---------- */
$designations = $conn->query("SELECT fd.fd_id, fd.created_at, f.faculty_name, d.name, d.hours, d.service_credit
                              FROM faculty_designations fd JOIN faculty f ON f.faculty_id = fd.faculty_id JOIN designations d ON d.designation_id = fd.designation_id
                              WHERE fd.status = 'Pending' ORDER BY fd.created_at")->fetch_all(MYSQLI_ASSOC);

$sessRows = $conn->query("SELECT os.session_id, os.faculty_id, os.day_of_week, os.start_time, os.end_time, f.faculty_name
                          FROM overload_sessions os JOIN faculty f ON f.faculty_id = os.faculty_id
                          WHERE os.status = 'Pending'
                          ORDER BY f.faculty_name, FIELD(os.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), os.start_time")->fetch_all(MYSQLI_ASSOC);
$byFaculty = [];
foreach ($sessRows as $r) $byFaculty[(int)$r['faculty_id']][] = $r;

$history = $conn->query("(SELECT 'Designation' AS kind, f.faculty_name, d.name AS what, fd.status, fd.decided_at, fd.decision_note
                          FROM faculty_designations fd JOIN faculty f ON f.faculty_id = fd.faculty_id JOIN designations d ON d.designation_id = fd.designation_id WHERE fd.status <> 'Pending')
                         UNION ALL
                         (SELECT 'Overload session', f.faculty_name, CONCAT(os.day_of_week, ' ', TIME_FORMAT(os.start_time, '%l:%i %p'), ' - ', TIME_FORMAT(os.end_time, '%l:%i %p')), os.status, os.decided_at, os.decision_note
                          FROM overload_sessions os JOIN faculty f ON f.faculty_id = os.faculty_id WHERE os.status <> 'Pending')
                         ORDER BY decided_at DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);

require '../includes/header.php';
$flash = flash_get();
$t12 = fn(string $t) => date('g:i A', strtotime($t));
?>
<link rel="stylesheet" href="<?= asset('portal.css') ?>">
<script src="<?= asset('ui.js') ?>"></script>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">CHAIR'S APPROVALS</span><h2>Requests waiting for you</h2></div>
</div>
<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<nav class="ap-tabs">
    <a href="?tab=designations" class="<?= $tab === 'designations' ? 'on' : '' ?>">Designation requests <span class="ap-count"><?= count($designations) ?></span></a>
    <a href="?tab=sessions" class="<?= $tab === 'sessions' ? 'on' : '' ?>">Overload sessions <span class="ap-count"><?= count($sessRows) ?></span></a>
</nav>

<?php if ($tab === 'designations'): ?>
  <?php if (!$designations): ?><div class="empty">No designation requests are waiting.</div>
  <?php else: foreach ($designations as $d):
      $credit = designation_credit($d); ?>
  <div class="ap-card">
    <div class="ap-main">
      <strong><?= h($d['faculty_name']) ?></strong> asked for <strong><?= h($d['name']) ?></strong>
      <small><?= nf($d['hours']) ?> hrs a week · <?= designation_is_chair_or_dean($d['name']) ? 'no service credit (Chair / Dean)' : nf($credit) . ' service credit (extra pay, outside the 40 hrs)' ?> · requested <?= h(time_ago($d['created_at'])) ?></small>
    </div>
    <form method="post" class="ap-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="design_decide"><input type="hidden" name="fd_id" value="<?= (int)$d['fd_id'] ?>">
      <input type="text" name="note" maxlength="200" placeholder="Note to the instructor (optional)" aria-label="Note">
      <button type="submit" name="decision" value="approve" class="primary-btn">Approve</button>
      <button type="submit" name="decision" value="reject" class="danger-btn">Reject</button>
    </form>
  </div>
  <?php endforeach; endif; ?>

<?php else: ?>
  <?php if (!$byFaculty): ?><div class="empty">No overload sessions are waiting.</div>
  <?php else: foreach ($byFaculty as $fid => $list):
      $L = faculty_load_for($conn, $fid); ?>
  <div class="ap-card ap-block">
    <div class="ap-main">
      <strong><?= h($list[0]['faculty_name']) ?></strong>
      <small>Overload <?= nf($L['overload']) ?> hrs → <?= nf($L['claim']) ?> hr(s) to claim · approved <?= nf($L['sessApproved']) ?> · waiting <?= nf($L['sessPending']) ?> · teaching service credit <?= nf($L['credit']) ?></small>
    </div>
    <table class="ap-table">
      <?php foreach ($list as $s): ?>
      <tr>
        <td><?= h($s['day_of_week']) ?></td><td><?= h($t12($s['start_time'])) ?> – <?= h($t12($s['end_time'])) ?></td><td><?= nf(session_hours($s)) ?> hr</td>
        <td>
          <form method="post" class="ap-form inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="session_decide"><input type="hidden" name="session_id" value="<?= (int)$s['session_id'] ?>">
            <input type="text" name="note" maxlength="200" placeholder="Note (optional)" aria-label="Note">
            <button type="submit" name="decision" value="approve" class="primary-btn">Approve</button>
            <button type="submit" name="decision" value="reject" class="danger-btn">Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (count($list) > 1): ?>
    <form method="post" class="ap-form ap-bulk">
      <?= csrf_field() ?><input type="hidden" name="action" value="session_bulk"><input type="hidden" name="faculty_id" value="<?= (int)$fid ?>">
      <span>All <?= count($list) ?> sessions of <?= h($list[0]['faculty_name']) ?>:</span>
      <button type="submit" name="decision" value="approve" class="outline-btn">Approve all</button>
      <button type="submit" name="decision" value="reject" class="outline-btn">Reject all</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; endif; ?>
<?php endif; ?>

<h3 class="ap-h">Recent decisions</h3>
<?php if (!$history): ?><div class="empty">Nothing decided yet.</div>
<?php else: ?>
<div class="table-card"><table>
  <thead><tr><th>INSTRUCTOR</th><th>REQUEST</th><th>DECISION</th><th>WHEN</th><th>NOTE</th></tr></thead>
  <tbody>
  <?php foreach ($history as $x): ?>
  <tr><td><?= h($x['faculty_name']) ?></td><td><?= h($x['kind']) ?>: <?= h($x['what']) ?></td>
      <td><span class="ds-badge <?= h(strtolower($x['status'])) ?>"><?= h($x['status']) ?></span></td>
      <td><?= $x['decided_at'] ? h(time_ago($x['decided_at'])) : '' ?></td><td><?= h($x['decision_note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</section>

<?php require '../includes/footer.php'; ?>
