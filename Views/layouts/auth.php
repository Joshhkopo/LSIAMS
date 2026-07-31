<?php /** Minimal centered layout for authentication screens. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
  <title><?= $e($pageTitle ?? 'Sign in') ?> · L-SIAMS</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo">L-SIAMS</div>
    <div class="sub">Local IoT-Based Multi-Layer Secured<br>Attendance Monitoring System</div>
    <?php foreach ($flashes as $type => $message): ?>
      <div class="flash <?= $e($type) ?>"><?= $e($message) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
</body>
</html>
