/* IoT device management: register, API key rotation, enable/disable, logs, health cards. */
'use strict';

(() => {
  let rows = [];

  async function load() {
    const res = await App.get('/devices/data');
    if (!res.success) { App.toast(res.message, 'error'); return; }
    rows = res.data.rows;
    const host = document.getElementById('cards');
    host.innerHTML = rows.length ? rows.map(card).join('')
      : '<p class="text-dim">No devices registered yet.</p>';
    bind();
  }

  const card = (d) => `
    <div class="card" style="margin-bottom:0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;"><span class="mono">${App.esc(d.device_code)}</span> — ${App.esc(d.device_name)}</h3>
        ${App.onlineBadge(Number(d.is_online) === 1)}
      </div>
      <p style="margin:10px 0;">
        <strong>Room:</strong> ${App.esc(d.room_number || 'unassigned')} &nbsp;
        <strong>IP:</strong> <span class="mono">${App.esc(d.ip_address || '—')}</span><br>
        <strong>MAC:</strong> <span class="mono">${App.esc(d.mac_address)}</span> &nbsp;
        <strong>FW:</strong> ${App.esc(d.firmware_version || '—')}<br>
        <strong>Signal:</strong> ${d.signal_strength != null ? d.signal_strength + ' dBm' : '—'} &nbsp;
        <strong>Queue:</strong> ${d.queue_count ?? 0} &nbsp;
        <strong>Heartbeat:</strong> ${App.esc(d.last_heartbeat || 'never')}<br>
        <strong>API key:</strong> ${d.key_status === 'active'
          ? `<span class="badge green">active (${App.esc(d.key_hint)}…)</span>`
          : '<span class="badge red">none / revoked</span>'} &nbsp;
        <strong>Status:</strong> ${App.statusBadge(d.status, d.status)}
      </p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn sm" data-edit="${d.id}">Edit</button>
        <button class="btn sm" data-logs="${d.id}">Logs</button>
        <button class="btn sm warning" data-key="${d.id}">Rotate API Key</button>
        ${d.status === 'active'
          ? `<button class="btn sm danger" data-set="${d.id}" data-status="disabled">Disable</button>`
          : `<button class="btn sm success" data-set="${d.id}" data-status="active">Enable</button>`}
      </div>
    </div>`;

  function bind() {
    document.querySelectorAll('[data-edit]').forEach((b) =>
      b.onclick = () => openForm(rows.find((r) => String(r.id) === b.dataset.edit)));
    document.querySelectorAll('[data-key]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog('Rotate this device’s API key? The current key stops working immediately.')) return;
      const res = await App.post(`/devices/${b.dataset.key}/apikey`, {});
      if (res.success) {
        App.openModal('New API key', `
          <p>${App.esc(res.message)}</p>
          <p class="mono" style="word-break:break-all;font-size:15px;">${App.esc(res.data.api_key)}</p>`);
        load();
      } else App.toast(res.message, 'error');
    });
    document.querySelectorAll('[data-set]').forEach((b) => b.onclick = async () => {
      if (!await App.confirmDialog(`Set device status to '${b.dataset.status}'?`)) return;
      const res = await App.post(`/devices/${b.dataset.set}/status`, { status: b.dataset.status });
      App.toast(res.message, res.success ? 'success' : 'error');
      load();
    });
    document.querySelectorAll('[data-logs]').forEach((b) => b.onclick = async () => {
      const res = await App.get(`/devices/${b.dataset.logs}/logs`);
      if (!res.success) { App.toast(res.message, 'error'); return; }
      const body = res.data.rows.map((l) => `
        <tr><td>${App.esc(l.created_at)}</td><td>${App.statusBadge(l.event, l.event)}</td>
        <td>${App.esc(l.details || '')}</td></tr>`).join('');
      App.openModal('Device logs', `
        <table class="data"><thead><tr><th>Time</th><th>Event</th><th>Details</th></tr></thead>
        <tbody>${body || '<tr><td colspan="3" class="empty">No logs.</td></tr>'}</tbody></table>`);
    });
  }

  function openForm(existing = null) {
    const modal = App.openModal(existing ? 'Edit Device' : 'Register Device',
      document.getElementById('tpl-form').innerHTML,
      `<button class="btn" data-act="cancel">Cancel</button>
       <button class="btn primary" data-act="save">${existing ? 'Update' : 'Register'}</button>`);
    const form = modal.querySelector('#device-form');
    if (existing) {
      for (const [key, value] of Object.entries(existing)) {
        if (form.elements[key] && value != null) form.elements[key].value = value;
      }
      // Identity fields are immutable after registration.
      form.elements.device_code.disabled = true;
      form.elements.mac_address.disabled = true;
      form.elements.firmware_version.disabled = true;
    }
    modal.querySelector('[data-act=cancel]').onclick = App.closeModal;
    modal.querySelector('[data-act=save]').onclick = async () => {
      const res = await App.submitForm(form, existing ? `/devices/${existing.id}/update` : '/devices', { close: false, reload: load });
      if (res.success && !existing && res.data.api_key) {
        App.openModal('Device registered — API key', `
          <p>Copy this API key into the device firmware now. <strong>It will not be shown again.</strong></p>
          <p class="mono" style="word-break:break-all;font-size:15px;">${App.esc(res.data.api_key)}</p>`);
      } else if (res.success) {
        App.closeModal();
      }
    };
  }

  document.getElementById('btn-add').onclick = () => openForm();
  load();
  setInterval(load, 20000);
})();
