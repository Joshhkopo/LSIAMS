/* Schedule management with conflict-checked CRUD (admin) / read-only (teacher). */
'use strict';

(() => {
  const DAYS = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday' };
  const isAdmin = !!document.getElementById('btn-add');
  let rows = [];

  async function load() {
    const res = await App.get('/schedules/data', {
      day_of_week: document.getElementById('f-day').value,
      teacher_id: document.getElementById('f-teacher')?.value || '',
      classroom_id: document.getElementById('f-room')?.value || '',
    });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    const columns = [
      { label: 'Day', render: (r) => DAYS[r.day_of_week] || r.day_of_week },
      { label: 'Time', render: (r) => `${String(r.start_time).slice(0, 5)}–${String(r.end_time).slice(0, 5)}` },
      { key: 'subject_code', label: 'Subject' },
      { key: 'teacher_name', label: 'Teacher' },
      { key: 'section_name', label: 'Section' },
      { key: 'room_number', label: 'Room' },
      { label: 'Window / Late', render: (r) => `${r.attendance_window_minutes}m / ${r.late_threshold_minutes}m` },
    ];
    if (isAdmin) {
      columns.push({ label: 'Actions', render: (r) => `
        <button class="btn sm" data-edit="${r.id}">Edit</button>
        <button class="btn sm danger" data-archive="${r.id}">Archive</button>` });
    }
    App.renderTable('#table', columns, rows, 'No schedules found.');
    if (isAdmin) bind();
  }

  function bind() {
    document.querySelectorAll('[data-edit]').forEach((b) =>
      b.onclick = () => openForm(rows.find((r) => String(r.id) === b.dataset.edit)));
    document.querySelectorAll('[data-archive]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Archive this schedule?')) return;
      const res = await App.post(`/schedules/${b.dataset.archive}/archive`, {});
      App.toast(res.message, res.success ? 'success' : 'error');
      load();
    });
  }

  function openForm(existing = null) {
    const modal = App.openModal(existing ? 'Edit Schedule' : 'Add Schedule',
      document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">${existing ? 'Update' : 'Create'}</button>`);
    const form = modal.querySelector('#schedule-form');
    if (existing) {
      for (const [key, value] of Object.entries(existing)) {
        if (form.elements[key] && value != null) {
          form.elements[key].value = ['start_time', 'end_time'].includes(key)
            ? String(value).slice(0, 5) : value;
        }
      }
    }
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(form, existing ? `/schedules/${existing.id}/update` : '/schedules', { reload: load });
  }

  if (isAdmin) document.getElementById('btn-add').onclick = () => openForm();
  ['f-day', 'f-teacher', 'f-room'].forEach((id) =>
    document.getElementById(id)?.addEventListener('change', load));

  load();
})();
