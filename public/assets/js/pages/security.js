/* Security center: event log with resolution workflow, 7-day summary, IP blocking. */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };

  async function load() {
    const res = await App.get('/security/data', {
      severity: document.getElementById('f-severity').value,
      resolved: document.getElementById('f-resolved').value,
      page: state.page, per_page: state.per_page,
    });
    if (!res.success) { App.toast(res.message, 'error'); return; }

    const summaryHost = document.getElementById('summary');
    summaryHost.innerHTML = res.data.summary.slice(0, 8).map((s) => `
      <div class="stat accent-${{ low: 'primary', medium: 'primary', high: 'warning', critical: 'danger' }[s.severity] || 'primary'}">
        <div class="stat-label">${App.esc(s.event)} (${App.esc(s.severity)})</div>
        <div class="stat-value">${s.total}</div>
      </div>`).join('') || '<p class="text-dim">No security events in the last 7 days.</p>';

    App.renderTable('#table', [
      { key: 'created_at', label: 'Time' },
      { key: 'event', label: 'Event' },
      { label: 'Severity', render: (r) => App.statusBadge(r.severity, r.severity) },
      { key: 'description', label: 'Description' },
      { label: 'Source', render: (r) => `<span class="mono">${App.esc(r.source_ip || '—')}</span> ${App.esc(r.device_code || '')}` },
      { label: 'Status', render: (r) => Number(r.resolved) === 1
          ? '<span class="badge green">resolved</span>'
          : '<span class="badge yellow">open</span>' },
      { label: 'Actions', render: (r) => Number(r.resolved) === 1 ? ''
          : `<button class="btn sm" data-resolve="${r.id}">Resolve</button>` },
    ], res.data.logs.rows, 'No security events.');
    App.renderPagination('#pager', res.data.logs.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });

    App.renderTable('#blocked-table', [
      { label: 'IP', render: (r) => `<span class="mono">${App.esc(r.ip_address)}</span>` },
      { key: 'reason', label: 'Reason' },
      { key: 'blocked_by_name', label: 'Blocked By' },
      { key: 'created_at', label: 'Since' },
      { label: 'Actions', render: (r) => `<button class="btn sm" data-unblock="${r.id}">Unblock</button>` },
    ], res.data.blocked_ips, 'No blocked IP addresses.');

    document.querySelectorAll('[data-resolve]').forEach((b) => b.onclick = () => resolve(b.dataset.resolve));
    document.querySelectorAll('[data-unblock]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Unblock this IP address?')) return;
      const res2 = await App.post(`/security/unblock-ip/${b.dataset.unblock}`, {});
      App.toast(res2.message, res2.success ? 'success' : 'error');
      load();
    });
  }

  function resolve(id) {
    const modal = App.openModal('Resolve security event', document.getElementById('tpl-resolve').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">Mark resolved</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#resolve-form'), `/security/logs/${id}/resolve`, { reload: load });
  }

  document.getElementById('btn-block').onclick = () => {
    const modal = App.openModal('Block IP address', document.getElementById('tpl-block').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn danger" data-act="save">Block</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#block-form'), '/security/block-ip', { reload: load });
  };

  ['f-severity', 'f-resolved'].forEach((id) =>
    document.getElementById(id).addEventListener('change', () => { state.page = 1; load(); }));

  load();
  setInterval(load, 30000);
})();
