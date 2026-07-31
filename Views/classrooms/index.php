<?php $pageScript = 'classrooms'; ?>
<div class="card">
  <div class="toolbar">
    <div class="spacer"></div>
    <button class="btn primary" id="btn-add">＋ Add Classroom</button>
  </div>
  <div id="table"></div>
</div>

<template id="tpl-form">
  <form id="classroom-form">
    <div class="form-row">
      <div><label>Room Number *</label><input type="text" name="room_number" required maxlength="30"></div>
      <div><label>Capacity *</label><input type="number" name="capacity" min="1" max="500" value="40" required></div>
    </div>
    <div class="form-row">
      <div><label>Building</label><input type="text" name="building" maxlength="80"></div>
      <div><label>Floor</label><input type="text" name="floor" maxlength="20"></div>
    </div>
    <label>Status *</label>
    <select name="status"><option value="active">Active</option><option value="archived">Archived</option></select>
    <p class="text-dim" style="font-size:12px;">
      The ESP32 terminal is assigned to a classroom from the <a href="/devices">IoT Devices</a> module.
    </p>
  </form>
</template>
