#pragma once
// MFRC522 RFID reader (SPI, 13.56 MHz). Returns the card UID as an
// uppercase hex string with per-card debouncing so one tap = one event.

#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>
#include "config.h"

class RfidManager {
public:
  void begin() {
    SPI.begin();
    reader_.PCD_Init(PIN_RC522_SS, PIN_RC522_RST);
    Serial.println("[RFID] MFRC522 initialized.");
  }

  // Non-blocking poll. Returns "" when no (new) card is present.
  String readUid() {
    if (!reader_.PICC_IsNewCardPresent() || !reader_.PICC_ReadCardSerial()) {
      return "";
    }
    String uid;
    for (byte i = 0; i < reader_.uid.size; i++) {
      if (reader_.uid.uidByte[i] < 0x10) uid += "0";
      uid += String(reader_.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();
    reader_.PICC_HaltA();
    reader_.PCD_StopCrypto1();

    // Debounce: ignore the same card re-read within RFID_DEBOUNCE_MS.
    unsigned long now = millis();
    if (uid == lastUid_ && now - lastReadMs_ < RFID_DEBOUNCE_MS) {
      return "";
    }
    lastUid_ = uid;
    lastReadMs_ = now;
    return uid;
  }

private:
  MFRC522 reader_;
  String lastUid_;
  unsigned long lastReadMs_ = 0;
};
