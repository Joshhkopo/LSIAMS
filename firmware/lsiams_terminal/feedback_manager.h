#pragma once
// Buzzer + status LED patterns per the L-SIAMS UX specification:
//   short beep  = success (green)     double beep = duplicate (yellow)
//   long beep   = invalid (red)       rapid beep  = fingerprint failure

#include <Arduino.h>
#include "config.h"

class FeedbackManager {
public:
  void begin() {
    pinMode(PIN_BUZZER, OUTPUT);
    pinMode(PIN_LED_GREEN, OUTPUT);
    pinMode(PIN_LED_YELLOW, OUTPUT);
    pinMode(PIN_LED_RED, OUTPUT);
    pinMode(PIN_LED_BLUE, OUTPUT);
    allOff();
  }

  void wifi(bool connected) { digitalWrite(PIN_LED_BLUE, connected ? HIGH : LOW); }

  void success() { flash(PIN_LED_GREEN); beep(120); }

  void duplicate() {
    flash(PIN_LED_YELLOW);
    beep(90); delay(90); beep(90);
  }

  void invalid() { flash(PIN_LED_RED); beep(600); }

  void fingerprintFail() {
    flash(PIN_LED_RED);
    for (int i = 0; i < 4; i++) { beep(60); delay(60); }
  }

  void allOff() {
    digitalWrite(PIN_LED_GREEN, LOW);
    digitalWrite(PIN_LED_YELLOW, LOW);
    digitalWrite(PIN_LED_RED, LOW);
  }

private:
  void beep(unsigned ms) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(ms);
    digitalWrite(PIN_BUZZER, LOW);
  }
  void flash(uint8_t pin) {
    allOff();
    digitalWrite(pin, HIGH);
    // LED is cleared on the next status change / loop tick.
  }
};
