<h3 style="text-align:center;">Set a new password</h3>
<p class="text-dim" style="font-size:12.5px;text-align:center;">
  Your password must be changed before you can continue.
</p>
<form method="post" action="/force-password">
  <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
  <label for="password">New password</label>
  <input type="password" id="password" name="password" required minlength="12" maxlength="255">
  <label for="password_confirm">Confirm new password</label>
  <input type="password" id="password_confirm" name="password_confirm" required minlength="12" maxlength="255">
  <p class="text-dim" style="font-size:12px;">
    Minimum 12 characters with uppercase, lowercase and numbers.
  </p>
  <button class="btn primary" type="submit" style="width:100%;justify-content:center;">Save password</button>
</form>
