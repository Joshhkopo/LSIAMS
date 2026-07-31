# L-SIAMS

**Local IoT-Based Multi-Layer Secured Attendance Monitoring System with RFID and Biometric Authentication**

L-SIAMS is an enterprise-grade, LAN-only attendance monitoring platform for educational
institutions. Each classroom hosts an ESP32 attendance terminal (MFRC522 RFID reader +
R307 fingerprint sensor). A teacher must authenticate with a fingerprint before an
attendance session opens; students then tap RFID cards which are validated in real time
against schedules, sections, sessions, and duplicate rules by the local server.

## Architecture

| Layer        | Technology                                                    |
|--------------|---------------------------------------------------------------|
| IoT Device   | ESP32 DevKit V1, MFRC522, R307, OLED (SSD1306), buzzer, LEDs  |
| Network      | LAN only, HTTPS/TLS, REST + JSON, API keys, nonce/timestamp   |
| Application  | PHP 8+ (custom MVC, PDO prepared statements)                  |
| Database     | MySQL 8+ (InnoDB, 3NF, FK constraints, transactions)          |
| Frontend     | Bootstrap 5, Chart.js, vanilla JS (fetch/AJAX)                |
| Security     | RBAC, bcrypt, CSRF, rate limiting, audit + security logging   |

## Repository layout

```
app/
  Controllers/        Web controllers (admin + teacher modules)
  Controllers/Api/    Device + dashboard REST API controllers
  Core/               Micro-framework (router, PDO, session, request/response)
  Helpers/            Validation, CSRF, audit, logging, rate limiting
  Middleware/         auth / role / csrf / device authentication
  Models/             One model per table (prepared statements only)
  Services/           Attendance engine, schedule conflicts, devices, reports
  Views/              Server-rendered pages (layouts, modules, errors)
api/                  API entry (rewritten to public/index.php)
config/               config.php, database.php, security.php (.env driven)
database/migrations/  Full MySQL schema (001_create_schema.sql)
database/seeders/     Seed data (roles, admin, samples)
firmware/             ESP32 firmware (modular: wifi, rfid, fingerprint, queue…)
public/               Web root (index.php front controller, assets, uploads)
routes/               web.php and api.php route tables
storage/              logs/, reports/, cache/
docs/                 API, security, deployment documentation
```

## Quick start

1. **Requirements**: PHP 8.1+, MySQL 8+, Apache or Nginx with HTTPS enabled,
   static server IP (e.g. `192.168.1.10`).
2. **Configure**: `cp .env.example .env` and edit DB credentials, `APP_URL`,
   and security settings.
3. **Database**:
   ```bash
   mysql -u root -p < database/migrations/001_create_schema.sql
   mysql -u root -p lsiams_db < database/seeders/002_seed_data.sql
   ```
4. **Web root**: point the virtual host `DocumentRoot` at `public/`.
5. **Frontend vendor assets** (LAN deployment — no CDN at runtime). On a machine
   with internet access run:
   ```bash
   bash scripts/fetch-vendor-assets.sh
   ```
   which places Bootstrap 5 and Chart.js under `public/assets/vendor/`.
6. **Login**: default administrator is `admin` / `ChangeMe!12345` (seeded).
   You are forced to change it — do so immediately.
7. **Devices**: register each ESP32 in *IoT Devices → Register Device*. Copy the
   generated API key (shown once) into `firmware/lsiams_terminal/config.h`,
   flash, and mount the terminal in its classroom.

## Security model (defense in depth)

- HTTPS/TLS 1.2+ for every request; HTTP only as a redirect.
- Device requests require `device_id`, API key (stored hashed server-side),
  fresh timestamp (±30 s window) and single-use nonce (replay protection).
- Web sessions: regenerated IDs, HttpOnly/SameSite/secure cookies, inactivity
  timeout, account lockout after repeated failures, login history.
- Strict RBAC (Administrator / Teacher); every route and API checks permission.
- All queries are prepared statements; all output escaped; CSRF token on all
  state-changing requests; upload type/size validation with randomized names.
- Immutable audit logs for every administrative action; separate security log
  for failed logins, unknown RFID/devices, replay attempts, rate-limit hits.
- Soft deletes everywhere; attendance, audit and security logs are never
  physically deleted.

See `docs/SECURITY.md`, `docs/API.md`, and `docs/DEPLOYMENT.md` for details.
