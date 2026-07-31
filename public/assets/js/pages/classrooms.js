/* Classroom management CRUD. */
'use strict';

(() => {
  let rows = [];

  async function load() {
    const res = await App.get('/classrooms/data');
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    App.renderTable('#table', [
      { key: 'room_number', label: 'Room' },
      { key: 'building', label: 'Building' },
      { key: 'floor', label: 'Floor' },
      { key: 'capacity', label: 'Capacity' },
      { label: 'Device', render: (r) => r.device_code
          ? `<span class="mono">${App.esc(r.device_code)}</span> ${App.onlineBadge(Number(r.is_online) === 1)}`
          : '<span class="badge yellow">no device</span>' },
      { label: 'Status', render: (r) => App.statusBadge(r.status, r.status) },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-edit="${r.id}">Edit</button>
          <button class="btn sm danger" data-archive="${r.id}">Archive</button>` },
    ], rows);
    document.querySelectorAll('[data-edit]').forEach((b) =>
      b.onclick = () => openForm(rows.find((r) => String(r.id) === b.dataset.edit)));
    document.querySelectorAll('[data-archive]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Archive this classroom?')) return;
      const res2 = await App.post(`/classrooms/${b.dataset.archive}/archive`, {});
      App.toast(res2.message, res2.success ? 'success' : 'error');
      load();
    });
  }

  function openForm(existing = null) {
    const modal = App.openModal(existing ? 'Edit Classroom' : 'Add Classroom',
      document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">${existing ? 'Update' : 'Create'}</button>`);
    const form = modal.querySelector('#classroom-form');
    if (existing) {
      for (const [key, value] of Object.entries(existing)) {
        if (form.elements[key] && value != null) form.elements[key].value = value;
      }
    }
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(form, existing ? `/classrooms/${existing.id}/update` : '/classrooms', { reload: load });
  }

  document.getElementById('btn-add').onclick = () => openForm();
  load();
})();
