/* Teacher management: list, create (with user account), edit, archive, password reset. */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };
  let rows = [];

  async function load() {
    const res = await App.get('/teachers/data', {
      search: document.getElementById('search').value.trim(),
      page: state.page, per_page: state.per_page,
    });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    App.renderTable('#table', [
      { key: 'employee_number', label: 'Employee #' },
      { label: 'Name', render: (r) => App.esc(`${r.last_name}, ${r.first_name}`) },
      { key: 'department', label: 'Department' },
      { label: 'Subjects', render: (r) => App.esc(r.subjects || '—') },
      { label: 'Fingerprint', render: (r) => r.fingerprint_status
          ? App.statusBadge(r.fingerprint_status, r.fingerprint_status)
          : '<span class="badge yellow">not enrolled</span>' },
      { label: 'Account', render: (r) => App.statusBadge(r.account_status, r.account_status) },
      { label: 'Last Login', render: (r) => App.esc(r.last_login || 'never') },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-edit="${r.id}">Edit</button>
          <button class="btn sm warning" data-reset="${r.id}">Reset PW</button>
          <button class="btn sm danger" data-archive="${r.id}">Archive</button>` },
    ], rows);
    App.renderPagination('#pager', res.data.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });
    bind();
  }

  function bind() {
    document.querySelectorAll('[data-edit]').forEach((b) =>
      b.onclick = () => openForm(rows.find((r) => String(r.id) === b.dataset.edit)));
    document.querySelectorAll('[data-archive]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Archive this teacher and deactivate their account?')) return;
      const res = await App.post(`/teachers/${b.dataset.archive}/archive`, {});
      App.toast(res.message, res.success ? 'success' : 'error');
      load();
    });
    document.querySelectorAll('[data-reset]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Reset this teacher’s password? A temporary password will be generated.')) return;
      const res = await App.post(`/teachers/${b.dataset.reset}/reset-password`, {});
      if (res.success) {
        App.openModal('Temporary password', `
          <p>Share this temporary password securely. The teacher must change it at next login.</p>
          <p class="mono" style="font-size:18px;">${App.esc(res.data.temporary_password)}</p>`);
      } else {
        App.toast(res.message, 'error');
      }
    });
  }

  function openForm(existing = null) {
    const modal = App.openModal(existing ? 'Edit Teacher' : 'Add Teacher',
      document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">${existing ? 'Update' : 'Register'}</button>`);
    const form = modal.querySelector('#teacher-form');
    if (existing) {
      modal.querySelector('.new-only').remove();
      modal.querySelector('.edit-only').style.display = '';
      for (const [key, value] of Object.entries(existing)) {
        const field = form.elements[key];
        if (field && value != null && field.type !== 'select-multiple') field.value = value;
      }
    } else {
      form.elements.username.required = true;
      form.elements.password.required = true;
    }
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(form, existing ? `/teachers/${existing.id}/update` : '/teachers', { reload: load });
  }

  document.getElementById('btn-add').onclick = () => openForm();
  let timer;
  document.getElementById('search').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { state.page = 1; load(); }, 350);
  });

  load();
})();
