<?php $pageScript = 'analytics'; ?>
<div class="toolbar">
  <label style="margin:0;">Period:</label>
  <select id="f-days">
    <option value="7">Last 7 days</option>
    <option value="30" selected>Last 30 days</option>
    <option value="90">Last 90 days</option>
    <option value="365">Last 12 months</option>
  </select>
</div>

<div class="grid cols-2">
  <div class="card"><h3>Daily Attendance</h3><div class="chart-box"><canvas id="c-daily"></canvas></div></div>
  <div class="card"><h3>Fingerprint Authentication Success</h3><div class="chart-box"><canvas id="c-auth"></canvas></div></div>
  <div class="card"><h3>Attendance by Grade Level</h3><div class="chart-box"><canvas id="c-grade"></canvas></div></div>
  <div class="card"><h3>Attendance by Section</h3><div class="chart-box"><canvas id="c-section"></canvas></div></div>
  <div class="card"><h3>Attendance by Subject</h3><div class="chart-box"><canvas id="c-subject"></canvas></div></div>
  <div class="card"><h3>Attendance by Teacher</h3><div class="chart-box"><canvas id="c-teacher"></canvas></div></div>
</div>

<div class="card">
  <h3>Device Health</h3>
  <div id="device-table"></div>
</div>
