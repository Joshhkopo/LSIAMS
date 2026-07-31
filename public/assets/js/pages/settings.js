/* System settings with password re-authentication. */
'use strict';

(() => {
  document.getElementById('btn-save').onclick = async () => {
    const form = document.getElementById('settings-form');
    if (!form.reportValidity()) return;
    const res = await App.post('/settings', new FormData(form));
    App.toast(res.message, res.success ? 'success' : 'error', 5000);
    if (res.success) form.elements.confirm_password.value = '';
  };
})();
