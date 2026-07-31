<?php $pageScript = 'profile'; ?>
<div class="grid cols-2">
  <div class="card">
    <h3>Profile Information</h3>
    <form id="profile-form" enctype="multipart/form-data">
      <label>Username</label>
      <input type="text" value="<?= $e($currentUser['username'] ?? '') ?>" disabled>
      <label>Role</label>
      <input type="text" value="<?= $e($currentUser['role_name'] ?? '') ?>" disabled>
      <?php if ($teacher !== null): ?>
        <label>Employee Number</label>
        <input type="text" value="<?= $e($teacher['employee_number']) ?>" disabled>
        <label>Department</label>
        <input type="text" value="<?= $e($teacher['department'] ?? '') ?>" disabled>
      <?php endif; ?>
      <label>Email *</label>
      <input type="email" name="email" required maxlength="150" value="<?= $e($currentUser['email'] ?? '') ?>">
      <?php if ($teacher !== null): ?>
        <label>Phone</label>
        <input type="text" name="phone" maxlength="30" value="<?= $e($teacher['phone'] ?? '') ?>">
      <?php endif; ?>
      <label>Photo (JPG/PNG, max 2 MB)</label>
      <input type="file" name="photo" accept=".jpg,.jpeg,.png">
      <button type="button" class="btn primary" id="btn-profile" style="margin-top:12px;">Save Profile</button>
    </form>
  </div>

  <div class="card">
    <h3>Change Password</h3>
    <form id="password-form">
      <label>Current Password *</label>
      <input type="password" name="current_password" required maxlength="255" autocomplete="current-password">
      <label>New Password *</label>
      <input type="password" name="password" required minlength="12" maxlength="255" autocomplete="new-password">
      <label>Confirm New Password *</label>
      <input type="password" name="password_confirm" required minlength="12" maxlength="255" autocomplete="new-password">
      <p class="text-dim" style="font-size:12px;">Minimum 12 characters with uppercase, lowercase and numbers.</p>
      <button type="button" class="btn primary" id="btn-password">Change Password</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>Login History</h3>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Time</th><th>IP Address</th><th>Browser</th><th>Result</th></tr></thead>
      <tbody>
        <?php if ($loginHistory === []): ?>
          <tr><td colspan="4" class="empty">No login history.</td></tr>
        <?php endif; ?>
        <?php foreach ($loginHistory as $h): ?>
          <tr>
            <td><?= $e($h['created_at']) ?></td>
            <td class="mono"><?= $e($h['ip_address']) ?></td>
            <td><?= $e(mb_strimwidth((string) $h['user_agent'], 0, 60, '…')) ?></td>
            <td><span class="badge <?= $h['status'] === 'success' ? 'green' : 'red' ?>"><?= $e($h['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
