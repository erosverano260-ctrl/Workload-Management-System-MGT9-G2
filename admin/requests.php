<?php
/* The chair's inbox: approve or reject faculty requests (designations, overload sessions, and schedule changes). */
$page = 'requests';$title = 'Faculty Requests';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/notifications.php';
require '../includes/notify.php';
require '../includes/schedule_helpers.php';
requireRole('admin');
date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
ensure_schedule_change_schema($conn);$t12  = fn(string $t) => date('g:i A', strtotime($t));

/* ---------- POST: decide on one request (post -> redirect -> get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $kind     =$_POST['kind'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $decision =$_POST['decision'] ?? '';
    $note     = mb_substr(preg_replace('/[^\x20-\x7E]/', '', post_str('note')), 0, 200);
    $noteTxt  = $note !== '' ? ' Note from the chair: ' . $note : '';

    if (!in_array($decision, ['Approved', 'Rejected'], true) || !in_array($kind, ['designation', 'overload', 'schedule_change'], true) || $id < 1) {
        flash_set('error', 'That request could not be processed.');
        redirect('requests.php');
    }
    $ok =$decision === 'Approved';
    try {
        notif_ensure($conn);
        ensure_approval_schema($conn);

        if ($kind === 'schedule_change') {
            $err = decide_schedule_change($conn, $id,$ok, $note);
            if ($err !== null) {
                flash_set('error', $err);
                redirect('requests.php');
            }
            $rq =$conn->prepare("SELECT faculty_id, old_day, old_start, old_end, new_day, new_start, new_end FROM schedule_change_requests WHERE request_id = ?");
            $rq->bind_param('i', $id);$rq->execute();
            if ($scRow =$rq->get_result()->fetch_assoc()) {
                $fromStr =$scRow['old_day'] . ' ' . $t12($scRow['old_start']) . ' - ' . $t12($scRow['old_end']);
                $toStr   =$scRow['new_day'] . ' ' . $t12($scRow['new_start']) . ' - ' . $t12($scRow['new_end']);
                notif_add(
                    $conn,
                    (int)$scRow['faculty_id'],$ok ? 'approved' : 'rejected',
                    $ok ? 'Schedule change approved' : 'Schedule change not approved',
                    'The chair ' . ($ok ? 'approved' : 'rejected') . ' moving your class from ' . $fromStr . ' to ' .$toStr . '.' . $noteTxt,
                    'schedule.php'
                );
                notify(
                    $conn,
                    'faculty',
                    (int)$scRow['faculty_id'],$ok ? 'approved' : 'rejected',
                    'The chair ' . ($ok ? 'approved' : 'rejected') . ' moving your class from ' . $fromStr . ' to ' .$toStr . '.' . $noteTxt,
                    'schedule.php'
                );
            }
            flash_set('success', 'Request ' . strtolower($decision) . '. The instructor has been notified.');
            redirect('requests.php');
        }

        $conn->begin_transaction();$done = false;

        if ($kind === 'designation') {
            $st =$conn->prepare("SELECT fd.faculty_id, d.name FROM faculty_designations fd
                                  JOIN designations d ON d.designation_id = fd.designation_id
                                  WHERE fd.fd_id = ? AND fd.status = 'Pending' FOR UPDATE");
            $st->bind_param('i', $id);$st->execute();
            if ($row =$st->get_result()->fetch_assoc()) {
                $st =$conn->prepare("UPDATE faculty_designations SET status = ? WHERE fd_id = ? AND status = 'Pending'");
                $st->bind_param('si',$decision, $id);$st->execute();
                $name =$row['name'];
                if ($ok) notif_add($conn, (int)$row['faculty_id'], 'approved', 'Designation approved',
                        'The chair approved your request for the designation "' . $name . '". It now prints in section C of your class program.' . $noteTxt, 'designations.php');
                else     notif_add($conn, (int)$row['faculty_id'], 'rejected', 'Designation not approved',
                        'The chair rejected your request for the designation "' . $name . '".' . $noteTxt . ' You can send the request again from My Designations.', 'designations.php');
                $done = true;
            }
        } else {
            $st =$conn->prepare("SELECT faculty_id, day_of_week, start_time, end_time FROM overload_sessions
                                  WHERE session_id = ? AND status = 'Pending' FOR UPDATE");
            $st->bind_param('i', $id);$st->execute();
            if ($row =$st->get_result()->fetch_assoc()) {
                $st =$conn->prepare("UPDATE overload_sessions SET status = ? WHERE session_id = ? AND status = 'Pending'");
                $st->bind_param('si',$decision, $id);$st->execute();
                $when =$row['day_of_week'] . ', ' . $t12($row['start_time']) . ' - ' . $t12($row['end_time']);
                if ($ok) notif_add($conn, (int)$row['faculty_id'], 'approved', 'Overload session approved',
                        'The chair approved your overload session on ' . $when . '.' . $noteTxt, 'overload.php');
                else     notif_add($conn, (int)$row['faculty_id'], 'rejected', 'Overload session not approved',
                        'The chair rejected your overload session on ' . $when . '.' . $noteTxt . ' You can add a different session from Overload Sessions.', 'overload.php');
                $done = true;
            }
        }
        $conn->commit();
        flash_set($done ? 'success' : 'error', $done ? ('Request ' . strtolower($decision) . '. The instructor has been notified.') : 'That request was already decided or no longer exists.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable$e2) { /* ignore */ }
        flash_set('error', 'Could not save the decision. Please try again.');
    }
    redirect('requests.php');
}

