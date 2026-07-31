#pragma once
// WiFi lifecycle with automatic reconnection and exponential backoff.

#include <Arduino.h>
#include <WiFi.h>
#include "config.h"

class WifiManager {
public:
  void begin() {
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    connect();
  }

  bool isConnected() const { return WiFi.status() == WL_CONNECTED; }
  int rssi() const { return isConnected() ? WiFi.RSSI() : 0; }
  String mac() const { return WiFi.macAddress(); }

  // Call from loop(); retries with backoff capped at 30 s.
  void maintain() {
    if (isConnected()) { backoffMs_ = 1000; return; }
    unsigned long now = millis();
    if (now - lastAttempt_ < backoffMs_) return;
    lastAttempt_ = now;
    backoffMs_ = min(backoffMs_ * 2, 30000UL);
    connect();
  }

private:
  unsigned long lastAttempt_ = 0;
  unsigned long backoffMs_ = 1000;

  void connect() {
    Serial.printf("[WiFi] Connecting to %s...\n", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    unsigned long start = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - start < 8000) {
      delay(200);
    }
    Serial.println(isConnected()
      ? "[WiFi] Connected: " + WiFi.localIP().toString()
      : "[WiFi] Connection failed; will retry.");
  }
};
