#pragma once
// R307 fingerprint sensor (UART2). Local 1:N search returns the matched
// template slot id; the SERVER decides whether that slot may open an
// attendance session. Repeated failures lock the sensor temporarily.

#include <Arduino.h>
#include <Adafruit_Fingerprint.h>
#include "config.h"

class FingerprintManager {
public:
  void begin() {
    Serial2.begin(57600, SERIAL_8N1, PIN_R307_RX, PIN_R307_TX);
    sensor_ = new Adafruit_Fingerprint(&Serial2);
    available_ = sensor_->verifyPassword();
    Serial.println(available_ ? "[FP] R307 ready." : "[FP] R307 NOT detected!");
  }

  bool isAvailable() const { return available_; }
  bool isLocked() const {
    return lockUntil_ != 0 && millis() < lockUntil_;
  }

  // Non-blocking-ish scan. Returns:
  //   >0 matched template slot, 0 no finger present, -1 scan/match failure.
  int scan() {
    if (!available_ || isLocked()) return 0;
    uint8_t p = sensor_->getImage();
    if (p == FINGERPRINT_NOFINGER) return 0;
    if (p != FINGERPRINT_OK) return fail();
    if (sensor_->image2Tz() != FINGERPRINT_OK) return fail();
    if (sensor_->fingerSearch() != FINGERPRINT_OK) return fail();
    failures_ = 0;
    return sensor_->fingerID;
  }

  // Enrollment helper used in maintenance mode (slot chosen by the admin).
  bool enroll(uint16_t slot) {
    if (!available_) return false;
    for (int sample = 1; sample <= 2; sample++) {
      Serial.printf("[FP] Place finger (sample %d)...\n", sample);
      while (sensor_->getImage() != FINGERPRINT_OK) delay(50);
      if (sensor_->image2Tz(sample) != FINGERPRINT_OK) return false;
      if (sample == 1) {
        Serial.println("[FP] Remove finger.");
        delay(1500);
        while (sensor_->getImage() != FINGERPRINT_NOFINGER) delay(50);
      }
    }
    if (sensor_->createModel() != FINGERPRINT_OK) return false;
    return sensor_->storeModel(slot) == FINGERPRINT_OK;
  }

private:
  Adafruit_Fingerprint* sensor_ = nullptr;
  bool available_ = false;
  uint8_t failures_ = 0;
  unsigned long lockUntil_ = 0;

  int fail() {
    if (++failures_ >= FINGERPRINT_MAX_FAILURES) {
      lockUntil_ = millis() + FINGERPRINT_LOCK_MS;
      failures_ = 0;
      Serial.println("[FP] Too many failures — sensor locked temporarily.");
    }
    return -1;
  }
};
