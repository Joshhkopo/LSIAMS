# L-SIAMS Deployment Guide (LAN)

## Topology

```
                    Router / Managed Switch
        ┌──────────────┬──────────────┬─────────────────┐
   Admin PC       Teacher PCs     Server (192.168.1.10)  ESP32 terminals
                                  Apache/Nginx + PHP 8   (one per classroom)
                                  MySQL 8 (localhost)
```

No internet required at runtime. Reserve static/DHCP-reserved IPs for the
server and terminals.

## Server setup

1. Install Apache (or Nginx), PHP 8.1+ (`pdo_mysql`, `fileinfo`, `openssl`
   extensions) and MySQL 8.
2. Clone the repository to e.g. `/var/www/lsiams` and point the vhost
   `DocumentRoot` to `public/`. Enable `mod_rewrite` and `mod_headers`
   (`AllowOverride All`).
3. Create the DB and least-privilege user:
   ```sql
   CREATE USER 'lsiams_app'@'localhost' IDENTIFIED BY '<strong password>';
   GRANT SELECT, INSERT, UPDATE, DELETE ON lsiams_db.* TO 'lsiams_app'@'localhost';
   ```
4. Load schema + seeds:
   ```bash
   mysql -u root -p < database/migrations/001_create_schema.sql
   mysql -u root -p lsiams_db < database/seeders/002_seed_data.sql
   ```
5. `cp .env.example .env` and fill in DB credentials, `APP_URL`, timezone,
   and a random `BACKUP_ENCRYPTION_KEY`.
6. Permissions: web user needs write access to `storage/`,
   `public/uploads/`, `database/backups/`.
7. HTTPS: generate a certificate (self-signed acceptable on a LAN):
   ```bash
   openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
     -keyout /etc/ssl/private/lsiams.key -out /etc/ssl/certs/lsiams.crt \
     -subj "/CN=192.168.1.10" -addext "subjectAltName=IP:192.168.1.10"
   ```
   Configure the vhost for TLS and paste `lsiams.crt` into the firmware's
   `SERVER_CA_CERT` for certificate pinning.
8. Frontend vendor assets (once, on a machine with internet):
   ```bash
   bash scripts/fetch-vendor-assets.sh
   ```
9. Scheduled tasks (optional but recommended — the app also runs these
   opportunistically on heartbeat traffic):
   ```cron
   */1 * * * * php /var/www/lsiams/scripts/cron.php
   0 2 * * *  php /var/www/lsiams/scripts/cron.php backup
   ```

## First login

`admin / ChangeMe!12345` → the system forces a password change. Then:

1. Settings → set the school name and attendance rules.
2. Classrooms → create rooms. Subjects, Sections (seeded samples exist).
3. Teachers → register accounts (temporary passwords force a change).
4. IoT Devices → register each ESP32; copy each API key into its firmware.
5. Fingerprints → enroll teachers at their classroom terminals and record
   the device/slot mapping.
6. Students → add or CSV-import; assign RFID cards (tap unknown cards on a
   terminal, then assign from RFID → Unknown Cards).
7. Schedules → create class schedules (conflicts are rejected).

## Firewall checklist

- Allow: 443/tcp (80/tcp only for the HTTPS redirect).
- Deny everything else inbound to the server; MySQL bound to 127.0.0.1.
- WPA2/WPA3 WiFi with a strong passphrase for the terminal SSID.

## Local development (no Apache/hardware needed)

```bash
cp .env.example .env
# For plain-HTTP local dev set:  SESSION_SECURE_COOKIE=false  APP_URL=http://localhost:8000
mysql -u root -p < database/migrations/001_create_schema.sql
mysql -u root -p lsiams_db < database/seeders/002_seed_data.sql
php -S 0.0.0.0:8000 -t public scripts/dev-router.php
```

Log in at http://localhost:8000 as `admin / ChangeMe!12345`.

**Simulating a classroom terminal** (the seeded device `DEV-001` with API key
`SAMPLE-DEV-KEY-0000000000000000` is registered to RM-101):

```bash
# The seeded schedule is Mon–Fri 08:00–09:00. To test at any time, insert a
# schedule that covers "right now":
mysql -u root -p lsiams_db -e "
INSERT INTO schedules (teacher_id, subject_id, section_id, classroom_id,
  day_of_week, start_time, end_time, attendance_window_minutes, late_threshold_minutes, status)
VALUES (1, 1, 1, 1, WEEKDAY(NOW())+1, DATE_FORMAT(NOW(), '%H:%i:00'),
  DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 HOUR), '%H:%i:00'), 20, 20, 'active');"

php scripts/simulate-device.php demo          # full flow: auth → fingerprint →
                                              # taps → duplicate → unknown → sync → close
# or step by step:
php scripts/simulate-device.php auth
php scripts/simulate-device.php open 1        # teacher fingerprint slot 1
php scripts/simulate-device.php tap 04A7C1935D
php scripts/simulate-device.php end
```

While the demo runs, keep the admin dashboard open — the live feed, counters,
device status, unknown-RFID queue and notifications update in real time.

## Verification

- Wireshark on the LAN: confirm only TLS to the server, no plaintext.
- `nmap <server-ip>`: only 443 (and 80 redirect) open.
- Tap an unregistered card: rejected, appears under RFID → Unknown Cards,
  security log created, admins notified.
- Unplug a terminal: dashboard shows Offline within ~90 s + notification.
- Disconnect WiFi mid-session, tap cards, reconnect: taps sync with their
  original timestamps and no duplicates.
