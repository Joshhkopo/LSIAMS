# L-SIAMS Security Architecture

Defense-in-depth across six layers. Each layer functions independently.

## 1. Physical
- ESP32 in a locked enclosure, USB port inaccessible after deployment.
- Server room locked; DB accessible from localhost only.

## 2. Device
- Only registered devices communicate: device code + MAC recorded at
  registration; unknown devices rejected and security-logged.
- API keys: 256-bit random, stored **hashed (SHA-256)** server-side, shown
  once at creation, revocable/rotatable from Device Management. Disabling a
  device revokes its key.
- Firmware pins the server TLS certificate (`SERVER_CA_CERT`).
- 5 fingerprint failures in 5 min → sensor locks 5 min + admin alert.

## 3. Network
- LAN only; no cloud. Static server IP; HTTPS (443) is the only service
  port required. Blocked-IP list enforced at the API layer
  (`blocked_ips` table) in addition to the firewall.
- Recommended firewall: allow 443 (+80 redirect only); deny FTP/Telnet;
  MySQL bound to 127.0.0.1. Optional IoT VLAN.

## 4. Transport
- TLS 1.2+ everywhere; HTTP 301-redirects to HTTPS (`public/.htaccess`).
- Replay protection: ISO timestamp within ±30 s (configurable) **and**
  single-use nonce per device (unique index `api_nonces(device_id, nonce)`).
  Violations logged as `replay_attempt` (high/critical).

## 5. Application
- **Sessions**: HttpOnly + SameSite + Secure cookies, ID regeneration on
  login, 30-min inactivity timeout, full destruction on logout.
- **Login**: bcrypt (cost 12), lockout after 5 failures/15 min (auto-unlock
  after the window), per-IP rate limiting, login history, admin alert on
  lockout.
- **Passwords**: min 12 chars with upper/lower/digit; forced change for
  seeded/reset accounts; never stored or transmitted in plaintext.
- **RBAC**: two roles (Administrator, Teacher); every route declares its
  middleware in `routes/web.php`; violations → 403 + `permission_violation`
  security log. Teachers are also row-scoped to their own classes.
- **CSRF**: session-bound token required on every state-changing request
  (form field or `X-CSRF-Token`); failures → 419 + security log.
- **Input validation**: centralized `App\Helpers\Validator`, server-side on
  every write path.
- **SQL injection**: PDO with native prepared statements only
  (`ATTR_EMULATE_PREPARES = false`); no string-concatenated SQL. Column
  names are whitelisted by regex in the base model.
- **XSS**: all template output through `htmlspecialchars`; all JS rendering
  through `App.esc`; CSP (`script-src 'self'`, no inline scripts) set in
  `.htaccess`.
- **Uploads**: extension + MIME (finfo) + size checks, randomized names,
  script execution denied under `/uploads/`.
- **Rate limits** (DB-backed sliding window): login 5/15 min, fingerprint
  verify 5/5 min, RFID scan 120/min, API 300/min.

## 6. Database
- Dedicated least-privilege MySQL user; InnoDB, FK constraints,
  transactions on every critical write (attendance is never partially
  saved — see `Database::transaction`).
- Soft deletes for students/teachers/users; attendance, audit logs,
  security logs and login history are **never** physically deleted.
- Attendance corrections: administrator-only, reason mandatory, original
  preserved in `attendance_modifications`, audit-logged.
- Backups: mysqldump + gzip + optional AES-256 (openssl); restore requires
  password re-authentication and is audit-logged.

## Monitoring & audit
- `audit_logs`: every admin/auth action (user, action, module, old/new
  values, IP, user agent). No update/delete path exists in the app.
- `security_logs`: failed logins, lockouts, unknown RFID/devices, replay
  attempts, CSRF failures, permission violations, rate limits, offline
  devices — with severity and a resolution workflow in the Security Center.
- In-app notifications to all administrators for critical events.

## Penetration-test expectations
- **Wireshark**: only TLS traffic; no plaintext credentials, attendance
  data, or API keys on the wire.
- **Nmap**: 443 (and optionally 80-redirect) only; MySQL not exposed.
- **Metasploit/manual**: unauthorized logins blocked + logged; role bypass
  returns 403; forged/replayed device requests rejected 100%; SQLi/XSS/CSRF
  attempts fail and are logged.

## Future extensions (architecture-ready)
2FA (users.must_change_password pattern extends naturally), HMAC request
signatures (field already tolerated by the middleware), mTLS, VLAN
segmentation, SIEM export of `security_logs`, IDS/IPS.
