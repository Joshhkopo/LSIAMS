/* Report builder: preview, printable page (browser PDF), CSV export. */
'use strict';

(() => {
  const params = () => ({
    date_from: document.getElementById('f-from').value,
    date_to: document.getElementById('f-to').value,
    status: document.getElementById('f-status').value,
    teacher_id: document.getElementById('f-teacher')?.value || '',
    section_id: document.getElementById('f-section').value,
    subject_id: document.getElementById('f-subject').value,
  });

  async function preview() {
    const res = await App.get('/reports/data', params());
    if (!res.success) { App.toast(res.message, 'error'); return; }

    const summaryHost = document.getElementById('summary');
    summaryHost.innerHTML = Object.entries(res.data.summary).map(([label, total]) => `
      <div class="stat"><div class="stat-label">${App.esc(label)}</div>
      <div class="stat-value">${total}</div></div>`).join('')
      || '<p class="text-dim">No data for the chosen filters.</p>';

    App.renderTable('#table', [
      { label: 'Date', render: (r) => App.esc(String(r.recorded_at).slice(0, 10)) },
      { label: 'Time', render: (r) => App.esc(String(r.recorded_at).slice(11, 19)) },
      { key: 'student_number', label: 'Number' },
      { key: 'student_name', label: 'Student' },
      { key: 'section_name', label: 'Section' },
      { key: 'subject_name', label: 'Subject' },
      { key: 'teacher_name', label: 'Teacher' },
      { label: 'Status', render: (r) => App.statusBadge(r.status_code, r.status_name) },
    ], res.data.rows, 'No attendance records match these filters.');
  }

  document.getElementById('btn-preview').onclick = preview;
  document.getElementById('btn-csv').onclick = () =>
    window.location.href = '/reports/export?' + new URLSearchParams(params());
  document.getElementById('btn-print').onclick = () =>
    window.open('/reports/print?' + new URLSearchParams(params()), '_blank');

  preview();
})();
