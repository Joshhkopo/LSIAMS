# L-SIAMS ESP32 Firmware

Firmware for the classroom attendance terminal (ESP32 DevKit V1 + MFRC522 +
R307 + optional SSD1306 OLED + buzzer + status LEDs).

## Required Arduino libraries

| Library                     | Purpose                    |
|-----------------------------|----------------------------|
| `MFRC522` (miguelbalboa)    | RFID reader (SPI)          |
| `Adafruit Fingerprint`      | R307 sensor (UART2)        |
| `Adafruit SSD1306` + GFX    | OLED display (I2C)         |
| `ArduinoJson` (v7)          | API payloads               |
| ESP32 core (`WiFi`, `HTTPClient`, `WiFiClientSecure`, `LittleFS`) | built in |

## Wiring (ESP32 DevKit V1)

| Peripheral | Pins |
|------------|------|
| MFRC522    | SS=5, RST=27, SCK=18, MISO=19, MOSI=23, 3.3 V |
| R307       | TX→GPIO16 (RX2), RX→GPIO17 (TX2), 5 V (3.3 V logic) |
| SSD1306    | SDA=21, SCL=22, addr 0x3C |
| Buzzer     | GPIO25 |
| LEDs       | Green=32, Yellow=33, Red=26, Blue(WiFi)=14 |

## Provisioning a terminal

1. Register the device in the web app (*IoT Devices → Register Device*)
   using its MAC address and classroom. Copy the API key — shown once.
2. Edit `config.h`: `DEVICE_ID`, `API_KEY`, WiFi credentials, `SERVER_HOST`,
   and paste the server certificate into `SERVER_CA_CERT` (pinning).
3. Flash, mount in the classroom, power on. The OLED shows `READY` after a
   successful handshake and the dashboard shows the device Online.

## Behaviour summary

- **Fingerprint first**: the terminal will not accept RFID taps until the
  server has verified the assigned teacher's fingerprint slot and opened a
  session. A second verified fingerprint closes the session.
- **Offline mode**: taps are queued in LittleFS (survives power loss,
  limit 500) with their original timestamps and synced oldest-first when
  the LAN returns; duplicates are resolved server-side.
- **Heartbeat**: every 30 s with RSSI, queue depth, firmware and uptime.
  90 s of silence marks the device Offline on the dashboard.
- **Security**: every request carries device id, API key, a server-synced
  timestamp (±30 s window) and a single-use nonce; TLS with certificate
  pinning.
- **Lockout**: 5 failed fingerprint scans lock the sensor for 5 minutes and
  raise an administrator alert server-side.
