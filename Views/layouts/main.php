<?php
/**
 * Main application layout: top bar + sidebar + content.
 * Available: $content, $e (escape), $csrfToken, $currentUser, $flashes, $pageTitle.
 */
$role = $currentUser['role_name'] ?? '';
$isAdmin = $role === 'Administrator';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = fn(string $prefix): string => str_starts_with($path, $prefix) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
  <title><?= $e($pageTitle ?? 'L-SIAMS') ?> · L-SIAMS</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css" media="all" onerror="this.remove()">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">L-SIAMS<small>IoT Attendance Monitoring</small></div>
    <nav>
      <a class="<?= $active('/dashboard') ?>" href="/dashboard">📊 Dashboard</a>
      <a class="<?= $active('/attendance') ?>" href="/attendance">🕒 Attendance</a>
      <?php if ($isAdmin): ?>
        <div class="nav-section">School</div>
        <a class="<?= $active('/students') ?>" href="/students">🎓 Students</a>
        <a class="<?= $active('/teachers') ?>" href="/teachers">👩‍🏫 Teachers</a>
        <a class="<?= $active('/schedules') ?>" href="/schedules">🗓 Schedules</a>
        <a class="<?= $active('/subjects') ?>" href="/subjects">📚 Subjects</a>
        <a class="<?= $active('/classrooms') ?>" href="/classrooms">🏫 Classrooms</a>
        <div class="nav-section">IoT &amp; Identity</div>
        <a class="<?= $active('/rfid') ?>" href="/rfid">💳 RFID Cards</a>
        <a class="<?= $active('/fingerprints') ?>" href="/fingerprints">🫆 Fingerprints</a>
        <a class="<?= $active('/devices') ?>" href="/devices">📡 IoT Devices</a>
        <div class="nav-section">Insight</div>
        <a class="<?= $active('/reports') ?>" href="/reports">📄 Reports</a>
        <a class="<?= $active('/analytics') ?>" href="/analytics">📈 Analytics</a>
        <div class="nav-section">Administration</div>
        <a class="<?= $active('/security') ?>" href="/security">🛡 Security Center</a>
        <a class="<?= $active('/audit') ?>" href="/audit">🧾 Audit Logs</a>
        <a class="<?= $active('/settings') ?>" href="/settings">⚙️ Settings</a>
        <a class="<?= $active('/backups') ?>" href="/backups">💾 Backup &amp; Restore</a>
      <?php else: ?>
        <a class="<?= $active('/schedules') ?>" href="/schedules">🗓 My Schedule</a>
        <a class="<?= $active('/reports') ?>" href="/reports">📄 Reports</a>
      <?php endif; ?>
      <div class="nav-section">Account</div>
      <a class="<?= $active('/profile') ?>" href="/profile">👤 Profile</a>
    </nav>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="menu-toggle" aria-label="Menu">☰</button>
      <div class="page-title"><?= $e($pageTitle ?? '') ?></div>
      <span class="clock" id="clock"></span>
      <a href="#" id="notif-bell" title="Notifications" style="position:relative;font-size:17px;">
        🔔<span id="notif-count" class="badge red" style="position:absolute;top:-6px;right:-10px;display:none;"></span>
      </a>
      <span><?= $e($currentUser['username'] ?? '') ?> <span class="badge blue"><?= $e($role) ?></span></span>
      <form method="post" action="/logout" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button class="btn sm outline" type="submit">Logout</button>
      </form>
    </div>

    <main class="content">
      <?php foreach ($flashes as $type => $message): ?>
        <div class="flash <?= $e($type) ?>"><?= $e($message) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>

    <div class="footer">L-SIAMS — Local IoT-Based Multi-Layer Secured Attendance Monitoring System · LAN-only deployment</div>
  </div>
</div>
<script src="/assets/vendor/chart.umd.js"></script>
<script src="/assets/js/app.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="/assets/js/pages/<?= $e($pageScript) ?>.js"></script>
<?php endif; ?>
</body>
</html>
