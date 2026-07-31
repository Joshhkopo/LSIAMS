#pragma once
// HTTPS REST client. Every request automatically carries the device
// authentication envelope required by the server:
//   device_id + api_key + timestamp (server-synced) + single-use nonce.

#include <Arduino.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "config.h"

struct ApiResponse {
  bool ok = false;          // transport-level success (HTTP reachable)
  bool success = false;     // application-level "success" flag
  int status = 0;
  String message;
  JsonDocument doc;         // full parsed body
};

class ApiClient {
public:
  // Offset between server epoch and local millis-based clock.
  void setTimeOffset(time_t serverEpoch) {
    bootEpoch_ = serverEpoch - (millis() / 1000);
  }
  time_t now() const { return bootEpoch_ + (millis() / 1000); }
  bool timeSynced() const { return bootEpoch_ != 0; }

  String isoTimestamp(time_t t = 0) const {
    time_t v = t == 0 ? now() : t;
    struct tm tmv;
    gmtime_r(&v, &tmv);
    char buf[24];
    strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S", &tmv);
    return String(buf);
  }

  // POST JSON to an API path; the auth envelope is merged into the body.
  ApiResponse post(const String& path, JsonDocument& body) {
    ApiResponse res;

    body["device_id"] = DEVICE_ID;
    body["api_key"]   = API_KEY;
    body["timestamp"] = isoTimestamp();
    body["nonce"]     = makeNonce();

    String payload;
    serializeJson(body, payload);

    HTTPClient http;
    http.setTimeout(HTTP_TIMEOUT_MS);
    bool began;
#if USE_TLS
    // Production: HTTPS with certificate pinning.
    WiFiClientSecure client;
    if (strlen(SERVER_CA_CERT) > 10) {
      client.setCACert(SERVER_CA_CERT);       // pinned certificate
    } else {
      client.setInsecure();                   // bench testing only
    }
    String url = String("https://") + SERVER_HOST + ":" + SERVER_PORT + path;
    began = http.begin(client, url);
#else
    // Bench/development only: plain HTTP to the PHP dev server.
    String url = String("http://") + SERVER_HOST + ":" + SERVER_PORT + path;
    began = http.begin(url);
#endif
    if (!began) {
      res.message = "HTTP begin failed";
      return res;
    }
    http.addHeader("Content-Type", "application/json");
    res.status = http.POST(payload);
    if (res.status <= 0) {
      res.message = http.errorToString(res.status);
      http.end();
      return res;
    }

    String bodyStr = http.getString();
    http.end();
    res.ok = true;
    if (deserializeJson(res.doc, bodyStr) == DeserializationError::Ok) {
      res.success = res.doc["success"] | false;
      res.message = String((const char*) (res.doc["message"] | ""));
    } else {
      res.message = "Malformed server response";
    }
    return res;
  }

private:
  time_t bootEpoch_ = 0;

  String makeNonce() {
    // 64-bit random nonce; the server enforces single use per device.
    char buf[17];
    snprintf(buf, sizeof(buf), "%08lx%08lx",
             (unsigned long) esp_random(), (unsigned long) esp_random());
    return String(buf);
  }
};
