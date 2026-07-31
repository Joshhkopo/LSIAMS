// =====================================================================
// L-SIAMS classroom attendance terminal — ESP32 firmware.
//
// State machine:
//   BOOT -> AUTHENTICATING -> WAITING_FINGERPRINT -> ATTENDANCE_OPEN
//        -> (session close / schedule end) -> WAITING_FINGERPRINT
//
// The teacher's fingerprint (verified server-side by device+slot) is the
// ONLY way a session opens. Student RFID taps are validated by the server;
// when the LAN is down, taps are queued in flash and synchronized later
// with their original timestamps.
//
// Libraries: MFRC522, Adafruit Fingerprint, Adafruit SSD1306, ArduinoJson.
// =====================================================================

#include <Arduino.h>
#include "config.h"
#include "wifi_manager.h"
#include "api_client.h"
#include "rfid_manager.h"
#include "fingerprint_manager.h"
#include "display_manager.h"
#include "feedback_manager.h"
#include "queue_manager.h"

enum class State { BOOT, AUTHENTICATING, WAITING_FINGERPRINT, ATTENDANCE_OPEN };

WifiManager        wifi;
ApiClient          api;
RfidManager        rfid;
FingerprintManager fingerprint;
DisplayManager     oled;
FeedbackManager    feedback;
QueueManager       queue;

State  state = State::BOOT;
String sessionCode;                 // open attendance session id (ATT-...)
unsigned long lastHeartbeat = 0;
unsigned long lastTimeSync  = 0;
unsigned long lastSyncTry   = 0;
unsigned long bootMillis    = 0;

// ---------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  bootMillis = millis();

  feedback.begin();          // Step 1: GPIO
  oled.begin();              // Step 2: OLED
  rfid.begin();              // Step 3: RFID
  fingerprint.begin();       // Step 4: fingerprint
  queue.begin();             // Step 5: restore offline queue
  wifi.begin();              // Step 6: WiFi
  state = State::AUTHENTICATING;
}

// ---------------------------------------------------------------------
void loop() {
  wifi.maintain();
  feedback.wifi(wifi.isConnected());
  handleSerialCommands();

  switch (state) {
    case State::AUTHENTICATING:    doAuthenticate(); break;
    case State::WAITING_FINGERPRINT: doFingerprintPhase(); break;
    case State::ATTENDANCE_OPEN:   doAttendancePhase(); break;
    default: break;
  }

  doHeartbeat();
  doQueueSync();
  delay(30);
}

// ---------------------------------------------------------------------
// Boot handshake: authenticate, sync clock, resume any open session.
void doAuthenticate() {
  if (!wifi.isConnected()) {
    oled.show("OFFLINE", "Waiting for WiFi...");
    delay(500);
    return;
  }

  oled.show("AUTH", "Contacting server...");
  JsonDocument body;
  ApiResponse res = api.post("/api/device/authenticate", body);
  if (!res.ok || !res.success) {
    oled.show("AUTH FAIL", res.message);
    feedback.invalid();
    delay(3000);
    return; // retried next loop
  }

  // Server time sync (attendance timestamps use server time).
  String serverTime = String((const char*) (res.doc["data"]["server_time"] | ""));
  applyServerTime(serverTime);
  lastTimeSync = millis();

  // Resume an in-progress session after an unexpected reboot.
  JsonDocument body2;
  ApiResponse res2 = api.post("/api/attendance/session", body2);
  if (res2.success && !res2.doc["data"]["session"].isNull()) {
    sessionCode = String((const char*) (res2.doc["data"]["session"]["session_id"] | ""));
    if (!sessionCode.isEmpty()) {
      oled.show("RESUMED", "Session " + sessionCode);
      state = State::ATTENDANCE_OPEN;
      logEvent("boot", "Resumed session " + sessionCode);
      return;
    }
  }

  logEvent("boot", "Device ready");
  oled.show("READY", "Scan fingerprint to open attendance");
  state = State::WAITING_FINGERPRINT;
}

