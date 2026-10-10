<?php
$page='rooms';$title='Classroom Availability';
require 'includes/db.php';
require 'includes/auth.php';
requireRole('faculty');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$db = new database();
$conn = $db->connect();

$flash = null;

// --- ADD ROOM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_room') {
    $stmt = $conn->prepare("INSERT INTO rooms (room_code, room_name, capacity) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $_POST['room_code'], $_POST['room_name'], $_POST['capacity']);
    $stmt->execute();
    $flash = ['type'=>'success','msg'=>'Room added.'];
}

// --- EDIT ROOM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_room') {
    $stmt = $conn->prepare("UPDATE rooms SET room_code=?, room_name=?, capacity=? WHERE room_id=?");
    $stmt->bind_param("ssii", $_POST['room_code'], $_POST['room_name'], $_POST['capacity'], $_POST['room_id']);
    $stmt->execute();
    $flash = ['type'=>'success','msg'=>'Room updated.'];
}

// --- DELETE ROOM ---
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    header('Location: classrooms.php'); exit;
}

require 'includes/header.php';

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
$result = $conn->query($sql);
$rooms = $result->fetch_all(MYSQLI_ASSOC);

const AVAILABLE_HOURS_PER_WEEK = 60; // 7am-7pm, 5 days a week
?>
<link rel="stylesheet" href="assets/faculty_schedule.css">
<section class="page-section">
<div class="section-heading">
    <div><span class="eyebrow">SPACE MANAGEMENT</span><h2>Classroom availability</h2></div>
    <button class="primary-btn" id="openAddRoom">+ Add Room</button>
</div>

<?php if ($flash): ?><div class="flash <?= $flash['type'] ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

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
    <div class="course-bottom" style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border);">
        <a href="?edit=<?= $r['room_id'] ?>" class="outline-btn">Edit</a>
        <a href="?delete=<?= $r['room_id'] ?>" class="outline-btn" onclick="return confirm('Delete this room?')">Delete</a>
    </div>
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
    <td>
        <a href="?edit=<?= $r['room_id'] ?>" class="outline-btn">Edit</a>
        <a href="?delete=<?= $r['room_id'] ?>" class="outline-btn" onclick="return confirm('Delete this room?')">Delete</a>
    </td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</section>

<!-- ADD / EDIT ROOM MODAL -->
<div class="modal-backdrop" id="roomFormBackdrop"></div>
<div class="modal <?= $editing ? 'open' : '' ?>" id="roomFormModal">
    <button class="modal-close" id="closeRoomForm">×</button>
    <h2><?= $editing ? 'Edit Room' : 'Add Room' ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?= $editing ? 'edit_room' : 'add_room' ?>">
        <?php if ($editing): ?><input type="hidden" name="room_id" value="<?= h($editing['room_id']) ?>"><?php endif; ?>

        <div class="form-group">
            <label>Room Code</label>
            <input type="text" name="room_code" required value="<?= h($editing['room_code'] ?? '') ?>" placeholder="e.g. R101">
        </div>

        <div class="form-group">
            <label>Room Name</label>
            <input type="text" name="room_name" required value="<?= h($editing['room_name'] ?? '') ?>" placeholder="e.g. Room 101">
        </div>

        <div class="form-group">
            <label>Capacity</label>
            <input type="number" name="capacity" required value="<?= h($editing['capacity'] ?? 40) ?>">
        </div>

        <button type="submit" class="primary-btn full"><?= $editing ? 'Save Changes' : 'Add Room' ?></button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roomModal = document.getElementById('roomFormModal');
    if (roomModal) {
        const roomBackdrop = document.getElementById('roomFormBackdrop');
        const openBtn = document.getElementById('openAddRoom');
        const closeBtn = document.getElementById('closeRoomForm');

        openBtn?.addEventListener('click', () => { roomModal.classList.add('open'); roomBackdrop.classList.add('open'); });
        closeBtn?.addEventListener('click', () => { roomModal.classList.remove('open'); roomBackdrop.classList.remove('open'); });
        roomBackdrop?.addEventListener('click', () => { roomModal.classList.remove('open'); roomBackdrop.classList.remove('open'); });
    }
});
</script>

<?php require 'includes/footer.php'; ?>