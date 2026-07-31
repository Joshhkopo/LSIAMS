/* Attendance table: filtering, detail modal with audit history, admin correction, sessions. */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };
  const isAdmin = !!document.getElementById('tpl-correct');
  let rows = [];

  const params = () => ({
    search: document.getElementById('search').value.trim(),
    date_from: document.getElementById('f-from').value,
    date_to: document.getElementById('f-to').value,
    status: document.getElementById('f-status').value,
    teacher_id: document.getElementById('f-teacher')?.value || '',
    section_id: document.getElementById('f-section').value,
    subject_id: document.getElementById('f-subject').value,
    page: state.page, per_page: state.per_page,
  });

  async function load() {
    const res = await App.get('/attendance/data', params());
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    App.renderTable('#table', [
      { key: 'student_name', label: 'Student' },
      { key: 'student_number', label: 'Number' },
      { key: 'section_name', label: 'Section' },
      { key: 'subject_name', label: 'Subject' },
      { key: 'room_number', label: 'Room' },
      { key: 'teacher_name', label: 'Teacher' },
      { label: 'Time In', render: (r) => App.esc(r.recorded_at) },
      { label: 'Status', render: (r) => App.statusBadge(r.status_code, r.status_name) },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-detail="${r.id}">Detail</button>
          ${isAdmin ? `<button class="btn sm warning" data-correct="${r.id}">Correct</button>` : ''}` },
    ], rows);
    App.renderPagination('#pager', res.data.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });
    bind();
  }

  function bind() {
    document.querySelectorAll('[data-detail]').forEach((b) => b.onclick = () => detail(b.dataset.detail));
    document.querySelectorAll('[data-correct]').forEach((b) => b.onclick = () => correct(b.dataset.correct));
  }

  async function detail(id) {
    const res = await App.get(`/attendance/${id}/detail`);
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const r = res.data.record;
    const history = res.data.history.map((h) => `
      <tr><td>${App.esc(h.created_at)}</td><td>${App.esc(h.admin_username)}</td>
      <td>${App.esc(h.old_status)} → ${App.esc(h.new_status)}</td><td>${App.esc(h.reason)}</td></tr>`).join('');
    App.openModal(`Attendance #${r.id}`, `
      <p><strong>Student:</strong> ${App.esc(r.student_name)} (${App.esc(r.student_number)})<br>
      <strong>Session:</strong> <span class="mono">${App.esc(r.session_code)}</span> —
        ${App.esc(r.subject_name)}, ${App.esc(r.section_name)}, ${App.esc(r.room_number)}<br>
      <strong>Teacher:</strong> ${App.esc(r.teacher_name)}<br>
      <strong>Recorded:</strong> ${App.esc(r.recorded_at)} &nbsp;
      <strong>Source:</strong> ${App.esc(r.source)}<br>
      <strong>RFID:</strong> <span class="mono">${App.esc(r.rfid_uid || '—')}</span> &nbsp;
      <strong>IP:</strong> <span class="mono">${App.esc(r.ip_address || '—')}</span><br>
      <strong>Status:</strong> ${App.statusBadge(r.status_code, r.status_name)}</p>
      <h4>Modification history</h4>
      <table class="data"><thead><tr><th>When</th><th>Admin</th><th>Change</th><th>Reason</th></tr></thead>
      <tbody>${history || '<tr><td colspan="4" class="empty">Never modified.</td></tr>'}</tbody></table>`);
  }

  function correct(id) {
    const modal = App.openModal('Correct Attendance', document.getElementById('tpl-correct').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn warning" data-act="save">Apply correction</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#correct-form'), `/attendance/${id}/correct`, { reload: load });
  }

  async function sessions() {
    const res = await App.get('/attendance/sessions', { per_page: 50 });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const body = res.data.rows.map((s) => `
      <tr><td><span class="mono">${App.esc(s.session_code)}</span></td>
      <td>${App.esc(s.teacher_name)}</td><td>${App.esc(s.subject_name)}</td>
      <td>${App.esc(s.section_name)}</td><td>${App.esc(s.room_number)}</td>
      <td>${App.esc(s.started_at)}</td><td>${s.record_count}</td>
      <td>${App.statusBadge(s.status, s.status)}</td>
      <td>${s.status === 'open' ? `<button class="btn sm danger" data-close="${s.id}">Close</button>` : ''}</td></tr>`).join('');
    const modal = App.openModal('Attendance Sessions', `
      <table class="data"><thead><tr><th>Code</th><th>Teacher</th><th>Subject</th><th>Section</th>
      <th>Room</th><th>Started</th><th>Records</th><th>Status</th><th></th></tr></thead>
      <tbody>${body || '<tr><td colspan="9" class="empty">No sessions.</td></tr>'}</tbody></table>`);
    modal.querySelectorAll('[data-close]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Close this session and generate absents?')) return;
      const res2 = await App.post(`/attendance/sessions/${b.dataset.close}/close`, {});
      App.toast(res2.message, res2.success ? 'success' : 'error');
      App.closeModal(); load();
    });
  }

  document.getElementById('btn-sessions').onclick = sessions;
  let timer;
  document.getElementById('search').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { state.page = 1; load(); }, 350);
  });
  ['f-from', 'f-to', 'f-status', 'f-teacher', 'f-section', 'f-subject'].forEach((id) =>
    document.getElementById(id)?.addEventListener('change', () => { state.page = 1; load(); }));

  load();
})();
