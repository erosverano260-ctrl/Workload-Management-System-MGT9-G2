<?php
$page='rooms';$title='Classroom Availability';
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/ui.php';
requireRole('faculty');

$db = new database();
$conn = $db->connect();

/* ---------- POST actions (post → redirect → get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_room' || $action === 'edit_room') {
            $code = post_str('room_code'); $name = post_str('room_name'); $cap = (int)($_POST['capacity'] ?? 0);
            if ($name === '')                       { flash_set('error', 'Room name is required.'); redirect('classrooms.php'); }
            if (strlen($code) > 100 || strlen($name) > 50) { flash_set('error', 'Room name can be at most 50 characters.'); redirect('classrooms.php'); }
            if ($cap < 1 || $cap > 500)             { flash_set('error', 'Capacity must be between 1 and 500.'); redirect('classrooms.php'); }

            if ($action === 'add_room') {
                $stmt = $conn->prepare("INSERT INTO rooms (room_code, room_name, capacity) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $code, $name, $cap);
                $stmt->execute();
                flash_set('success', 'Room added.');
            } else {
                $stmt = $conn->prepare("UPDATE rooms SET room_code=?, room_name=?, capacity=? WHERE room_id=?");
                $stmt->bind_param("ssii", $code, $name, $cap, $_POST['room_id']);
                $stmt->execute();
                flash_set('success', 'Room updated.');
            }
        } elseif ($action === 'delete_room') {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id=?");
            $stmt->bind_param("i", $_POST['room_id']);
            $stmt->execute();
            flash_set('success', 'Room deleted.');
        }
    } catch (mysqli_sql_exception $e) {
        flash_set('error', db_error_message($e, 'room'));
    }
    redirect('classrooms.php');
}

require 'includes/header.php';
$flash = flash_get();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE room_id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

$sql = "SELECT r.*,
               COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time))) / 3600, 0) AS booked_hours
        FROM rooms r
        LEFT JOIN schedules s ON s.room_id = r.room_id
        GROUP BY r.room_id
        ORDER BY r.room_name";
$rooms = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

const AVAILABLE_HOURS_PER_WEEK = 60; // 7am-7pm, 5 days a week

/** Edit + Delete buttons, side by side. */
function roomButtons(array $r): string {
    $id = (int)$r['room_id'];
    return '<div class="btn-pair">'
        . '<a href="?edit=' . $id . '" class="outline-btn js-edit-room" data-id="' . $id . '" data-code="' . h($r['room_code']) . '" data-name="' . h($r['room_name']) . '" data-capacity="' . h($r['capacity']) . '">Edit</a>'
        . '<form method="post" data-confirm="Delete ' . h($r['room_name']) . '? This can\'t be undone.">'
        . '<input type="hidden" name="action" value="delete_room"><input type="hidden" name="room_id" value="' . $id . '">'
        . '<button type="submit" class="outline-btn danger">Delete</button></form></div>';
}
?>
<link rel="stylesheet" href="assets/ui.css">
<script src="assets/ui.js"></script>

<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">SPACE MANAGEMENT</span><h2>Classroom availability</h2></div>
    <button class="primary-btn" id="openAddRoom" type="button">+ Add Room</button>
</div>

<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<div class="room-grid">
<?php foreach($rooms as $r):
    $percent = min(100, round(($r['booked_hours'] / AVAILABLE_HOURS_PER_WEEK) * 100));
?>
<div class="room-card">
    <div class="room-icon">⌂</div>
    <div class="room-head">
        <div><h3><?=h($r['room_name'])?></h3><small><?=h($r['room_code'])?></small></div>
    </div>
    <div class="room-numbers">
        <span><b><?=h($r['capacity'])?></b> Capacity</span>
    </div>
    <div class="room-numbers">
        <span><?= round($r['booked_hours'], 1) ?> of <?= AVAILABLE_HOURS_PER_WEEK ?> hrs booked this week</span>
    </div>
    <div class="progress wide"><span data-width="<?= $percent ?>"></span></div>
    <small class="muted"><?= $percent ?>% utilization</small>
    <div class="card-actions"><?= roomButtons($r) ?></div>
