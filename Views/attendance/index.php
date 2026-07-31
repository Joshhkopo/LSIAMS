<?php
$pageScript = 'attendance';
$isAdmin = ($currentUser['role_name'] ?? '') === 'Administrator';
?>
<div class="card">
  <div class="toolbar">
    <input type="text" id="search" placeholder="Search student, number, RFID…">
    <input type="date" id="f-from" value="<?= $e(date('Y-m-d')) ?>">
    <input type="date" id="f-to" value="<?= $e(date('Y-m-d')) ?>">
    <select id="f-status">
      <option value="">All statuses</option>
      <option value="present">Present</option><option value="late">Late</option>
      <option value="absent">Absent</option><option value="excused">Excused</option>
      <option value="official_business">Official Business</option>
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
    <button class="btn" id="btn-sessions">Sessions</button>
  </div>
  <div id="table"></div>
  <div id="pager"></div>
</div>
<?php if ($isAdmin): ?>
<template id="tpl-correct">
  <form id="correct-form">
    <label>New Status *</label>
    <select name="status_id" required>
      <option value="1">Present</option><option value="2">Late</option>
      <option value="3">Absent</option><option value="4">Excused</option>
      <option value="5">Official Business</option><option value="6">Incomplete</option>
    </select>
    <label>Reason * (required, audited)</label>
    <textarea name="reason" rows="3" required minlength="5" maxlength="500"></textarea>
  </form>
</template>
<?php endif; ?>
