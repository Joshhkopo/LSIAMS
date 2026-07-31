/* Profile: contact info update + password change. */
'use strict';

(() => {
  document.getElementById('btn-profile').onclick = async () => {
    const form = document.getElementById('profile-form');
    if (!form.reportValidity()) return;
    const res = await App.post('/profile/update', new FormData(form));
    App.toast(res.message, res.success ? 'success' : 'error');
  };

  document.getElementById('btn-password').onclick = async () => {
    const form = document.getElementById('password-form');
    if (!form.reportValidity()) return;
    const res = await App.post('/profile/password', new FormData(form));
    App.toast(res.message, res.success ? 'success' : 'error', 5000);
    if (res.success) form.reset();
  };
})();
