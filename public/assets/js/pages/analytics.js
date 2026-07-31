/* Analytics: grouped charts by day / grade / section / subject / teacher, device uptime. */
'use strict';

(() => {
  const charts = {};

  function grouped(rows) {
    // rows: [{label|day, code, total}] -> { labels, series: {code: [totals]} }
    const labelKey = rows.length && 'day' in rows[0] ? 'day' : 'label';
    const labels = [...new Set(rows.map((r) => r[labelKey]))];
    const codes = [...new Set(rows.map((r) => r.code))];
    const series = {};
    codes.forEach((c) => {
      series[c] = labels.map((l) =>
        Number(rows.find((r) => r[labelKey] === l && r.code === c)?.total || 0));
    });
    return { labels, series };
  }

  function draw(id, rows, kind = 'bar') {
    const { labels, series } = grouped(rows);
    const canvas = document.getElementById(id);
    if (charts[id]?.destroy) charts[id].destroy();
    const datasets = Object.entries(series).map(([label, data]) => ({ label, data }));
    charts[id] = kind === 'line'
      ? App.chartLine(canvas, labels, datasets)
      : App.chartBar(canvas, labels, datasets);
  }

  async function load() {
    const res = await App.get('/analytics/data', { days: document.getElementById('f-days').value });
    if (!res.success) { App.toast(res.message, 'error'); return; }
    const d = res.data;

    draw('c-daily', d.daily, 'line');
    draw('c-grade', d.by_grade);
    draw('c-section', d.by_section);
    draw('c-subject', d.by_subject);
    draw('c-teacher', d.by_teacher);

    const canvas = document.getElementById('c-auth');
    if (charts['c-auth']?.destroy) charts['c-auth'].destroy();
    charts['c-auth'] = App.chartDonut(canvas,
      d.auth_success.map((r) => r.result),
      d.auth_success.map((r) => Number(r.total)));

    App.renderTable('#device-table', [
      { key: 'label', label: 'Device' },
      { key: 'heartbeats', label: 'Heartbeats (period)' },
      { label: 'Avg Signal', render: (r) => r.avg_signal != null ? Number(r.avg_signal).toFixed(0) + ' dBm' : '—' },
    ], d.device_uptime, 'No device data.');
  }

  document.getElementById('f-days').addEventListener('change', load);
  load();
})();