// ---------------------------------------------------------------------
// Phase 1: only the assigned teacher's fingerprint opens attendance.
void doFingerprintPhase() {
  if (fingerprint.isLocked()) {
    oled.show("LOCKED", "Too many failed scans");
    delay(500);
    return;
  }

  int slot = fingerprint.scan();
  if (slot == 0) return; // no finger
  if (slot < 0) {
    feedback.fingerprintFail();
    oled.show("RETRY", "Fingerprint not recognized");
    return;
  }

  oled.show("VERIFYING", "Checking with server...");
  JsonDocument body;
  body["fingerprint_id"] = slot;
  ApiResponse res = api.post("/api/fingerprint/verify", body);

  if (res.ok && res.success) {
    sessionCode = String((const char*) (res.doc["data"]["session_code"] | ""));
    String teacher = String((const char*) (res.doc["data"]["teacher"] | ""));
    feedback.success();
    oled.show("OPEN", teacher + " — tap RFID cards");
    state = State::ATTENDANCE_OPEN;
  } else {
    feedback.fingerprintFail();
    oled.show("DENIED", res.ok ? res.message : "Server unreachable");
    delay(1500);
    oled.show("READY", "Scan fingerprint to open attendance");
  }
}

// ---------------------------------------------------------------------
// Phase 2: student RFID taps (online -> server, offline -> queue).
void doAttendancePhase() {
  // The teacher can close the session with a verified fingerprint.
  int slot = fingerprint.scan();
  if (slot > 0) {
    closeSession();
    return;
  }

  String uid = rfid.readUid();
  if (uid.isEmpty()) return;

  if (!wifi.isConnected()) {
    // Offline mode: preserve original tap time, queue for sync.
    if (queue.push(sessionCode, uid, api.isoTimestamp())) {
      feedback.success();
      oled.showAttendance(uid, "QUEUED", "offline");
    } else {
      feedback.invalid();
      oled.show("QUEUE FULL", "Cannot store more taps");
    }
    return;
  }

  JsonDocument body;
  body["session_id"] = sessionCode;
  body["rfid_uid"]   = uid;
  ApiResponse res = api.post("/api/attendance/record", body);

  if (!res.ok) {
    // Server unreachable mid-session: fall back to the offline queue.
    queue.push(sessionCode, uid, api.isoTimestamp());
    feedback.success();
    oled.showAttendance(uid, "QUEUED", "server timeout");
    return;
  }

  if (res.success) {
    String student = String((const char*) (res.doc["data"]["student"] | ""));
    String status  = String((const char*) (res.doc["data"]["status"] | "Present"));
    String time    = String((const char*) (res.doc["data"]["time"] | ""));
    feedback.success();
    oled.showAttendance(student, status, time);
  } else if (res.message.indexOf("already recorded") >= 0) {
    feedback.duplicate();
    oled.show("DUPLICATE", "Already recorded");
  } else if (res.message.indexOf("closed") >= 0) {
    feedback.invalid();
    oled.show("CLOSED", "Attendance session closed");
    sessionCode = "";
    state = State::WAITING_FINGERPRINT;
  } else {
    feedback.invalid();
    oled.show("REJECTED", res.message);
  }
}

// ---------------------------------------------------------------------
void closeSession() {
  oled.show("CLOSING", "Ending attendance...");
  JsonDocument body;
  body["session_id"] = sessionCode;
  ApiResponse res = api.post("/api/attendance/end", body);
  if (res.ok && res.success) {
    feedback.success();
    oled.show("CLOSED", "Absents generated");
  } else {
    feedback.invalid();
    oled.show("NOTE", "Session will auto-close on schedule end");
  }
  sessionCode = "";
  delay(1500);
  oled.show("READY", "Scan fingerprint to open attendance");
  state = State::WAITING_FINGERPRINT;
}

