# L-SIAMS Device REST API

Base URL: `https://<server-ip>/api` — HTTPS only, JSON only, POST only for
device endpoints. All responses use the envelope:

```json
{ "success": true, "message": "…", "data": { } }
```

## Authentication envelope (every device request)

Every request body MUST contain:

| Field       | Type   | Description                                        |
|-------------|--------|----------------------------------------------------|
| `device_id` | string | Registered device code, e.g. `DEV-001`             |
| `api_key`   | string | Plaintext key; server stores only its SHA-256 hash |
| `timestamp` | string | ISO-8601 (`2026-06-01T08:15:32`), ±30 s window     |
| `nonce`     | string | Random single-use value (replay protection)        |

Failure of any check returns **401** `{"success":false,"message":"Device authentication failed."}`
and generates a security log. Rate limit: 300 requests/min/IP → **429**.

---

## POST /api/device/authenticate

Boot handshake. Returns server time for clock sync.

**Response 200**
```json
{ "success": true, "message": "Device authenticated.",
  "data": { "device_code": "DEV-001", "classroom_id": 1,
            "server_time": "2026-06-01T08:00:00+08:00", "heartbeat_interval": 30 } }
```

## POST /api/device/heartbeat

Body extras: `firmware`, `wifi_signal` (RSSI dBm), `queue` (int), `uptime` (s).

**200** → `data.server_time`. Devices silent for 90 s are marked Offline and
administrators are notified.

## POST /api/device/log

Body extras: `event` (`boot|shutdown|restart|sync|auth|error|config`), `details`.

## POST /api/fingerprint/verify

The only way to open an attendance session.

Body extras: `fingerprint_id` — the R307 template slot matched locally.

Server checks, in order: enrollment exists for (device, slot) → teacher
active → an active schedule exists for this classroom right now → the
schedule belongs to this teacher → no session already open.

**200**
```json
{ "success": true, "message": "Fingerprint verified. Attendance session opened.",
  "data": { "session_id": 12, "session_code": "ATT-20260601-A1B2C3",
            "teacher": "Juan Dela Cruz", "subject": "Mathematics 7",
            "section": "Rizal", "ends_at": "09:00:00" } }
```
**403** — with the specific denial reason. Rate limit 5/5 min per device.

## POST /api/attendance/record

Live student tap. Body extras: `session_id` (session code), `rfid_uid`.

Validation chain: session open → session belongs to device → card known →
card active → student active → student in section → not duplicate →
within cutoff (late threshold applied).

**200** `data`: `student`, `status` (`Present|Late`), `time`.
**422** messages: `Unknown RFID.` / `Card disabled.` / `Attendance already
recorded.` / `Attendance session closed.` / `Student is not enrolled in this
class.` / `Attendance window has closed.`

## POST /api/attendance/end

Body extras: `session_id`. Closes the session (owning device only) and
auto-generates Absent records for students who never tapped.

## POST /api/attendance/session

Returns the open session for the device's classroom (`data.session` or
`null`) — used to resume after reboot.

## POST /api/attendance/sync

Offline queue upload (max 500 records/call).

```json
{ "records": [ { "session_id": "ATT-…", "rfid_uid": "04A7C1935D",
                 "timestamp": "2026-06-01T08:10:05" } ] }
```

Original timestamps are preserved; duplicates return `success: true` in the
per-record result so the device can safely drop them. **Response**
`data.results[] = {index, success, message}` in the original order.

## POST /api/rfid/scan

Diagnostic/registration lookup outside a session. Unknown UIDs are recorded
in the administrator's *Unknown Cards* queue.

---

## Web JSON endpoints (session + CSRF authenticated)

The web UI consumes `/dashboard/data`, `/attendance/data`, `/attendance/live`,
`/students/data`, … — these require a logged-in session cookie plus the
`X-CSRF-Token` header on writes, and enforce RBAC (403 + security log on
violation). See `routes/web.php` for the full table.