/* ---------- Load ---------- */
$designations =$conn->query("SELECT fd.fd_id, fd.created_at, f.faculty_name, d.name, d.hours, d.service_credit
                              FROM faculty_designations fd
                              JOIN faculty f ON f.faculty_id = fd.faculty_id
                              JOIN designations d ON d.designation_id = fd.designation_id
                              WHERE fd.status = 'Pending' ORDER BY fd.created_at, fd.fd_id")->fetch_all(MYSQLI_ASSOC);
$sessions =$conn->query("SELECT o.session_id, o.day_of_week, o.start_time, o.end_time, o.created_at, f.faculty_name
                          FROM overload_sessions o JOIN faculty f ON f.faculty_id = o.faculty_id
                          WHERE o.status = 'Pending' ORDER BY o.created_at, o.session_id")->fetch_all(MYSQLI_ASSOC);
$scheduleChanges =$conn->query("SELECT r.request_id, r.old_day, r.old_start, r.old_end, r.new_day, r.new_start, r.new_end, r.faculty_note, r.created_at, f.faculty_name
                                 FROM schedule_change_requests r
                                 JOIN faculty f ON f.faculty_id = r.faculty_id
                                 WHERE r.status = 'Pending' ORDER BY r.created_at, r.request_id")->fetch_all(MYSQLI_ASSOC);
$recent = [];
try {
    notif_ensure($conn);
    $recent =$conn->query("SELECT n.kind, n.title, n.body, n.created_at, f.faculty_name
                            FROM notifications n JOIN faculty f ON f.faculty_id = n.faculty_id
                            WHERE n.kind IN ('approved','rejected') ORDER BY n.notif_id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {$recent = []; }
$pending = count($designations) + count($sessions) + count($scheduleChanges);

require '../includes/header.php';
$flash = flash_get();
?>
<link rel="stylesheet" href="../assets/ui.css">
<style>
.rq-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.rq-form input[type=text] { flex: 1; min-width: 150px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 9px; font: inherit; font-size: 13px; background: #fff; }
.rq-form .primary-btn, .rq-form .danger-btn { padding: 8px 14px; font-size: 13px; }
.rq-card { margin-bottom: 22px; }
.rq-card h3 { margin: 0 0 4px; font-size: 16px; }
.rq-sub { margin: 0 0 10px; color: var(--muted); font-size: 13px; }
.rq-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.rq-pill.approved { background: #dff5e7; color: #1f7a45; }
.rq-pill.rejected { background: var(--red-bg); color: var(--red); }
.rq-count { display: inline-block; min-width: 22px; padding: 1px 8px; border-radius: 999px; background: var(--amber); color: var(--navy); font-size: 12px; font-weight: 800; text-align: center; vertical-align: middle; margin-left: 6px; }
.rq-diff { color: #1d4ed8; font-weight: 700; }
</style>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">CHAIR APPROVALS</span><h2>Faculty requests <?php if ($pending): ?><span class="rq-count"><?= $pending ?></span><?php endif; ?></h2></div>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<div class="ui-notice">Approve or reject each request. The instructor gets a notification straight away, and you can add a short note that is included in it.</div>

<div class="rq-card">
  <h3>Schedule change requests</h3>
  <p class="rq-sub">Compare the instructor's current assigned slot against the requested drag move before approving or rejecting.</p>
  <div class="table-card">
  <?php if (!$scheduleChanges): ?><div class="empty">No pending schedule change requests.</div><?php else: ?>
  <table>
    <tr><th>INSTRUCTOR</th><th>CURRENT</th><th>REQUESTED CHANGE</th><th>NOTE</th><th>REQUESTED</th><th>DECISION</th></tr>
    <?php foreach ($scheduleChanges as$r):
      $dayChanged  =$r['old_day'] !== $r['new_day'];$timeChanged = ($r['old_start'] !==$r['new_start']) || ($r['old_end'] !==$r['new_end']);
    ?>
    <tr>
      <td><strong><?= h($r['faculty_name']) ?></strong></td>
      <td>
        <strong><?= h($r['old_day']) ?></strong><br>
        <?= h($t12($r['old_start'])) ?> - <?= h($t12($r['old_end'])) ?>
      </td>
      <td>
        <strong class="<?= $dayChanged ? 'rq-diff' : '' ?>"><?= h($r['new_day']) ?></strong><br>
        <span class="<?= $timeChanged ? 'rq-diff' : '' ?>"><?= h($t12($r['new_start'])) ?> - <?= h($t12($r['new_end'])) ?></span>
      </td>
      <td><?= h($r['faculty_note'] ?: '-') ?></td>
      <td><?= h(notif_ago($r['created_at'])) ?></td>
      <td>
        <form method="post" class="rq-form"><?= csrf_field() ?>
          <input type="hidden" name="kind" value="schedule_change"><input type="hidden" name="id" value="<?= (int)$r['request_id'] ?>">
          <input type="text" name="note" maxlength="200" placeholder="Note to instructor (optional)">
          <button type="submit" name="decision" value="Approved" class="primary-btn">Approve</button>
          <button type="submit" name="decision" value="Rejected" class="danger-btn">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>



<div class="rq-card">
  <h3>Recent notifications</h3>
  <div class="table-card">
  <?php if (!$recent): ?><div class="empty">No recent notifications.</div><?php else: ?>
  <table>
    <tr><th>TYPE</th><th>TITLE</th><th>DETAILS</th><th>DATE</th></tr>
    <?php foreach ($recent as$r): ?>
    <tr>
      <td><span class="rq-pill <?= h($r['kind']) ?>"><?= h($r['kind']) ?></span></td>
      <td><?= h($r['title']) ?></td>
      <td><?= h($r['body']) ?></td>
      <td><?= h(notif_ago($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>

</section>

<?php require '../includes/footer.php';
$conn->close();
?>