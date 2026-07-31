/* Audit log viewer (read-only, immutable trail). */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };

  async function load() {
    const res = await App.get('/audit/data', {
      search: document.getElementById('search').value.trim(),
      date_from: document.getElementById('f-from').value,
      date_to: document.getElementById('f-to').value,
      page: state.page, per_page: state.per_page,
    });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    App.renderTable('#table', [
      { key: 'created_at', label: 'Timestamp' },
      { label: 'User', render: (r) => App.esc(r.username || 'system') },
      { key: 'action', label: 'Action' },
      { key: 'module', label: 'Module' },
      { key: 'affected_record', label: 'Record' },
      { label: 'IP', render: (r) => `<span class="mono">${App.esc(r.ip_address || '—')}</span>` },
      { label: 'Changes', render: (r) => (r.old_value || r.new_value)
          ? `<button class="btn sm" data-diff="${r.id}">View</button>` : '' },
    ], res.data.rows, 'No audit records.');
    App.renderPagination('#pager', res.data.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });

    const rows = res.data.rows;
    document.querySelectorAll('[data-diff]').forEach((b) => b.onclick = () => {
      const r = rows.find((x) => String(x.id) === b.dataset.diff);
      App.openModal(`Audit #${r.id} — ${r.action}`, `
        <h4>Old value</h4><pre class="mono" style="white-space:pre-wrap;">${App.esc(r.old_value || '—')}</pre>
        <h4>New value</h4><pre class="mono" style="white-space:pre-wrap;">${App.esc(r.new_value || '—')}</pre>`);
    });
  }

  let timer;
  document.getElementById('search').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { state.page = 1; load(); }, 350);
  });
  ['f-from', 'f-to'].forEach((id) =>
    document.getElementById(id).addEventListener('change', () => { state.page = 1; load(); }));

  load();
})();
