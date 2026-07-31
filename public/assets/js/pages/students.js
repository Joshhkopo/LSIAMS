/* Student management: AJAX search/filter/pagination + CRUD modals + import/export. */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };
  let rows = [];

  const params = () => ({
    search: document.getElementById('search').value.trim(),
    status: document.getElementById('f-status').value,
    section_id: document.getElementById('f-section').value,
    grade_level_id: document.getElementById('f-grade').value,
    page: state.page, per_page: state.per_page,
  });

  async function load() {
    const res = await App.get('/students/data', params());
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    App.renderTable('#table', [
      { key: 'student_number', label: 'Number' },
      { label: 'Name', render: (r) => App.esc(`${r.last_name}, ${r.first_name} ${r.middle_name || ''}`.trim()) },
      { key: 'grade_name', label: 'Grade' },
      { key: 'section_name', label: 'Section' },
      { label: 'RFID', render: (r) => r.rfid_uid ? `<span class="mono">${App.esc(r.rfid_uid)}</span>` : '<span class="badge yellow">none</span>' },
      { label: 'Status', render: (r) => App.statusBadge(r.status, r.status) },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-view="${r.id}">View</button>
          <button class="btn sm" data-edit="${r.id}">Edit</button>
          <button class="btn sm danger" data-archive="${r.id}">Archive</button>` },
    ], rows);
    App.renderPagination('#pager', res.data.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });
    bindRowActions();
  }

  function bindRowActions() {
    document.querySelectorAll('[data-view]').forEach((b) => b.onclick = () => viewStudent(b.dataset.view));
    document.querySelectorAll('[data-edit]').forEach((b) => b.onclick = () => openForm(rows.find((r) => String(r.id) === b.dataset.edit)));
    document.querySelectorAll('[data-archive]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Archive this student? Attendance history is preserved.')) return;
      const res = await App.post(`/students/${b.dataset.archive}/archive`, {});
      App.toast(res.message, res.success ? 'success' : 'error');
      load();
    });
  }

  async function viewStudent(id) {
    const res = await App.get(`/students/${id}/detail`);
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const s = res.data.student, rfid = res.data.rfid;
    const att = res.data.attendance.map((a) =>
      `<tr><td>${App.statusBadge(a.code, a.name)}</td><td>${a.total}</td></tr>`).join('');
    App.openModal(`${s.first_name} ${s.last_name}`, `
      <p><strong>Student Number:</strong> ${App.esc(s.student_number)}<br>
      <strong>Gender:</strong> ${App.esc(s.gender)} &nbsp; <strong>Birthdate:</strong> ${App.esc(s.birthdate || '—')}<br>
      <strong>Guardian:</strong> ${App.esc(s.guardian_name || '—')} (${App.esc(s.guardian_contact || '—')})<br>
      <strong>RFID:</strong> ${rfid ? `<span class="mono">${App.esc(rfid.uid)}</span>` : 'not assigned'}<br>
      <strong>Status:</strong> ${App.statusBadge(s.status, s.status)}</p>
      <h4>Attendance summary</h4>
      <table class="data"><thead><tr><th>Status</th><th>Total</th></tr></thead>
      <tbody>${att || '<tr><td colspan="2" class="empty">No attendance yet.</td></tr>'}</tbody></table>`);
  }

  function openForm(existing = null) {
    const body = document.getElementById('tpl-form').innerHTML;
    const modal = App.openModal(existing ? 'Edit Student' : 'Add Student', body,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">${existing ? 'Update' : 'Register'}</button>`);
    const form = modal.querySelector('#student-form');
    if (existing) {
      for (const [key, value] of Object.entries(existing)) {
        const field = form.elements[key];
        if (field && value != null) field.value = value;
      }
      form.elements.rfid_uid?.closest('div')?.remove(); // RFID is managed on the RFID page after creation
    }
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(form, existing ? `/students/${existing.id}/update` : '/students', { reload: load });
  }

  function openImport() {
    const modal = App.openModal('Import Students', document.getElementById('tpl-import').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">Import</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#import-form'), '/students/import', { reload: load });
  }

  document.getElementById('btn-add').onclick = () => openForm();
  document.getElementById('btn-import').onclick = openImport;
  document.getElementById('btn-export').onclick = () => {
    const qs = new URLSearchParams(params());
    window.location.href = '/students/export?' + qs;
  };

  let timer;
  document.getElementById('search').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { state.page = 1; load(); }, 350);
  });
  ['f-status', 'f-section', 'f-grade'].forEach((id) =>
    document.getElementById(id).addEventListener('change', () => { state.page = 1; load(); }));

  load();
})();