</div>
<?php endforeach;?>
</div>
<?php if(!$rooms):?><div class="empty">No rooms found.</div><?php endif;?>

<div class="table-card">
<table>
<thead><tr><th>CODE</th><th>ROOM</th><th>CAPACITY</th><th></th></tr></thead>
<tbody>
<?php foreach($rooms as $r): ?>
<tr>
    <td><?=h($r['room_code'])?></td>
    <td><strong><?=h($r['room_name'])?></strong></td>
    <td><?=h($r['capacity'])?></td>
    <td><?= roomButtons($r) ?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</section>

<!-- ADD / EDIT ROOM MODAL -->
<div class="ui-modal" id="roomModal" role="dialog" aria-modal="true" aria-labelledby="roomModalTitle" aria-hidden="true" <?= $editing ? 'data-autoopen' : '' ?>>
  <div class="ui-modal-card">
    <header class="ui-modal-head">
      <span class="ui-modal-icon">⌂</span>
      <div>
        <h2 id="roomModalTitle"><?= $editing ? 'Edit room' : 'Add room' ?></h2>
        <p id="roomModalSub"><?= $editing ? 'Update the room details, then save.' : 'Rooms appear in the workload scheduler.' ?></p>
      </div>
      <button type="button" class="ui-modal-x" data-close-modal aria-label="Close">×</button>
    </header>

    <form method="post" class="ui-form">
      <input type="hidden" name="action" id="rfAction" value="<?= $editing ? 'edit_room' : 'add_room' ?>">
      <input type="hidden" name="room_id" id="rfId" value="<?= h($editing['room_id'] ?? '') ?>">

      <div class="ui-grid">
        <div class="ui-field">
          <label class="ui-label" for="rfCode">Room code</label>
          <input type="text" id="rfCode" name="room_code" required maxlength="100" value="<?= h($editing['room_code'] ?? '') ?>" placeholder="e.g. R101">
        </div>
        <div class="ui-field">
          <label class="ui-label" for="rfCap">Capacity</label>
          <input type="number" id="rfCap" name="capacity" required min="1" max="500" value="<?= h($editing['capacity'] ?? 40) ?>">
        </div>
        <div class="ui-field span2">
          <label class="ui-label" for="rfName">Room name</label>
          <input type="text" id="rfName" name="room_name" required maxlength="50" value="<?= h($editing['room_name'] ?? '') ?>" placeholder="e.g. Room 101">
        </div>
      </div>

      <div class="ui-actions">
        <button type="button" class="ui-btn" data-close-modal>Cancel</button>
        <button type="submit" class="ui-btn primary" id="rfSubmit"><?= $editing ? 'Save changes' : 'Add room' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const modal = document.getElementById('roomModal');
    const $ = id => document.getElementById(id);

    function setMode(edit, d) {
        d = d || {};
        $('roomModalTitle').textContent = edit ? 'Edit room' : 'Add room';
        $('roomModalSub').textContent   = edit ? 'Update the room details, then save.' : 'Rooms appear in the workload scheduler.';
        $('rfSubmit').textContent       = edit ? 'Save changes' : 'Add room';
        $('rfAction').value = edit ? 'edit_room' : 'add_room';
        $('rfId').value     = edit ? d.id : '';
        $('rfCode').value   = edit ? d.code : '';
        $('rfName').value   = edit ? d.name : '';
        $('rfCap').value    = edit ? d.capacity : 40;
    }

    $('openAddRoom').addEventListener('click', () => { setMode(false); UI.open(modal); });
    document.querySelectorAll('.js-edit-room').forEach(a => {
        a.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); setMode(true, a.dataset); UI.open(modal); });
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
