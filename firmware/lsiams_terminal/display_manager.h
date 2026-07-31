#pragma once
// SSD1306 OLED wrapper. The display is optional: every call degrades to
// Serial logging when the panel is not detected at boot.

#include <Arduino.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

class DisplayManager {
public:
  void begin() {
    available_ = display_.begin(SSD1306_SWITCHCAPVCC, 0x3C);
    if (available_) {
      display_.clearDisplay();
      display_.setTextColor(SSD1306_WHITE);
    }
    show("L-SIAMS", "Booting...");
  }

  // Two-line status screen: big header + detail line(s).
  void show(const String& title, const String& detail = "") {
    Serial.printf("[OLED] %s | %s\n", title.c_str(), detail.c_str());
    if (!available_) return;
    display_.clearDisplay();
    display_.setTextSize(2);
    display_.setCursor(0, 0);
    display_.println(title.substring(0, 10));
    display_.setTextSize(1);
    display_.setCursor(0, 26);
    display_.println(detail.substring(0, 63));
    display_.display();
  }

  void showAttendance(const String& student, const String& status, const String& time) {
    show(status, student + "  " + time);
  }

private:
  Adafruit_SSD1306 display_{128, 64, &Wire, -1};
  bool available_ = false;
};
