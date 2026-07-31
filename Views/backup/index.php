<?php $pageScript = 'backup'; ?>
<div class="card">
  <div class="toolbar">
    <div class="spacer"></div>
    <button class="btn primary" id="btn-create">Create Backup Now</button>
  </div>
  <p class="text-dim" style="font-size:12.5px;">
    Backups run mysqldump + gzip (AES-encrypted when a key is configured).
    Restoring requires your administrator password and is fully audited.
  </p>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Name</th><th>Size</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($backups === []): ?>
          <tr><td colspan="5" class="empty">No backups yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($backups as $b): ?>
          <tr>
            <td class="mono"><?= $e($b['name']) ?></td>
            <td><?= $b['size_bytes'] !== null ? $e(number_format((int) $b['size_bytes'] / 1024, 1)) . ' KB' : '—' ?></td>
            <td><span class="badge <?= $b['status'] === 'completed' ? 'green' : ($b['status'] === 'failed' ? 'red' : 'blue') ?>"><?= $e($b['status']) ?></span></td>
            <td><?= $e($b['created_at']) ?></td>
            <td>
              <?php if ($b['status'] !== 'failed'): ?>
                <button class="btn sm danger" data-restore="<?= $e($b['id']) ?>">Restore</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<template id="tpl-restore">
  <form id="restore-form">
    <p><strong>Warning:</strong> restoring replaces the current database with this backup.
    All records created after the backup will be lost.</p>
    <label>Your Password *</label>
    <input type="password" name="confirm_password" required maxlength="255">
  </form>
</template>
