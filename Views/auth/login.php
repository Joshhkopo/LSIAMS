<form method="post" action="/login" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
  <label for="username">Username or Email</label>
  <input type="text" id="username" name="username" required maxlength="150" autofocus>
  <label for="password">Password</label>
  <input type="password" id="password" name="password" required maxlength="255">
  <button class="btn primary" type="submit" style="width:100%;margin-top:18px;justify-content:center;">Sign in</button>
</form>
<p class="text-dim" style="font-size:12px;margin-top:16px;text-align:center;">
  Forgot your password? Contact the system administrator.
</p>
