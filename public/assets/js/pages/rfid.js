/* RFID management: register, replace, status changes, tap history, unknown-card queue. */
'use strict';

(() => {
  const state = { page: 1, per_page: 25 };
  let rows = [];

  async function load() {
    const res = await App.get('/rfid/data', {
      search: document.getElementById('search').value.trim(),
      page: state.page, per_page: state.per_page,
    });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    App.renderTable('#table', [
      { label: 'UID', render: (r) => `<span class="mono">${App.esc(r.uid)}</span>` },
      { key: 'student_name', label: 'Student' },
      { key: 'student_number', label: 'Number' },
      { key: 'issue_date', label: 'Issued' },
      { label: 'Status', render: (r) => App.statusBadge(r.status, r.status) },
      { label: 'Actions', render: (r) => `
          <button class="btn sm" data-history="${r.uid}">History</button>
          <button class="btn sm" data-replace="${r.id}">Replace</button>
          <select class="btn sm" data-status="${r.id}">
            <option value="">Set status…</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="lost">Lost</option>
            <option value="blacklisted">Blacklisted</option>
          </select>` },
    ], rows);
    App.renderPagination('#pager', res.data.total, state.page, state.per_page,
      (p) => { state.page = p; load(); });
    bind();
  }

  function bind() {
    document.querySelectorAll('[data-history]').forEach((b) => b.onclick = () => history(b.dataset.history));
    document.querySelectorAll('[data-replace]').forEach((b) => b.onclick = () => replaceCard(b.dataset.replace));
    document.querySelectorAll('[data-status]').forEach((sel) => sel.onchange = async () => {
      if (!sel.value) return;
      const res = await App.post(`/rfid/${sel.dataset.status}/status`, { status: sel.value });
      App.toast(res.message, res.success ? 'success' : 'error');
      load();
    });
  }

  async function history(uid) {
    const res = await App.get(`/rfid/${uid}/history`);
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const body = res.data.rows.map((r) => `
      <tr><td>${App.esc(r.created_at)}</td><td>${App.esc(r.device_code || '—')}</td>
      <td>${App.statusBadge(r.result, r.result)}</td><td>${App.esc(r.details || '')}</td></tr>`).join('');
    App.openModal(`Tap history — ${uid}`, `
      <table class="data"><thead><tr><th>Time</th><th>Device</th><th>Result</th><th>Details</th></tr></thead>
      <tbody>${body || '<tr><td colspan="4" class="empty">No taps recorded.</td></tr>'}</tbody></table>`);
  }

  function replaceCard(id) {
    const modal = App.openModal('Replace RFID Card', document.getElementById('tpl-replace').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">Replace</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#replace-form'), `/rfid/${id}/replace`, { reload: load });
  }

  async function unknownCards() {
    const res = await App.get('/rfid/unknown');
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const body = res.data.rows.map((r) => `
      <tr><td><span class="mono">${App.esc(r.uid)}</span></td>
      <td>${r.seen_count}</td><td>${App.esc(r.last_seen)}</td>
      <td>${App.statusBadge(r.status, r.status)}</td>
      <td>
        <button class="btn sm" data-uact="ignored" data-uid="${r.id}">Ignore</button>
        <button class="btn sm danger" data-uact="blacklisted" data-uid="${r.id}">Blacklist</button>
      </td></tr>`).join('');
    const modal = App.openModal('Unknown RFID Cards', `
      <p class="text-dim" style="font-size:12.5px;">Cards tapped on terminals but not registered to any student.
      Assign them from “Register Card” using the UID shown here.</p>
      <table class="data"><thead><tr><th>UID</th><th>Seen</th><th>Last Seen</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>${body || '<tr><td colspan="5" class="empty">No unknown cards.</td></tr>'}</tbody></table>`);
    modal.querySelectorAll('[data-uact]').forEach((b) => b.onclick = async () => {
      const res2 = await App.post(`/rfid/unknown/${b.dataset.uid}/status`, { status: b.dataset.uact });
      App.toast(res2.message, res2.success ? 'success' : 'error');
      App.closeModal(); unknownCards();
    });
  }

  document.getElementById('btn-add').onclick = () => {
    const modal = App.openModal('Register RFID Card', document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">Register</button>`);
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = () =>
      App.submitForm(modal.querySelector('#rfid-form'), '/rfid', { reload: load });
  };
  document.getElementById('btn-unknown').onclick = unknownCards;

  let timer;
  document.getElementById('search').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { state.page = 1; load(); }, 350);
  });

  load();
})();
