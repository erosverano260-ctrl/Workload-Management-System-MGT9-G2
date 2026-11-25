<?php
$page = 'other_workload';
$title = 'Other Functions & Designation';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/print_helpers.php';
requireRole('faculty');
date_default_timezone_set('Asia/Manila');

$conn = (new database())->connect();
ensure_other_workload_schema($conn);

$inst         = loadInstructor($conn, currentUserId());
$facId        = (int)$inst['faculty_id'];
$entries      = loadFacultySchedule($conn, $facId);
$designations = load_designations($conn, $facId);
$sessions     = load_overload_sessions($conn, $facId);
$L            = faculty_load($entries, $designations, $sessions);
$secA         = round((float)($L['comp'] ?? 0.0), 2);
$approvedDes  = round((float)($L['desHrs'] ?? 0.0), 2);
$target40     = defined('FACULTY_WEEK_HOURS') ? (float)FACULTY_WEEK_HOURS : 40.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $resHrs   = max(0.0, min(40.0, (float)($_POST['research_hrs'] ?? 0)));
    $extHrs   = max(0.0, min(40.0, (float)($_POST['extension_hrs'] ?? 0)));
    $impHrs   = max(0.0, min(40.0, (float)($_POST['imp_hrs'] ?? 0)));
    $conHrs   = max(0.0, min(40.0, (float)($_POST['consultation_hrs'] ?? 0)));
    $exDesHrs = max(0.0, min(40.0, (float)($_POST['extra_designation_hrs'] ?? 0)));
    $exDesLbl = mb_substr(trim((string)($_POST['extra_designation_label'] ?? '')), 0, 120);

    $st = $conn->prepare("
        INSERT INTO faculty_other_workload
            (faculty_id, research_hrs, extension_hrs, imp_hrs, consultation_hrs, extra_designation_hrs, extra_designation_label)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            research_hrs = VALUES(research_hrs),
            extension_hrs = VALUES(extension_hrs),
            imp_hrs = VALUES(imp_hrs),
            consultation_hrs = VALUES(consultation_hrs),
            extra_designation_hrs = VALUES(extra_designation_hrs),
            extra_designation_label = VALUES(extra_designation_label)
    ");
    $st->bind_param('iddddds', $facId, $resHrs, $extHrs, $impHrs, $conHrs, $exDesHrs, $exDesLbl);
    $st->execute();
    flash_set('success', 'Saved Section B (Other Functions) and Section C (Designation) hours.');
    redirect('other_workload.php');
}

$ow           = load_other_workload($conn, $facId);
$subB         = round($ow['research_hrs'] + $ow['extension_hrs'] + $ow['imp_hrs'] + $ow['consultation_hrs'], 2);
$subC         = round($approvedDes + $ow['extra_designation_hrs'], 2);
$grand        = round($secA + $subB + $subC, 2);
$remainingTo40 = max(0.0, round($target40 - $grand, 2));

$flash = flash_get();
require_once __DIR__ . '/../includes/faculty_header.php';
?>
<style>
  .wl-muted { color: #475569; font-size: 14px; line-height: 1.5; }
  .wl-alert {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 14px;
    background: #ecfdf5;
    border: 1px solid #10b981;
    color: #065f46;
  }
  .wl-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    max-width: 100%;
    display: grid;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  }
  .wl-card h3 {
    margin: 4px 0 0;
    font-size: 16px;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 6px;
  }
  .wl-grid-4 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
  }
  .wl-grid-des {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
  }
  @media (max-width: 600px) {
    .wl-grid-des { grid-template-columns: 1fr; }
  }
  .wl-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
  }
  .wl-field input {
    height: 38px;
    padding: 6px 10px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    font: inherit;
    font-weight: 400;
    background: #fff;
    color: #0f172a;
  }
  .wl-field input:focus {
    outline: 2px solid #1d4ed8;
    outline-offset: 1px;
    border-color: #1d4ed8;
  }
  .wl-des-list {
    font-size: 13px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
  }
  .wl-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    padding-top: 4px;
  }
</style>

<section class="page-section">
  <div class="section-heading">
    <div>
      <span class="eyebrow">40-HOUR WEEKLY WORKLOAD</span>
      <h2>Other Functions &amp; Designation</h2>
      <p class="wl-muted" style="margin:4px 0 0">
        A. Teaching Load: <strong><?= h(nf($secA)) ?> hrs</strong> ·
        B. Other Functions: <strong><?= h(nf($subB)) ?> hrs</strong> ·
        C. Designation: <strong><?= h(nf($subC)) ?> hrs</strong> ·
        Grand Total: <strong><?= h(nf($grand)) ?> / <?= h(nf($target40)) ?> hrs</strong>
        (<?= $remainingTo40 > 0 ? h(nf($remainingTo40)) . ' hrs remaining to 40' : '40-hr target met' ?>)
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="print.php" class="primary-btn" style="text-decoration:none">📄 Official Class Program</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="wl-alert"><?= h($flash['msg']) ?></div><?php endif; ?>

  <form method="post" class="wl-card">
    <?= csrf_field() ?>

    <h3>B. Other Functions (Hours / Week)</h3>
    <div class="wl-grid-4">
      <label class="wl-field">
        Research
        <input type="number" step="0.5" min="0" max="40" name="research_hrs" value="<?= h((string)$ow['research_hrs']) ?>">
      </label>
      <label class="wl-field">
        Extension
        <input type="number" step="0.5" min="0" max="40" name="extension_hrs" value="<?= h((string)$ow['extension_hrs']) ?>">
      </label>
      <label class="wl-field">
        IMP
        <input type="number" step="0.5" min="0" max="40" name="imp_hrs" value="<?= h((string)$ow['imp_hrs']) ?>">
      </label>
      <label class="wl-field">
        Consultation
        <input type="number" step="0.5" min="0" max="40" name="consultation_hrs" value="<?= h((string)$ow['consultation_hrs']) ?>">
      </label>
    </div>

    <h3>C. Designation (Hours / Week)</h3>
    <?php if ($designations): ?>
    <div class="wl-des-list">
      Approved designations:
      <?php foreach ($designations as $idx => $dg): ?>
        <span><?= h($dg['name']) ?> (<?= h(nf($dg['hours'])) ?> hrs)</span><?= $idx < count($designations) - 1 ? ' · ' : '' ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="wl-grid-des">
      <label class="wl-field">
        Designation Title / Note
        <input type="text" name="extra_designation_label" value="<?= h($ow['extra_designation_label']) ?>" placeholder="e.g. Lab Coordinator / Adviser">
      </label>
      <label class="wl-field">
        Designation Hours / Week
        <input type="number" step="0.5" min="0" max="40" name="extra_designation_hrs" value="<?= h((string)$ow['extra_designation_hrs']) ?>">
      </label>
    </div>

    <div class="wl-actions">
      <button type="submit" class="primary-btn">Save Other Functions &amp; Designation</button>
    </div>
  </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>