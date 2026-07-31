/* Teacher dashboard: today's classes, live feed, personal statistics. */
'use strict';

(() => {
  let trendChart = null, lastLiveId = 0;
  const DAY_LABELS = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };

  async function refresh() {
    const res = await App.get('/dashboard/data');
    if (!res.success) return;
    const s = res.data.summary;

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('st-classes', s.today_classes.length);
    set('st-present', s.present_today);
    set('st-late', s.late_today);
    set('st-absent', s.absent_today);
    set('st-pct', s.attendance_pct + '%');
    set('st-week', s.week_sessions);

    App.renderTable('#today-table', [
      { label: 'Time', render: (c) => `${String(c.start_time).slice(0, 5)}–${String(c.end_time).slice(0, 5)}` },
      { key: 'subject_name', label: 'Subject' },
      { key: 'section_name', label: 'Section' },
      { key: 'room_number', label: 'Room' },
      { label: 'Session', render: (c) => c.open_session_id
          ? '<span class="badge green">Open</span>'
          : '<span class="badge gray">Not started</span>' },
      { label: 'Action', render: (c) => c.open_session_id
          ? `<button class="btn sm danger" data-close="${c.open_session_id}">Close attendance</button>`
          : '<span class="text-dim">Verify fingerprint at the terminal</span>' },
    ], s.today_classes, 'No classes scheduled today.');

    document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', async () => {
      if (!await App.confirmDialog('Close this attendance session? Absent records will be generated for students who did not tap.')) return;
      const res2 = await App.post(`/attendance/sessions/${b.dataset.close}/close`, {});
      App.toast(res2.message, res2.success ? 'success' : 'error');
      refresh();
    }));

    drawTrend(s.trend);
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

  async function refreshLive() {
    const res = await App.get('/attendance/live', { since_id: lastLiveId });
    if (!res.success) return;
    if (res.data.rows.length) lastLiveId = Math.max(...res.data.rows.map((r) => Number(r.id)), lastLiveId);
    App.renderTable('#live-feed', [
      { key: 'student_name', label: 'Student' },
      { label: 'Time', render: (r) => App.esc(String(r.recorded_at).slice(11, 19)) },
      { label: 'Status', render: (r) => App.statusBadge(r.status_code, r.status_name) },
      { label: 'RFID', render: (r) => `<span class="mono">${App.esc(r.rfid_uid || '—')}</span>` },
    ], res.data.rows, 'No attendance recorded today yet.');
  }

  refresh();
  refreshLive();
  setInterval(refresh, 20000);
  setInterval(refreshLive, 5000);
})();
