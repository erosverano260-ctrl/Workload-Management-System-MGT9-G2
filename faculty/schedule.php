<?php
$page = 'schedule'; $title = 'My Schedule';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/ui.php';
require '../includes/schedule_helpers.php';
requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn    = (new database())->connect();
$inst    = loadInstructor($conn, currentUserId());
$facId   = (int)$inst['faculty_id'];
$entries = loadFacultySchedule($conn, $facId);

/* Load rooms for room-change requests */
$roomsList = [];
try {
    $roomsList = $conn->query("SELECT room_id, room_name FROM rooms ORDER BY room_name")->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $roomsList = [];
}

/* Load other instructors' blocks so they can be filtered by room and overlapping time */
$otherMerged = [];
try {
    $stOther = $conn->prepare(
        "SELECT s.schedule_id, s.course_id, s.faculty_id, s.room_id, s.section, s.day_of_week, s.start_time, s.end_time, s.cell_color,
                c.course_code, c.course_name, r.room_name, f.faculty_name
         FROM schedules s
         JOIN courses c ON c.course_id = s.course_id
         JOIN rooms r   ON r.room_id   = s.room_id
         LEFT JOIN faculty f ON f.faculty_id = s.faculty_id
         WHERE s.faculty_id IS NULL OR s.faculty_id <> ?
         ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), s.start_time"
    );
    $stOther->bind_param('i', $facId);
    $stOther->execute();
    foreach ($stOther->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $last = count($otherMerged) - 1;
        if ($last >= 0) {
            $p = $otherMerged[$last];
            if (
                $p['day_of_week'] === $r['day_of_week'] &&
                (int)$p['course_id'] === (int)$r['course_id'] &&
                (int)$p['room_id'] === (int)$r['room_id'] &&
                $p['section'] === $r['section'] &&
                (string)$p['faculty_id'] === (string)$r['faculty_id'] &&
                $p['end_time'] === $r['start_time']
            ) {
                $otherMerged[$last]['end_time'] = $r['end_time'];
                continue;
            }
        }
        $otherMerged[] = $r;
    }
} catch (Throwable $e) {
    $otherMerged = [];
}

/* Timetable rows: 1 header · 2-11 = 7:00-12:00 · 12 lunch · 13-24 = 1:00-7:00 (30 min each) */
$AM0 = 7 * 60; $PM0 = 13 * 60;
$buildSegments = function (array $list) use ($AM0, $PM0): array {
    $segs = [];
    foreach ($list as $e) {
        $di = array_search($e['day_of_week'], FAC_DAYS, true);
        if ($di === false) continue;
        $col = 3 + $di;
        $s = timeToMin($e['start_time']); $en = timeToMin($e['end_time']);
        $as = max($s, $AM0); $ae = min($en, 12 * 60);
        if ($ae > $as) { $segs[] = [$col, 2 + intdiv($as - $AM0, 30), 2 + (int)ceil(($ae - $AM0) / 30), $e]; }
        $ps = max($s, $PM0); $pe = min($en, 19 * 60);
        if ($pe > $ps) { $segs[] = [$col, 13 + intdiv($ps - $PM0, 30), 13 + (int)ceil(($pe - $PM0) / 30), $e]; }
    }
    return $segs;
};

$segments = $buildSegments($entries);
$otherSegments = $buildSegments($otherMerged);
$hidden = max(0, count($entries) - count(array_unique(array_map(fn($x) => (int)($x[3]['schedule_id'] ?? 0), $segments))));
$lbl = fn(int $m) => ((intdiv($m, 60) % 12) ?: 12) . ':' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);

