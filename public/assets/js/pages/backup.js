/* Backup & restore with password re-authentication. */
'use strict';

(() => {
  document.getElementById('btn-create').onclick = async () => {
    if (!await App.confirmDialog('Create a database backup now?')) return;
    const btn = document.getElementById('btn-create');
    btn.disabled = true; btn.textContent = 'Backing up…';
    const res = await App.post('/backups/create', {});
    App.toast(res.message, res.success ? 'success' : 'error', 6000);
    setTimeout(() => window.location.reload(), 1200);
  };

  document.querySelectorAll('[data-restore]').forEach((b) => b.onclick = () => {
    const modal = App.openModal('Restore backup', document.getElementById('tpl-restore').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn danger" data-act="save">Restore database</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = async () => {
      const res = await App.submitForm(modal.querySelector('#restore-form'),
        `/backups/${b.dataset.restore}/restore`, { close: true });
      if (res.success) setTimeout(() => window.location.reload(), 1500);
    };
  });
})();
