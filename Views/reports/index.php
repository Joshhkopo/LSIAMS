<?php
$pageScript = 'reports';
$isAdmin = ($currentUser['role_name'] ?? '') === 'Administrator';
?>
<div class="card">
  <h3>Attendance Reports</h3>
  <div class="toolbar">
    <input type="date" id="f-from" value="<?= $e(date('Y-m-d')) ?>">
    <input type="date" id="f-to" value="<?= $e(date('Y-m-d')) ?>">
    <select id="f-status">
      <option value="">All statuses</option>
      <option value="present">Present</option><option value="late">Late</option>
      <option value="absent">Absent</option><option value="excused">Excused</option>
    </select>
    <?php if ($isAdmin): ?>
      <select id="f-teacher">
        <option value="">All teachers</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= $e($t['id']) ?>"><?= $e($t['last_name'] . ', ' . $t['first_name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <select id="f-section">
      <option value="">All sections</option>
      <?php foreach ($sections as $s): ?>
        <option value="<?= $e($s['id']) ?>"><?= $e($s['grade_name'] . ' — ' . $s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-subject">
      <option value="">All subjects</option>
      <?php foreach ($subjects as $s): ?>
        <option value="<?= $e($s['id']) ?>"><?= $e($s['subject_code']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="spacer"></div>
    <button class="btn" id="btn-preview">Preview</button>
    <button class="btn" id="btn-print">Print / PDF</button>
    <button class="btn primary" id="btn-csv">Export CSV / Excel</button>
  </div>
  <div id="summary" class="grid cols-6"></div>
  <div id="table"></div>
</div>