require '../includes/faculty_header.php';
?>
<style>
    .print-header { display: none; }
    @media print {
        .sidebar, .no-print { display: none !important; }
        .main-area { padding: 0; max-width: none; }
        .print-header { display: flex; }
        .fs-wrap { overflow: visible; border: 0; box-shadow: none; }
        .fs-wrap .schedule { min-width: 0; width: 100%;
            grid-template-columns: 34px 78px repeat(5, 1fr);
            grid-template-rows: 26px repeat(10, 26px) 22px repeat(12, 26px); }
        .schedule, .schedule * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .cell { padding: 1px; font-size: 8px; }
        .header { font-size: 9px; }
        .tt-entry { padding: 2px 4px; font-size: 7.5px; margin: 1px; }
        .tt-entry strong { font-size: 8.5px; }
        @page { size: landscape; margin: 8mm; }
    }

    .tt-entry { position: relative; }
    .tt-entry[draggable="true"] { cursor: grab; user-select: none; }
    .tt-entry[draggable="true"]:active { cursor: grabbing; }
    .tt-entry.is-selected { outline: 2px solid #d97706; outline-offset: -1px; z-index: 6; }
    .tt-entry.is-dragging { opacity: 0.55; z-index: 20; }
    .tt-entry.is-modified { outline: 2px dashed #1d4ed8; outline-offset: -2px; }
    .grid-slot.drag-over { background: rgba(29, 78, 216, 0.12); outline: 1px dashed #1d4ed8; }
    .req-status { font-size: 13px; color: #374151; }

    /* Other instructors' blocks: only shown when auto-filtered by room + overlapping time */
    .tt-other-blk {
        display: none;
        pointer-events: none;
        z-index: 2;
        margin: 1px;
        padding: 3px 5px;
        border-radius: 6px;
        border: 1px dashed rgba(71, 85, 105, 0.55);
        background: rgba(226, 232, 240, 0.78);
        color: #334155;
        font-size: 10.5px;
        line-height: 1.2;
        overflow: hidden;
    }
    .schedule.show-other-blocks .tt-other-blk.is-room-match {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .tt-other-blk strong { font-size: 11px; color: #0f172a; }

    .room-change-bar {
        display: none;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0 0 12px;
        padding: 8px 12px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        font-size: 13px;
    }
    .room-change-bar.is-visible { display: flex; }
    .room-change-bar select {
        height: 32px;
        padding: 0 8px;
        border-radius: 7px;
        border: 1px solid #94a3b8;
        background: #fff;
        font: inherit;
    }

    .tt-entry .rz-top,
    .tt-entry .rz {
        position: absolute;
        left: 2px;
        right: 2px;
        height: 10px;
        cursor: ns-resize;
        background: rgba(18, 33, 61, 0.14);
        opacity: 0;
        transition: opacity 0.14s ease, background 0.14s ease;
        z-index: 4;
        touch-action: none;
    }
    .tt-entry .rz-top {
        top: 0;
        border-top-left-radius: inherit;
        border-top-right-radius: inherit;
    }
    .tt-entry .rz {
        bottom: 0;
        border-bottom-left-radius: inherit;
        border-bottom-right-radius: inherit;
    }
    .tt-entry .rz-top::after,
    .tt-entry .rz::after {
        content: "";
        display: block;
        width: 24px;
        height: 2px;
        margin: 3px auto 0;
        border-radius: 999px;
        background: rgba(18, 33, 61, 0.55);
    }
    .tt-entry:hover .rz-top,
    .tt-entry:hover .rz,
    .tt-entry.is-resizing .rz-top,
    .tt-entry.is-resizing .rz {
        opacity: 1;
    }
    .tt-entry.is-resizing .rz-top,
    .tt-entry.is-resizing .rz {
        background: rgba(230, 153, 0, 0.35);
        opacity: 1;
    }
    .tt-entry.is-resizing {
        cursor: ns-resize !important;
        user-select: none;
        z-index: 25;
    }
</style>
<link rel="stylesheet" href="<?= asset('faculty_schedule.css') ?>">

<section class="page-section">
<div class="section-heading no-print">
    <div>
        <span class="eyebrow" id="scheduleMode">DRAG, RESIZE OR ROOM CHANGE · CHAIR APPROVAL REQUIRED</span>
        <h2>My weekly schedule</h2>
        
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="primary-btn" type="button" id="sendRequestBtn" disabled>✉ Send Change Request</button>
        <button class="primary-btn" type="button" id="resetMovesBtn" disabled>↺ Reset Changes</button>
        <a href="print.php" class="primary-btn" style="text-decoration:none">📄 Official class program</a>
        <button class="primary-btn" type="button" onclick="window.print()">🖨 Print this grid</button>
        <a href="../export_excel.php?type=instructor" class="primary-btn" style="text-decoration:none" title="Fixed paper size; legal ' 8.5 × 13 inches'"> Export Excel</a>
        
        
    </div>
    
</div>


<div class="room-change-bar no-print" id="roomChangeBar">
    <strong id="roomBarLabel">Selected block:</strong>
    <label for="roomChangeSelect">Request room:</label>
    <select id="roomChangeSelect">
        <option value="">Keep current room</option>
        <?php foreach ($roomsList as $rm): ?>
        <option value="<?= (int)$rm['room_id'] ?>"><?= h($rm['room_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="req-status" id="pendingMoveCount">No pending changes</div>
</div>

<div class="print-header">
    <img src="../assets/ee-logo.png" alt="logo">
    <div>
        <div class="print-school">INSTITUTE OF INTEGRATED ELECTRICAL ENGINEERS</div>
        <h3><?= h($inst['faculty_name']) ?> — Weekly Teaching Load</h3>
        <p style="margin:0;font-size:12px;color:#555"><?= count($entries) ?> classes · <?= h(scheduleHours($entries)) ?> hrs · Printed <?= h(date('M j, Y')) ?></p>
    </div>
</div>

<?php if (!$entries): ?>
    <div class="empty">No classes are assigned to you yet. Once the department assigns them, they'll appear here.</div>
<?php else: ?>
<div class="fs-wrap">
  <div class="schedule" id="facultyScheduleGrid">
    <div class="cell header" style="grid-column:1 / 3;grid-row:1">Time</div>
    <?php foreach (FAC_DAYS as $i => $d): ?><div class="cell header" style="grid-column:<?= 3 + $i ?>;grid-row:1"><?= h($d) ?></div><?php endforeach; ?>

    <div class="cell period" style="grid-column:1;grid-row:2 / 12">AM</div>
    <?php for ($i = 0; $i < 10; $i++): $m = $AM0 + $i * 30; ?>
    <div class="cell time" style="grid-column:2;grid-row:<?= 2 + $i ?>"><?= $lbl($m) ?> - <?= $lbl($m + 30) ?></div>
    <?php endfor; ?>

    <div class="cell lunch" style="grid-column:1 / 8;grid-row:12">LUNCH BREAK</div>

    <div class="cell period" style="grid-column:1;grid-row:13 / 25">PM</div>
    <?php for ($i = 0; $i < 12; $i++): $m = $PM0 + $i * 30; ?>
    <div class="cell time" style="grid-column:2;grid-row:<?= 13 + $i ?>"><?= $lbl($m) ?> - <?= $lbl($m + 30) ?></div>
    <?php endfor; ?>

    <?php
    for ($c = 3; $c <= 7; $c++) {
        $dayName = FAC_DAYS[$c - 3];
        for ($r = 2; $r <= 11; $r++) {
            $slotMin = $AM0 + ($r - 2) * 30;
            echo '<div class="cell grid-slot" data-col="' . $c . '" data-row="' . $r . '" data-day="' . h($dayName) . '" data-start-min="' . $slotMin . '" style="grid-column:' . $c . ';grid-row:' . $r . '"></div>';
        }
        for ($r = 13; $r <= 24; $r++) {
            $slotMin = $PM0 + ($r - 13) * 30;
            echo '<div class="cell grid-slot" data-col="' . $c . '" data-row="' . $r . '" data-day="' . h($dayName) . '" data-start-min="' . $slotMin . '" style="grid-column:' . $c . ';grid-row:' . $r . '"></div>';
        }
    }
    ?>

    <?php foreach ($otherSegments as [$oCol, $oR1, $oR2, $oe]):
        $oStartMin = timeToMin($oe['start_time']);
        $oEndMin   = timeToMin($oe['end_time']);
    ?>
    <div class="tt-other-blk"
         data-room-id="<?= (int)($oe['room_id'] ?? 0) ?>"
         data-start-min="<?= $oStartMin ?>"
         data-end-min="<?= $oEndMin ?>"
         style="grid-column:<?= $oCol ?>;grid-row:<?= $oR1 ?> / <?= $oR2 ?>"
         title="<?= h($oe['course_code'] . ' · ' . ($oe['faculty_name'] ?: 'Unassigned') . ' · ' . $oe['room_name'] . ' · ' . rangeLabel($oe['start_time'], $oe['end_time'])) ?>">
      <strong><?= h($oe['course_code']) ?></strong>
      <span><?= h($oe['room_name']) ?> · <?= h($oe['section']) ?></span>
      <span><?= h($oe['faculty_name'] ?: 'Unassigned') ?></span>
    </div>
    <?php endforeach; ?>

    <?php foreach ($segments as [$col, $r1, $r2, $e]):
        $origStartMin = timeToMin($e['start_time']);
        $origEndMin   = timeToMin($e['end_time']);
        $durationMin  = max(30, $origEndMin - $origStartMin);
        $rowSpan      = max(1, (int)round($durationMin / 30));
        $scheduleId   = (int)($e['schedule_id'] ?? $e['id'] ?? 0);
        $origRoomId   = (int)($e['room_id'] ?? 0);
        $origRoomName = (string)($e['room_name'] ?? '');
    ?>
    <div class="tt-entry"
         draggable="true"
         data-schedule-id="<?= $scheduleId ?>"
         data-course-code="<?= h($e['course_code']) ?>"
         data-section="<?= h($e['section']) ?>"
         data-orig-col="<?= $col ?>"
         data-orig-r1="<?= $r1 ?>"
         data-orig-r2="<?= $r2 ?>"
         data-orig-day="<?= h($e['day_of_week']) ?>"
         data-orig-start-min="<?= $origStartMin ?>"
         data-orig-end-min="<?= $origEndMin ?>"
         data-orig-room-id="<?= $origRoomId ?>"
         data-orig-room-name="<?= h($origRoomName) ?>"
         data-curr-col="<?= $col ?>"
         data-curr-r1="<?= $r1 ?>"
         data-curr-r2="<?= $r2 ?>"
         data-curr-day="<?= h($e['day_of_week']) ?>"
         data-curr-start-min="<?= $origStartMin ?>"
         data-curr-end-min="<?= $origEndMin ?>"
         data-curr-room-id="<?= $origRoomId ?>"
         data-curr-room-name="<?= h($origRoomName) ?>"
         data-duration-min="<?= $durationMin ?>"
         data-row-span="<?= $rowSpan ?>"
         style="grid-column:<?= $col ?>;grid-row:<?= $r1 ?> / <?= $r2 ?>;--c:<?= h($e['cell_color'] ?: '#ffb100') ?>"
         title="<?= h($e['course_code'] . ' · ' . $e['course_name'] . ' · ' . rangeLabel($e['start_time'], $e['end_time'])) ?>">
      <span class="rz-top" title="Drag top edge to adjust start time"></span>
      <strong><?= h($e['course_code']) ?></strong>
      <span><?= h($e['course_name']) ?></span>
      <span class="tt-meta-label"><?= h($e['section']) ?> · <?= h($origRoomName) ?></span>
      <span class="tt-time-label"><?= h(rangeLabel($e['start_time'], $e['end_time'])) ?></span>
      <span class="rz" title="Drag bottom edge to adjust end time"></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php if ($hidden): ?>
<p class="tt-legend no-print"><?= $hidden ?> class<?= $hidden === 1 ? '' : 'es' ?> fall in the lunch hour or outside 7 AM – 7 PM and aren't drawn. Your dashboard lists everything.</p>
<?php endif; ?>
<?php endif; ?>
</section>

<script>
(() => {
    const grid = document.getElementById('facultyScheduleGrid');
    if (!grid) return;

    const sendBtn       = document.getElementById('sendRequestBtn');
    const resetBtn      = document.getElementById('resetMovesBtn');
    const countEl       = document.getElementById('pendingMoveCount');
    const roomBar       = document.getElementById('roomChangeBar');
    const roomBarLabel  = document.getElementById('roomBarLabel');
    const roomSelect    = document.getElementById('roomChangeSelect');

    let draggedBlock  = null;
    let selectedBlock = null;
    let isResizing    = false;

    const toSqlTime = m => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}:00`;
    const toLabelTime = m => {
        const h24 = Math.floor(m / 60), min = m % 60;
        return `${(h24 % 12) || 12}:${String(min).padStart(2, '0')} ${h24 >= 12 ? 'PM' : 'AM'}`;
    };

    function refreshOtherBlocksFilter() {
        const active = draggedBlock || selectedBlock;
        const otherEls = Array.from(grid.querySelectorAll('.tt-other-blk'));

        if (!active) {
            grid.classList.remove('show-other-blocks');
            otherEls.forEach(el => el.classList.remove('is-room-match'));
            return;
        }

        const targetRoomId = Number(active.dataset.currRoomId || active.dataset.origRoomId || 0);
        const startMin = Number(active.dataset.currStartMin || active.dataset.origStartMin || 0);
        const endMin = Number(active.dataset.currEndMin || active.dataset.origEndMin || 0);

        let anyMatch = false;
        otherEls.forEach(el => {
            const oRoomId = Number(el.dataset.roomId || 0);
            const oStart = Number(el.dataset.startMin || 0);
            const oEnd = Number(el.dataset.endMin || 0);
            const matches = targetRoomId > 0 && oRoomId === targetRoomId && (startMin < oEnd && endMin > oStart);
            el.classList.toggle('is-room-match', matches);
            if (matches) anyMatch = true;
        });

        grid.classList.toggle('show-other-blocks', anyMatch);
    }

    function selectBlock(block) {
        if (selectedBlock && selectedBlock !== block) {
            selectedBlock.classList.remove('is-selected');
        }
        selectedBlock = block || null;
        if (selectedBlock) {
            selectedBlock.classList.add('is-selected');
            roomBar.classList.add('is-visible');
            roomBarLabel.textContent = `${selectedBlock.dataset.courseCode} (${selectedBlock.dataset.section}) — Current: ${selectedBlock.dataset.currRoomName}`;
            roomSelect.value = String(selectedBlock.dataset.currRoomId || '');
        } else {
            roomBar.classList.remove('is-visible');
        }
        refreshOtherBlocksFilter();
    }

    function minRowForEnd(r2) {
        if (r2 >= 3 && r2 <= 12) return 2;
        if (r2 >= 14 && r2 <= 25) return 13;
        return r2 - 1;
    }

    function maxEndRowForStart(r1) {
        if (r1 >= 2 && r1 <= 11) return 12;
        if (r1 >= 13 && r1 <= 24) return 25;
        return r1 + 1;
    }

    function canFitSpan(row, span) {
        return (row + span) <= maxEndRowForStart(row);
    }

    function hasOverlap(movingEl, col, r1, r2) {
        return Array.from(grid.querySelectorAll('.tt-entry')).some(other => {
            if (other === movingEl) return false;
            return Number(other.dataset.currCol) === col &&
                (r1 < Number(other.dataset.currR2) && r2 > Number(other.dataset.currR1));
        });
    }

    function isBlockChanged(b) {
        return b.dataset.currDay !== b.dataset.origDay ||
            Number(b.dataset.currStartMin) !== Number(b.dataset.origStartMin) ||
            Number(b.dataset.currEndMin) !== Number(b.dataset.origEndMin) ||
            Number(b.dataset.currRoomId) !== Number(b.dataset.origRoomId);
    }

    function getMovedBlocks() {
        return Array.from(grid.querySelectorAll('.tt-entry')).filter(isBlockChanged);
    }

    function updateControls() {
        const n = getMovedBlocks().length;
        countEl.textContent = n === 0 ? 'No pending changes' : `${n} change${n === 1 ? '' : 's'} ready to send`;
        sendBtn.disabled = n === 0;
        resetBtn.disabled = n === 0;
    }

    function refreshMetaLabel(block) {
        const meta = block.querySelector('.tt-meta-label');
        const roomName = block.dataset.currRoomName || block.dataset.origRoomName || '';
        const roomChanged = Number(block.dataset.currRoomId) !== Number(block.dataset.origRoomId);
        if (meta) {
            meta.textContent = `${block.dataset.section} · ${roomName}${roomChanged ? ' (Room req)' : ''}`;
        }
        block.classList.toggle('is-modified', isBlockChanged(block));
    }

    function applyBlockGeometry(block, col, r1, r2, day, startMin, endMin) {
        const span = Math.max(1, r2 - r1);
        const dur  = Math.max(30, endMin - startMin);

        Object.assign(block.dataset, {
            currCol: col,
            currR1: r1,
            currR2: r2,
            currDay: day,
            currStartMin: startMin,
            currEndMin: endMin,
            rowSpan: span,
            durationMin: dur
        });
        block.style.gridColumn = `${col}`;
        block.style.gridRow = `${r1} / ${r2}`;

        const lbl = block.querySelector('.tt-time-label');
        if (lbl) lbl.textContent = `${toLabelTime(startMin)} - ${toLabelTime(endMin)}`;

        refreshMetaLabel(block);
        if (block === draggedBlock || block === selectedBlock) {
            refreshOtherBlocksFilter();
        }
    }

    function placeBlock(block, col, r1, day, startMin) {
        const span = Number(block.dataset.rowSpan);
        const dur  = Number(block.dataset.durationMin);
        applyBlockGeometry(block, col, r1, r1 + span, day, startMin, startMin + dur);
    }

    function getColumnSlotRows(col) {
        return Array.from(grid.querySelectorAll(`.grid-slot[data-col="${col}"]`))
            .map(el => ({
                row: Number(el.dataset.row),
                rect: el.getBoundingClientRect()
            }))
            .filter(item => !Number.isNaN(item.row) && item.rect.height > 0)
            .sort((a, b) => a.row - b.row);
    }

    function getRowPitchPx(col) {
        const slots = getColumnSlotRows(col);
        for (let i = 1; i < slots.length; i++) {
            if (slots[i].row === slots[i - 1].row + 1) {
                const pitch = slots[i].rect.top - slots[i - 1].rect.top;
                if (pitch > 10) return pitch;
            }
        }
        return slots[0] ? slots[0].rect.height : 28;
    }

    roomSelect.addEventListener('change', () => {
        if (!selectedBlock) return;
        const chosenId = Number(roomSelect.value || 0);
        const chosenText = roomSelect.options[roomSelect.selectedIndex]?.textContent || '';
        if (chosenId > 0) {
            selectedBlock.dataset.currRoomId = String(chosenId);
            selectedBlock.dataset.currRoomName = chosenText;
        } else {
            selectedBlock.dataset.currRoomId = selectedBlock.dataset.origRoomId;
            selectedBlock.dataset.currRoomName = selectedBlock.dataset.origRoomName;
        }
        roomBarLabel.textContent = `${selectedBlock.dataset.courseCode} (${selectedBlock.dataset.section}) — Current: ${selectedBlock.dataset.currRoomName}`;
        refreshMetaLabel(selectedBlock);
        refreshOtherBlocksFilter();
        updateControls();
    });

    grid.addEventListener('click', e => {
        const block = e.target.closest('.tt-entry');
        if (block) {
            selectBlock(block);
        } else if (!isResizing && !draggedBlock) {
            selectBlock(null);
        }
    });

    grid.addEventListener('contextmenu', e => { if (e.target.closest('.tt-entry')) e.preventDefault(); });

    grid.addEventListener('dragstart', e => {
        if (isResizing || e.target.closest('.rz, .rz-top')) {
            e.preventDefault();
            return;
        }
        const block = e.target.closest('.tt-entry[draggable="true"]');
        if (!block) return;
        draggedBlock = block;
        selectBlock(block);
        block.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', block.dataset.scheduleId || '');
    });

    grid.addEventListener('dragend', () => {
        if (draggedBlock) draggedBlock.classList.remove('is-dragging');
        draggedBlock = null;
        refreshOtherBlocksFilter();
        grid.querySelectorAll('.grid-slot.drag-over').forEach(s => s.classList.remove('drag-over'));
    });

    grid.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.rz, .rz-top');
        if (!handle) return;

        const block = handle.closest('.tt-entry');
        if (!block) return;

        e.preventDefault();
        e.stopPropagation();

        selectBlock(block);
        const fromTop = handle.classList.contains('rz-top');
        isResizing = true;
        const wasDraggable = block.getAttribute('draggable');
        block.setAttribute('draggable', 'false');
        block.classList.add('is-resizing');

        const col = Number(block.dataset.currCol);
        const origR1 = Number(block.dataset.currR1);
        const origR2 = Number(block.dataset.currR2);
        const origStartMin = Number(block.dataset.currStartMin);
        const origEndMin = Number(block.dataset.currEndMin);
        const day = block.dataset.currDay;
        const startY = e.clientY;
        const rowPx = getRowPitchPx(col);

        let targetR1 = origR1;
        let targetR2 = origR2;

        const onPointerMove = moveEvent => {
            const deltaRows = Math.round((moveEvent.clientY - startY) / rowPx);

            if (fromTop) {
                const minR1 = minRowForEnd(origR2);
                const maxR1 = origR2 - 1;
                let candidateR1 = Math.max(minR1, Math.min(maxR1, origR1 + deltaRows));

                while (candidateR1 < maxR1 && hasOverlap(block, col, candidateR1, origR2)) {
                    candidateR1++;
                }
                if (hasOverlap(block, col, candidateR1, origR2)) return;

                targetR1 = candidateR1;
                const candidateStartMin = origStartMin + ((targetR1 - origR1) * 30);
                applyBlockGeometry(block, col, targetR1, origR2, day, candidateStartMin, origEndMin);
            } else {
                const minR2 = origR1 + 1;
                const maxR2 = maxEndRowForStart(origR1);
                let candidateR2 = Math.max(minR2, Math.min(maxR2, origR2 + deltaRows));

                while (candidateR2 > minR2 && hasOverlap(block, col, origR1, candidateR2)) {
                    candidateR2--;
                }
                if (hasOverlap(block, col, origR1, candidateR2)) return;

                targetR2 = candidateR2;
                const candidateEndMin = origStartMin + ((targetR2 - origR1) * 30);
                applyBlockGeometry(block, col, origR1, targetR2, day, origStartMin, candidateEndMin);
            }
        };

        const finishResize = () => {
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', finishResize);
            window.removeEventListener('pointercancel', finishResize);
            block.classList.remove('is-resizing');
            if (wasDraggable !== null) block.setAttribute('draggable', wasDraggable);
            isResizing = false;
            updateControls();
        };

        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', finishResize);
        window.addEventListener('pointercancel', finishResize);
    });

    grid.querySelectorAll('.grid-slot').forEach(slot => {
        slot.addEventListener('dragover', e => {
            if (!draggedBlock || isResizing) return;
            const col = Number(slot.dataset.col), r1 = Number(slot.dataset.row);
            const r2 = r1 + Number(draggedBlock.dataset.rowSpan);
            if (!canFitSpan(r1, Number(draggedBlock.dataset.rowSpan)) || hasOverlap(draggedBlock, col, r1, r2)) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            slot.classList.add('drag-over');
        });
        slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
        slot.addEventListener('drop', e => {
            e.preventDefault();
            slot.classList.remove('drag-over');
            if (!draggedBlock || isResizing) return;
            const col = Number(slot.dataset.col), r1 = Number(slot.dataset.row);
            const span = Number(draggedBlock.dataset.rowSpan), r2 = r1 + span;
            if (!canFitSpan(r1, span) || hasOverlap(draggedBlock, col, r1, r2)) return;
            placeBlock(draggedBlock, col, r1, slot.dataset.day, Number(slot.dataset.startMin));
            updateControls();
        });
    });

    resetBtn.addEventListener('click', () => {
        grid.querySelectorAll('.tt-entry').forEach(b => {
            const origSpan = Math.max(1, Math.round((Number(b.dataset.origEndMin) - Number(b.dataset.origStartMin)) / 30));
            b.dataset.rowSpan = String(origSpan);
            b.dataset.durationMin = String(Number(b.dataset.origEndMin) - Number(b.dataset.origStartMin));
            b.dataset.currRoomId = b.dataset.origRoomId;
            b.dataset.currRoomName = b.dataset.origRoomName;
            applyBlockGeometry(
                b,
                Number(b.dataset.origCol),
                Number(b.dataset.origR1),
                Number(b.dataset.origR2),
                b.dataset.origDay,
                Number(b.dataset.origStartMin),
                Number(b.dataset.origEndMin)
            );
        });
        if (selectedBlock) {
            roomSelect.value = String(selectedBlock.dataset.currRoomId || '');
            roomBarLabel.textContent = `${selectedBlock.dataset.courseCode} (${selectedBlock.dataset.section}) — Current: ${selectedBlock.dataset.currRoomName}`;
        }
        refreshOtherBlocksFilter();
        updateControls();
    });

    sendBtn.addEventListener('click', async () => {
        const moved = getMovedBlocks();
        if (!moved.length) return;

        const note = prompt('Optional note for the chair (leave blank if none):', '') ?? '';
        const requests = moved.map(b => ({
            schedule_id: Number(b.dataset.scheduleId),
            old_day: b.dataset.origDay,
            old_start: toSqlTime(Number(b.dataset.origStartMin)),
            old_end: toSqlTime(Number(b.dataset.origEndMin)),
            old_room_id: Number(b.dataset.origRoomId || 0),
            new_day: b.dataset.currDay,
            new_start: toSqlTime(Number(b.dataset.currStartMin)),
            new_end: toSqlTime(Number(b.dataset.currEndMin)),
            new_room_id: Number(b.dataset.currRoomId || b.dataset.origRoomId || 0),
            faculty_note: note.trim().slice(0, 200)
        }));

        sendBtn.disabled = true;
        countEl.textContent = 'Sending request...';

        try {
            const res = await fetch('request_schedule_change.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ requests })
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                throw new Error(data.message || 'Unable to send schedule change request.');
            }
            alert('Schedule change request sent to the chair.');
            window.location.reload();
        } catch (err) {
            alert(err.message);
            updateControls();
        }
    });
})();
</script>

<?php require '../includes/footer.php'; ?>