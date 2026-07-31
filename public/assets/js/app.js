/* =====================================================================
 * L-SIAMS shared frontend framework.
 * - CSRF-aware fetch helpers (JSON envelope: {success, message, data})
 * - Toasts, modal manager, table renderer with pagination
 * - Minimal canvas chart renderer (falls back if Chart.js is absent)
 * - Notification poller and live clock
 * ===================================================================== */
'use strict';

const App = (() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  /* ---------- HTTP ---------- */
  async function request(url, options = {}) {
    const res = await fetch(url, {
      headers: {
        'X-CSRF-Token': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.json ? { 'Content-Type': 'application/json' } : {}),
      },
      method: options.method || 'GET',
      body: options.json ? JSON.stringify(options.json) : options.body,
    });
    let payload;
    try { payload = await res.json(); }
    catch { payload = { success: false, message: 'Unexpected server response.' }; }
    if (res.status === 401) { window.location.href = '/login'; return payload; }
    return payload;
  }
  const get = (url, params) => {
    const qs = params ? '?' + new URLSearchParams(
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
    ) : '';
    return request(url + qs);
  };
  const post = (url, data) => data instanceof FormData
    ? request(url, { method: 'POST', body: data })
    : request(url, { method: 'POST', json: data });

  /* ---------- UI helpers ---------- */
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function toast(message, type = 'success', ms = 3500) {
    let host = document.getElementById('toasts');
    if (!host) { host = document.createElement('div'); host.id = 'toasts'; document.body.appendChild(host); }
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    host.appendChild(el);
    setTimeout(() => el.remove(), ms);
  }

  function openModal(title, bodyHtml, footerHtml = '') {
    closeModal();
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop open';
    backdrop.id = 'app-modal';
    backdrop.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true">
        <header><h3>${esc(title)}</h3>
          <button type="button" class="close-x" aria-label="Close">&times;</button></header>
        <div class="modal-body">${bodyHtml}</div>
        ${footerHtml ? `<footer>${footerHtml}</footer>` : ''}
      </div>`;
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
    backdrop.querySelector('.close-x').addEventListener('click', closeModal);
    document.body.appendChild(backdrop);
    return backdrop;
  }
  function closeModal() { document.getElementById('app-modal')?.remove(); }

  function confirmDialog(message) {
    return new Promise((resolve) => {
      const m = openModal('Please confirm', `<p>${esc(message)}</p>`,
        `<button class="btn" data-act="no">Cancel</button>
         <button class="btn danger" data-act="yes">Confirm</button>`);
      m.querySelector('[data-act=no]').onclick = () => { closeModal(); resolve(false); };
      m.querySelector('[data-act=yes]').onclick = () => { closeModal(); resolve(true); };
    });
  }

  /* ---------- Tables ---------- */
  /**
   * renderTable(container, columns, rows)
   * column: { key, label, render?(row) -> html }
   */
  function renderTable(container, columns, rows, emptyText = 'No records found.') {
    const el = typeof container === 'string' ? document.querySelector(container) : container;
    const head = columns.map((c) => `<th>${esc(c.label)}</th>`).join('');
    const body = rows.length
      ? rows.map((row) => `<tr>${columns.map((c) =>
          `<td>${c.render ? c.render(row) : esc(row[c.key])}</td>`).join('')}</tr>`).join('')
      : `<tr><td class="empty" colspan="${columns.length}">${esc(emptyText)}</td></tr>`;
    el.innerHTML = `<div class="table-wrap"><table class="data">
      <thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
    return el;
  }

  function renderPagination(container, total, page, perPage, onPage) {
    const el = typeof container === 'string' ? document.querySelector(container) : container;
    const pages = Math.max(1, Math.ceil(total / perPage));
    let html = '';
    const btn = (p, label = p, cls = '') =>
      `<button data-page="${p}" class="${cls}" ${p < 1 || p > pages ? 'disabled' : ''}>${label}</button>`;
    html += btn(page - 1, '&lsaquo;');
    const start = Math.max(1, page - 2), end = Math.min(pages, page + 2);
    for (let p = start; p <= end; p++) html += btn(p, p, p === page ? 'current' : '');
    html += btn(page + 1, '&rsaquo;');
    html += `<span class="total">${total} record(s)</span>`;
    el.innerHTML = `<div class="pagination">${html}</div>`;
    el.querySelectorAll('button[data-page]').forEach((b) =>
      b.addEventListener('click', () => onPage(parseInt(b.dataset.page, 10))));
  }

  /* ---------- Badges ---------- */
  const statusBadge = (code, label) => {
    const map = { present: 'green', late: 'yellow', absent: 'red', excused: 'blue',
      official_business: 'blue', incomplete: 'gray', active: 'green', open: 'green',
      inactive: 'gray', archived: 'gray', closed: 'gray', disabled: 'red', lost: 'yellow',
      blacklisted: 'red', locked: 'red', revoked: 'red', low: 'gray', medium: 'blue',
      high: 'yellow', critical: 'red', verified: 'green', failed: 'red', unknown: 'yellow' };
    return `<span class="badge ${map[code] || 'gray'}">${esc(label || code || '—')}</span>`;
  };
  const onlineBadge = (isOnline) => isOnline
    ? '<span class="badge green"><span class="dot green"></span>Online</span>'
    : '<span class="badge red"><span class="dot red"></span>Offline</span>';

  /* ---------- Forms ---------- */
  async function submitForm(form, url, { close = true, reload = null } = {}) {
    const fd = new FormData(form);
    const res = await post(url, fd);
    if (res.success) {
      toast(res.message || 'Saved.', 'success');
      if (close) closeModal();
      if (reload) reload();
    } else {
      toast(res.message || 'Request failed.', 'error', 5000);
    }
    return res;
  }

  /* ---------- Minimal charts (Chart.js used automatically if present) ---------- */
  const palette = ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

  function chartLine(canvas, labels, datasets) {
    if (window.Chart) {
      return new Chart(canvas, { type: 'line',
        data: { labels, datasets: datasets.map((d, i) => ({ ...d, borderColor: palette[i % palette.length], backgroundColor: 'transparent', tension: .3 })) },
        options: { maintainAspectRatio: false } });
    }
    drawAxesChart(canvas, labels, datasets, 'line');
  }
  function chartBar(canvas, labels, datasets) {
    if (window.Chart) {
      return new Chart(canvas, { type: 'bar',
        data: { labels, datasets: datasets.map((d, i) => ({ ...d, backgroundColor: palette[i % palette.length] })) },
        options: { maintainAspectRatio: false } });
    }
    drawAxesChart(canvas, labels, datasets, 'bar');
  }
  function chartDonut(canvas, labels, values) {
    if (window.Chart) {
      return new Chart(canvas, { type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: palette }] },
        options: { maintainAspectRatio: false } });
    }
    drawDonut(canvas, labels, values);
  }

  /* Fallback renderer: simple but readable axes chart. */
  function drawAxesChart(canvas, labels, datasets, kind) {
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.clientWidth * 2, H = canvas.height = canvas.clientHeight * 2;
    ctx.scale(1, 1); ctx.clearRect(0, 0, W, H);
    const pad = 70, plotW = W - pad * 2, plotH = H - pad * 2;
    const max = Math.max(1, ...datasets.flatMap((d) => d.data));
    ctx.strokeStyle = '#e2e8f0'; ctx.fillStyle = '#64748b'; ctx.font = '20px Inter, sans-serif';
    for (let g = 0; g <= 4; g++) {
      const y = pad + plotH - (plotH * g / 4);
      ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W - pad, y); ctx.stroke();
      ctx.fillText(String(Math.round(max * g / 4)), 10, y + 6);
    }
    const step = plotW / Math.max(1, labels.length - (kind === 'line' ? 1 : 0));
    labels.forEach((lb, i) => {
      if (labels.length <= 16 || i % Math.ceil(labels.length / 16) === 0) {
        ctx.fillText(String(lb).slice(-5), pad + i * step, H - pad + 28);
      }
    });
    datasets.forEach((ds, di) => {
      ctx.strokeStyle = ctx.fillStyle = palette[di % palette.length];
      if (kind === 'line') {
        ctx.lineWidth = 3; ctx.beginPath();
        ds.data.forEach((v, i) => {
          const x = pad + i * step, y = pad + plotH - (v / max) * plotH;
          i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();
      } else {
        const bw = Math.max(4, step / (datasets.length + 1));
        ds.data.forEach((v, i) => {
          const x = pad + i * step + di * bw, h = (v / max) * plotH;
          ctx.fillRect(x, pad + plotH - h, bw - 3, h);
        });
      }
    });
  }
  function drawDonut(canvas, labels, values) {
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.clientWidth * 2, H = canvas.height = canvas.clientHeight * 2;
    ctx.clearRect(0, 0, W, H);
    const total = values.reduce((a, b) => a + b, 0) || 1;
    const cx = W / 2, cy = H / 2, r = Math.min(W, H) / 2 - 20;
    let angle = -Math.PI / 2;
    values.forEach((v, i) => {
      const slice = (v / total) * Math.PI * 2;
      ctx.beginPath(); ctx.moveTo(cx, cy);
      ctx.fillStyle = palette[i % palette.length];
      ctx.arc(cx, cy, r, angle, angle + slice); ctx.fill();
      angle += slice;
    });
    ctx.beginPath(); ctx.fillStyle = '#fff'; ctx.arc(cx, cy, r * .6, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#334155'; ctx.font = 'bold 34px Inter, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(String(total), cx, cy + 10);
  }

  /* ---------- Global widgets ---------- */
  function startClock() {
    const el = document.getElementById('clock');
    if (!el) return;
    const tick = () => { el.textContent = new Date().toLocaleString(); };
    tick(); setInterval(tick, 1000);
  }

  async function pollNotifications() {
    const badge = document.getElementById('notif-count');
    if (!badge) return;
    const res = await get('/notifications/data');
    if (!res.success) return;
    const unread = res.data.unread;
    badge.textContent = unread > 0 ? unread : '';
    badge.style.display = unread > 0 ? 'inline-block' : 'none';
    const list = document.getElementById('notif-list');
    if (list) {
      list.innerHTML = res.data.rows.length
        ? res.data.rows.map((n) => `
            <div class="card" style="margin-bottom:8px;padding:10px 14px;${n.is_read == 0 ? 'border-left:3px solid var(--primary);' : ''}">
              <strong>${esc(n.title)}</strong> ${statusBadge(n.priority, n.priority)}<br>
              <span>${esc(n.message)}</span><br>
              <small class="text-dim">${esc(n.created_at)}</small>
            </div>`).join('')
        : '<p class="text-dim">No notifications.</p>';
    }
  }

  function initShell() {
    startClock();
    pollNotifications();
    setInterval(pollNotifications, 30000);
    document.querySelector('.menu-toggle')?.addEventListener('click',
      () => document.querySelector('.sidebar')?.classList.toggle('open'));
    const bell = document.getElementById('notif-bell');
    bell?.addEventListener('click', async (e) => {
      e.preventDefault();
      openModal('Notifications', '<div id="notif-list"><p class="text-dim">Loading…</p></div>',
        '<button class="btn" id="notif-read-all">Mark all as read</button>');
      await pollNotifications();
      document.getElementById('notif-read-all')?.addEventListener('click', async () => {
        await post('/notifications/read', {});
        closeModal(); pollNotifications();
      });
    });
  }
  document.addEventListener('DOMContentLoaded', initShell);

  return { get, post, esc, toast, openModal, closeModal, confirmDialog,
    renderTable, renderPagination, statusBadge, onlineBadge, submitForm,
    chartLine, chartBar, chartDonut, palette };
})();
