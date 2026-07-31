<?php $pageScript = 'devices'; ?>
<div class="card">
  <div class="toolbar">
    <div class="spacer"></div>
    <button class="btn primary" id="btn-add">＋ Register Device</button>
  </div>
  <div id="cards" class="grid cols-2"></div>
</div>

<template id="tpl-form">
  <form id="device-form">
    <div class="form-row">
      <div><label>Device ID * (e.g. DEV-002)</label><input type="text" name="device_code" required maxlength="30"></div>
      <div><label>Device Name *</label><input type="text" name="device_name" required maxlength="100"></div>
    </div>
    <div class="form-row">
      <div><label>MAC Address *</label><input type="text" name="mac_address" required maxlength="17" placeholder="AA:BB:CC:DD:EE:FF"></div>
      <div><label>Firmware Version</label><input type="text" name="firmware_version" maxlength="20" value="1.0.0"></div>
    </div>
    <div class="form-row">
      <div><label>Classroom</label>
        <select name="classroom_id"><option value="">— unassigned —</option>
          <?php foreach ($classrooms as $c): ?>
            <option value="<?= $e($c['id']) ?>"><?= $e($c['room_number']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>WiFi SSID</label><input type="text" name="wifi_ssid" maxlength="64"></div>
    </div>
    <p class="text-dim" style="font-size:12px;">
      A 256-bit API key is generated on registration and shown once — copy it into the firmware.
    </p>
  </form>
</template>
