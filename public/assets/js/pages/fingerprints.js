/* Fingerprint enrollment management. */
'use strict';

(() => {
  async function load() {
    const res = await App.get('/fingerprints/data');
    if (!res.success) { App.toast(res.message, 'error'); return; }
    App.renderTable('#table', [
      { key: 'teacher_name', label: 'Teacher' },
      { key: 'employee_number', label: 'Employee #' },
      { label: 'Device / Slot', render: (r) =>
          `<span class="mono">${App.esc(r.device_code || '—')} #${App.esc(r.sensor_template_id)}</span>` },
      { key: 'enrolled_at', label: 'Enrolled' },
      { key: 'verification_count', label: 'Verifications' },
      { label: 'Status', render: (r) => App.statusBadge(r.status, r.status) },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-toggle="${r.id}" data-next="${r.status === 'active' ? 'disabled' : 'active'}">
            ${r.status === 'active' ? 'Disable' : 'Enable'}</button>
          <button class="btn sm danger" data-del="${r.id}">Delete</button>` },
    ], res.data.rows, 'No fingerprints enrolled.');

    document.querySelectorAll('[data-toggle]').forEach((b) => b.onclick = async () => {
      const res2 = await App.post(`/fingerprints/${b.dataset.toggle}/status`, { status: b.dataset.next });
      App.toast(res2.message, res2.success ? 'success' : 'error');
      load();
    });
    document.querySelectorAll('[data-del]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Delete this fingerprint enrollment? The teacher will be unable to open attendance until re-enrolled.')) return;
      const res2 = await App.post(`/fingerprints/${b.dataset.del}/delete`, {});
      App.toast(res2.message, res2.success ? 'success' : 'error');
      load();
    });
  }

  document.getElementById('btn-enroll').onclick = () => {
    const modal = App.openModal('Enroll Fingerprint', document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">Enroll</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#fp-form'), '/fingerprints/enroll', { reload: load });
  };

  load();
})();
