<?php $pageScript = 'dashboard-teacher'; ?>
<div class="grid cols-6">
  <div class="stat accent-primary"><div class="stat-label">Today's Classes</div><div class="stat-value" id="st-classes">—</div></div>
  <div class="stat accent-success"><div class="stat-label">Present Today</div><div class="stat-value" id="st-present">—</div></div>
  <div class="stat accent-warning"><div class="stat-label">Late Today</div><div class="stat-value" id="st-late">—</div></div>
  <div class="stat accent-danger"><div class="stat-label">Absent Today</div><div class="stat-value" id="st-absent">—</div></div>
  <div class="stat accent-primary"><div class="stat-label">Attendance %</div><div class="stat-value" id="st-pct">—</div></div>
  <div class="stat"><div class="stat-label">Sessions This Week</div><div class="stat-value" id="st-week">—</div></div>
</div>

<div class="card">
  <h3>Today's Schedule</h3>
  <p class="text-dim" style="font-size:12.5px;">
    Attendance sessions open at the classroom terminal after your fingerprint is verified.
    You can close an open session from here.
  </p>
  <div id="today-table"></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h3>My Attendance Trend (14 days)</h3>
    <div class="chart-box"><canvas id="chart-trend"></canvas></div>
  </div>
  <div class="card">
    <h3>Recent Attendance <span class="badge green">live</span></h3>
    <div id="live-feed"></div>
  </div>
</div>
