<?php
$page = 'designations'; $title = 'My Designations';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/approvals.php';              // nf(), designation_credit(); also loads print_helpers + schedule_helpers
requireRole('faculty');

$conn = (new database())->connect();
$inst = loadInstructor($conn, currentUserId());
$fid  = (int)$inst['faculty_id'];                  // always the signed-in instructor, never taken from the form
ensure_approval_schema($conn);

/* ---------- POST (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'request') {
            $did = (int)($_POST['designation_id'] ?? 0);
            $st = $conn->prepare("SELECT name FROM designations WHERE designation_id = ?");
            $st->bind_param('i', $did); $st->execute();
            $dname = $st->get_result()->fetch_assoc()['name'] ?? null;
            if ($dname === null) { flash_set('error', 'Pick a designation from the list.'); redirect('designations.php'); }

            $st = $conn->prepare("SELECT status FROM faculty_designations WHERE faculty_id = ? AND designation_id = ?");
            $st->bind_param('ii', $fid, $did); $st->execute();
            $cur = $st->get_result()->fetch_assoc()['status'] ?? null;

            if ($cur === 'Pending' || $cur === 'Approved') {
                flash_set('error', 'You already have that designation, or a request for it is waiting for the chair.');
            } elseif ($cur === 'Rejected') {                 // a rejected request can be sent again
                $st = $conn->prepare("UPDATE faculty_designations SET status = 'Pending' WHERE faculty_id = ? AND designation_id = ?");
                $st->bind_param('ii', $fid, $did); $st->execute();
                flash_set('success', 'Request sent again. It is waiting for the chair.');
                notify_admins($conn, 'request', $inst['faculty_name'] . ' asked again for the designation "' . $dname . '".', 'approvals.php?tab=designations');
            } else {
                $st = $conn->prepare("INSERT INTO faculty_designations (faculty_id, designation_id) VALUES (?, ?)");
                $st->bind_param('ii', $fid, $did); $st->execute();
                flash_set('success', 'Request sent. It is waiting for the chair.');
                notify_admins($conn, 'request', $inst['faculty_name'] . ' requested the designation "' . $dname . '".', 'approvals.php?tab=designations');
            }
        } elseif ($action === 'cancel') {
            $id = (int)($_POST['fd_id'] ?? 0);
            $st = $conn->prepare("DELETE FROM faculty_designations WHERE fd_id = ? AND faculty_id = ? AND status = 'Pending'");   // only their own, only while pending
            $st->bind_param('ii', $id, $fid); $st->execute();
            flash_set('success', $st->affected_rows ? 'Request cancelled.' : 'That request can no longer be cancelled.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', 'Could not save your request. Please try again.');
    }
    redirect('designations.php');
}

$all = $conn->query("SELECT designation_id, name, hours, service_credit FROM designations ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$st = $conn->prepare("SELECT fd.fd_id, fd.designation_id, fd.status, fd.decision_note, d.name, d.hours, d.service_credit
                      FROM faculty_designations fd JOIN designations d ON d.designation_id = fd.designation_id
                      WHERE fd.faculty_id = ? ORDER BY fd.created_at DESC");
$st->bind_param('i', $fid); $st->execute();
$mine = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$blocked = [];                                       // can't be requested again while Pending or Approved
foreach ($mine as $m) if ($m['status'] !== 'Rejected') $blocked[$m['designation_id']] = true;
$options = array_filter($all, fn($d) => !isset($blocked[$d['designation_id']]));

$apprHrs = 0.0; $apprCredit = 0.0;
foreach ($mine as $m) if ($m['status'] === 'Approved') { $apprHrs += (float)$m['hours']; $apprCredit += designation_credit($m); }

require '../includes/faculty_header.php';
$flash = flash_get();
?>
<style>
.ds-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.ds-table th, .ds-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); }
.ds-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.ds-badge.pending  { background: #fff4d6; color: #8a6100; }
.ds-badge.approved { background: #dff5e7; color: #1f7a45; }
.ds-badge.rejected { background: var(--red-bg); color: var(--red); }
.f-field select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; font: inherit; background: #fff; }
</style>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">SECTION C</span><h2>My designations</h2></div>
    <a href="print.php" class="primary-btn" style="text-decoration:none">📄 Official class program</a>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<div class="ui-notice">An approved designation prints in section C of your class program. Every request has to be approved by the chair first, and you get a notification with the decision. Designation hours count inside your <?= FACULTY_WEEK_HOURS ?>-hour week; service credit is extra pay outside it (Chair and Dean earn none).</div>

<div class="profile-grid">
  <form method="post" class="f-card f-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="request">
    <div><h3>Request a designation</h3><p class="f-sub">The chair reviews each request. You can cancel it while it is still pending.</p></div>
    <?php if (!$all): ?>
      <p class="f-hint">No designations have been set up yet.</p>
    <?php elseif (!$options): ?>
      <p class="f-hint">You already have, or have requested, every designation.</p>
    <?php else: ?>
    <div class="f-field"><label for="des">Designation</label>
      <select id="des" name="designation_id" required>
        <?php foreach ($options as $d): ?>
        <option value="<?= (int)$d['designation_id'] ?>"><?= h($d['name']) ?> (<?= nf($d['hours']) ?> hrs, <?= nf(designation_credit($d)) ?> credit)</option>
        <?php endforeach; ?>
      </select></div>
    <div class="f-actions"><button type="submit" class="primary-btn">Send request</button></div>
    <?php endif; ?>
  </form>

  <div class="f-card">
    <h3>My requests</h3>
    <p class="f-sub">Approved so far: <?= nf($apprHrs) ?> hours and <?= nf($apprCredit) ?> service credit.</p>
    <?php if (!$mine): ?>
      <p class="f-hint">No requests yet.</p>
    <?php else: ?>
    <table class="ds-table">
      <tr><th>Designation</th><th>Hours</th><th>Credit</th><th>Status</th><th></th></tr>
      <?php foreach ($mine as $m): ?>
      <tr>
        <td><?= h($m['name']) ?></td><td><?= nf($m['hours']) ?></td><td><?= nf(designation_credit($m)) ?></td>
        <td><span class="ds-badge <?= h(strtolower($m['status'])) ?>"><?= h($m['status']) ?></span><?php if (!empty($m['decision_note'])): ?><br><small class="muted">Chair: <?= h($m['decision_note']) ?></small><?php endif; ?></td>
        <td><?php if ($m['status'] === 'Pending'): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="fd_id" value="<?= (int)$m['fd_id'] ?>">
            <button type="submit" class="danger-btn">Cancel</button></form>
        <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
</section>

<?php require '../includes/footer.php'; ?>