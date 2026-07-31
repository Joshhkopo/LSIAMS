/* Administrator dashboard: summary cards, charts, live feed, device health. */
'use strict';

(() => {
  let trendChart = null, todayChart = null, lastLiveId = 0;

  async function refreshDashboard() {
    const res = await App.get('/dashboard/data');
    if (!res.success) return;
    const s = res.data.summary;

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('st-students', s.total_students);
    set('st-teachers', s.total_teachers);
    set('st-present', s.present_today);
    set('st-late', s.late_today);
    set('st-absent', s.absent_today);
    set('st-pct', s.attendance_pct + '%');
    set('st-sessions', s.active_sessions);
    set('st-online', s.devices_online);
    set('st-offline', s.devices_offline);
    set('st-security', s.security_events_24h);

    drawTrend(s.trend);
    drawToday(s);
    renderDevices(res.data.devices || []);
    renderSecurity(s.security_summary || []);
  }

  function drawTrend(trend) {
    const days = [...new Set(trend.map((r) => r.day))];
    const series = (code) => days.map((d) =>
      Number(trend.find((r) => r.day === d && r.code === code)?.total || 0));
    const canvas = document.getElementById('chart-trend');
    if (trendChart?.destroy) trendChart.destroy();
    trendChart = App.chartLine(canvas, days, [
      { label: 'Present', data: series('present') },
      { label: 'Late', data: series('late') },
      { label: 'Absent', data: series('absent') },
    ]);
  }

  function drawToday(s) {
    const canvas = document.getElementById('chart-today');
    if (todayChart?.destroy) todayChart.destroy();
    todayChart = App.chartDonut(canvas, ['Present', 'Late', 'Absent'],
      [Number(s.present_today), Number(s.late_today), Number(s.absent_today)]);
  }

  function renderDevices(devices) {
    App.renderTable('#device-table', [
      { key: 'device_code', label: 'Device' },
      { key: 'room_number', label: 'Room' },
      { key: 'ip_address', label: 'IP' },
      { key: 'firmware_version', label: 'Firmware' },
      { label: 'Signal', render: (d) => d.signal_strength != null ? `${d.signal_strength} dBm` : '—' },
      { label: 'Queue', render: (d) => d.queue_count ?? '0' },
      { label: 'Heartbeat', render: (d) => App.esc(d.last_heartbeat || 'never') },
      { label: 'Status', render: (d) => App.onlineBadge(Number(d.is_online) === 1) },
    ], devices, 'No devices registered.');
  }

  function renderSecurity(rows) {
    App.renderTable('#security-table', [
      { key: 'event', label: 'Event' },
      { label: 'Severity', render: (r) => App.statusBadge(r.severity, r.severity) },
      { key: 'total', label: 'Count' },
    ], rows, 'No security events in the last 7 days.');
  }

  async function refreshLive() {
    const res = await App.get('/attendance/live', { since_id: lastLiveId });
    if (!res.success) return;
    if (res.data.rows.length) lastLiveId = Math.max(...res.data.rows.map((r) => Number(r.id)), lastLiveId);
    App.renderTable('#live-feed', [
      { key: 'student_name', label: 'Student' },
      { key: 'student_number', label: 'Number' },
      { key: 'section_name', label: 'Section' },
      { key: 'room_number', label: 'Room' },
      { key: 'teacher_name', label: 'Teacher' },
      { label: 'Time', render: (r) => App.esc(String(r.recorded_at).slice(11, 19)) },
      { label: 'Status', render: (r) => App.statusBadge(r.status_code, r.status_name) },
      { label: 'RFID', render: (r) => `<span class="mono">${App.esc(r.rfid_uid || '—')}</span>` },
    ], res.data.rows, 'No attendance recorded today yet.');
  }

  refreshDashboard();
  refreshLive();
  setInterval(refreshDashboard, 15000);
  setInterval(refreshLive, 5000);
})();
