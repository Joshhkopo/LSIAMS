<?php $pageScript = 'dashboard-admin'; ?>
<div class="grid cols-6">
  <div class="stat accent-primary"><div class="stat-label">Students</div><div class="stat-value" id="st-students">—</div></div>
  <div class="stat accent-primary"><div class="stat-label">Teachers</div><div class="stat-value" id="st-teachers">—</div></div>
  <div class="stat accent-success"><div class="stat-label">Present Today</div><div class="stat-value" id="st-present">—</div></div>
  <div class="stat accent-warning"><div class="stat-label">Late Today</div><div class="stat-value" id="st-late">—</div></div>
  <div class="stat accent-danger"><div class="stat-label">Absent Today</div><div class="stat-value" id="st-absent">—</div></div>
  <div class="stat accent-primary"><div class="stat-label">Attendance %</div><div class="stat-value" id="st-pct">—</div></div>
</div>
<div class="grid cols-4">
  <div class="stat"><div class="stat-label">Active Sessions</div><div class="stat-value" id="st-sessions">—</div></div>
  <div class="stat"><div class="stat-label">Devices Online</div><div class="stat-value" id="st-online">—</div></div>
  <div class="stat"><div class="stat-label">Devices Offline</div><div class="stat-value" id="st-offline">—</div></div>
  <div class="stat"><div class="stat-label">Security Events (24h)</div><div class="stat-value" id="st-security">—</div></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h3>Attendance Trend (14 days)</h3>
    <div class="chart-box"><canvas id="chart-trend"></canvas></div>
  </div>
  <div class="card">
    <h3>Present vs Late vs Absent (today)</h3>
    <div class="chart-box"><canvas id="chart-today"></canvas></div>
  </div>
</div>

<div class="card">
  <h3>Live Attendance Feed <span class="badge green">auto-refresh</span></h3>
  <div id="live-feed"></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h3>Device Status</h3>
    <div id="device-table"></div>
  </div>
  <div class="card">
    <h3>Security Summary (7 days)</h3>
    <div id="security-table"></div>
  </div>
</div>