// ---------------------------------------------------------------------
// Heartbeat every 30 s: firmware, RSSI, queue depth, uptime.
void doHeartbeat() {
  if (!wifi.isConnected() || millis() - lastHeartbeat < HEARTBEAT_INTERVAL_MS) return;
  lastHeartbeat = millis();

  JsonDocument body;
  body["firmware"]    = FIRMWARE_VERSION;
  body["wifi_signal"] = wifi.rssi();
  body["queue"]       = (int) queue.size();
  body["uptime"]      = (unsigned long) ((millis() - bootMillis) / 1000);
  ApiResponse res = api.post("/api/device/heartbeat", body);

  // Periodic clock re-sync piggybacks on the heartbeat.
  if (res.success && millis() - lastTimeSync > TIME_SYNC_INTERVAL_MS) {
    applyServerTime(String((const char*) (res.doc["data"]["server_time"] | "")));
    lastTimeSync = millis();
  }
}

// ---------------------------------------------------------------------
// Upload the offline queue oldest-first; duplicates are dropped safely.
void doQueueSync() {
  if (!wifi.isConnected() || queue.size() == 0) return;
  if (millis() - lastSyncTry < SYNC_RETRY_INTERVAL_MS) return;
  lastSyncTry = millis();

  auto batch = queue.peek(25);
  JsonDocument body;
  JsonArray records = body["records"].to<JsonArray>();
  for (const auto& t : batch) {
    JsonObject r = records.add<JsonObject>();
    r["session_id"] = t.sessionId;
    r["rfid_uid"]   = t.uid;
    r["timestamp"]  = t.timestamp;   // original tap time is preserved
  }

  ApiResponse res = api.post("/api/attendance/sync", body);
  if (!res.ok || !res.success) return;

  // Drop every record the server marked synced (accepted OR duplicate).
  size_t confirmed = 0;
  for (JsonObject r : res.doc["data"]["results"].as<JsonArray>()) {
    if (r["success"] | false) confirmed++;
    else break; // keep order: stop at the first hard failure
  }
  if (confirmed > 0) {
    queue.drop(confirmed);
    oled.show("SYNCED", String(confirmed) + " record(s) uploaded");
  }
}

// ---------------------------------------------------------------------
// Maintenance commands over the Serial Monitor (115200 baud):
//   enroll <slot>   capture a fingerprint into R307 slot <slot>
//   status          print device state
// After enrolling, record the teacher/device/slot mapping in the web app
// (Fingerprints -> Enroll Fingerprint).
void handleSerialCommands() {
  if (!Serial.available()) return;
  String line = Serial.readStringUntil('\n');
  line.trim();
  if (line.startsWith("enroll ")) {
    int slot = line.substring(7).toInt();
    if (slot < 1 || slot > 1000) {
      Serial.println("[CMD] Usage: enroll <slot 1-1000>");
      return;
    }
    oled.show("ENROLL", "Follow Serial Monitor");
    bool ok = fingerprint.enroll((uint16_t) slot);
    Serial.printf("[CMD] Enrollment %s (slot %d)\n", ok ? "OK" : "FAILED", slot);
    oled.show(ok ? "ENROLLED" : "ENROLL FAIL", "Slot " + String(slot));
    if (ok) logEvent("config", "Fingerprint enrolled in slot " + String(slot));
    delay(1500);
    oled.show("READY", "Scan fingerprint to open attendance");
  } else if (line == "status") {
    Serial.printf("[CMD] state=%d wifi=%s rssi=%d queue=%d session=%s\n",
                  (int) state, wifi.isConnected() ? "up" : "down",
                  wifi.rssi(), (int) queue.size(), sessionCode.c_str());
  } else if (line.length() > 0) {
    Serial.println("[CMD] Commands: enroll <slot> | status");
  }
}

// ---------------------------------------------------------------------
void applyServerTime(const String& iso) {
  if (iso.length() < 19) return;
  struct tm tmv = {};
  if (strptime(iso.c_str(), "%Y-%m-%dT%H:%M:%S", &tmv) != nullptr) {
    api.setTimeOffset(mktime(&tmv));
    Serial.println("[TIME] Synced with server: " + iso);
  }
}

void logEvent(const char* event, const String& details) {
  if (!wifi.isConnected()) return;
  JsonDocument body;
  body["event"]   = event;
  body["details"] = details;
  api.post("/api/device/log", body);
}
