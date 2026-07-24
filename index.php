<?php
$pageScript = 'settings';
$byKey = [];
foreach ($settings as $s) {
    $byKey[$s['setting_key']] = $s['setting_value'];
}
$val = fn(string $k, string $d = ''): string => (string) ($byKey[$k] ?? $d);
?>
<div class="card">
  <h3>System Settings</h3>
  <p class="text-dim" style="font-size:12.5px;">
    Changing settings requires re-entering your administrator password. All changes are audited.
  </p>
  <form id="settings-form">
    <h4>School</h4>
    <div class="form-row">
      <div><label>School Name</label>
        <input type="text" name="school_name" maxlength="150" value="<?= $e($val('school_name')) ?>"></div>
      <div><label>Timezone</label>
        <input type="text" name="timezone" maxlength="60" value="<?= $e($val('timezone', 'Asia/Manila')) ?>"></div>
    </div>

    <h4>Attendance Rules</h4>
    <div class="form-row">
      <div><label>Attendance Window (minutes)</label>
        <input type="number" name="attendance_window_minutes" min="1" max="120" value="<?= $e($val('attendance_window_minutes', '20')) ?>"></div>
      <div><label>Late Threshold (minutes)</label>
        <input type="number" name="late_threshold_minutes" min="1" max="120" value="<?= $e($val('late_threshold_minutes', '20')) ?>"></div>
      <div><label>Absent Cutoff (minutes)</label>
        <input type="number" name="absent_cutoff_minutes" min="1" max="240" value="<?= $e($val('absent_cutoff_minutes', '60')) ?>"></div>
    </div>

    <h4>IoT &amp; Security</h4>
    <div class="form-row">
      <div><label>Heartbeat Interval (seconds)</label>
        <input type="number" name="heartbeat_interval_seconds" min="10" max="300" value="<?= $e($val('heartbeat_interval_seconds', '30')) ?>"></div>
      <div><label>Offline After (seconds)</label>
        <input type="number" name="heartbeat_offline_after_seconds" min="30" max="900" value="<?= $e($val('heartbeat_offline_after_seconds', '90')) ?>"></div>
      <div><label>API Timestamp Window (seconds)</label>
        <input type="number" name="api_timestamp_window_seconds" min="5" max="300" value="<?= $e($val('api_timestamp_window_seconds', '30')) ?>"></div>
      <div><label>Fingerprint Max Failures</label>
        <input type="number" name="fingerprint_max_failures" min="3" max="20" value="<?= $e($val('fingerprint_max_failures', '5')) ?>"></div>
    </div>

    <h4>System</h4>
    <div class="form-row">
      <div><label>Backup Schedule</label>
        <select name="backup_schedule">
          <?php foreach (['daily', 'weekly', 'manual'] as $option): ?>
            <option value="<?= $option ?>" <?= $val('backup_schedule', 'daily') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Theme</label>
        <select name="theme">
          <option value="light" <?= $val('theme', 'light') === 'light' ? 'selected' : '' ?>>Light</option>
          <option value="dark" <?= $val('theme') === 'dark' ? 'selected' : '' ?>>Dark</option>
        </select></div>
    </div>

    <h4>Confirm</h4>
    <div class="form-row">
      <div><label>Your Password *</label>
        <input type="password" name="confirm_password" required maxlength="255" autocomplete="current-password"></div>
    </div>
    <button type="button" class="btn primary" id="btn-save" style="margin-top:14px;">Save Settings</button>
  </form>
</div>
