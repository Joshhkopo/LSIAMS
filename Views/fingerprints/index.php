<?php $pageScript = 'fingerprints'; ?>
<div class="card">
  <div class="toolbar">
    <div class="spacer"></div>
    <button class="btn primary" id="btn-enroll">＋ Enroll Fingerprint</button>
  </div>
  <p class="text-dim" style="font-size:12.5px;">
    Enrollment procedure: put the classroom terminal into enrollment mode, capture the teacher's
    fingerprint on the R307 sensor (the sensor stores the template in a slot), then record the
    teacher / device / slot mapping here. One fingerprint per teacher.
  </p>
  <div id="table"></div>
</div>

<template id="tpl-form">
  <form id="fp-form">
    <label>Teacher *</label>
    <select name="teacher_id" required>
      <?php foreach ($teachers as $t): ?>
        <option value="<?= $e($t['id']) ?>"><?= $e($t['last_name'] . ', ' . $t['first_name'] . ' (' . $t['employee_number'] . ')') ?></option>
      <?php endforeach; ?>
    </select>
    <label>Enrollment Device *</label>
    <select name="device_id" required>
      <?php foreach ($devices as $d): ?>
        <option value="<?= $e($d['id']) ?>"><?= $e($d['device_code'] . ' — ' . $d['device_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Sensor Template Slot * (1–1000)</label>
    <input type="number" name="sensor_template_id" min="1" max="1000" required>
  </form>
</template>
