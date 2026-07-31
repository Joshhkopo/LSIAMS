#pragma once
// Offline attendance queue persisted in LittleFS (survives power loss).
// FIFO order is preserved; corrupted lines are skipped during load so a
// partial write never blocks the rest of the queue.

#include <Arduino.h>
#include <LittleFS.h>
#include <ArduinoJson.h>
#include "config.h"

struct QueuedTap {
  String sessionId;
  String uid;
  String timestamp;
};

class QueueManager {
public:
  void begin() {
    if (!LittleFS.begin(true)) {
      Serial.println("[QUEUE] LittleFS mount failed!");
      return;
    }
    load();
    Serial.printf("[QUEUE] %d pending record(s) restored.\n", (int) queue_.size());
  }

  size_t size() const { return queue_.size(); }
  bool isFull() const { return queue_.size() >= OFFLINE_QUEUE_LIMIT; }

  bool push(const String& sessionId, const String& uid, const String& timestamp) {
    if (isFull()) return false;
    queue_.push_back({sessionId, uid, timestamp});
    persist();
    return true;
  }

  // Peek up to `count` oldest records (for a sync batch).
  std::vector<QueuedTap> peek(size_t count) const {
    std::vector<QueuedTap> out;
    for (size_t i = 0; i < queue_.size() && i < count; i++) out.push_back(queue_[i]);
    return out;
  }

  // Remove the first `count` records after a confirmed sync.
  void drop(size_t count) {
    if (count >= queue_.size()) queue_.clear();
    else queue_.erase(queue_.begin(), queue_.begin() + count);
    persist();
  }

private:
  std::vector<QueuedTap> queue_;
  static constexpr const char* FILE_PATH = "/queue.jsonl";

  void load() {
    queue_.clear();
    File f = LittleFS.open(FILE_PATH, "r");
    if (!f) return;
    while (f.available()) {
      String line = f.readStringUntil('\n');
      line.trim();
      if (line.isEmpty()) continue;
      JsonDocument doc;
      if (deserializeJson(doc, line) != DeserializationError::Ok) continue; // repair: skip bad line
      QueuedTap t;
      t.sessionId = String((const char*) (doc["s"] | ""));
      t.uid       = String((const char*) (doc["u"] | ""));
      t.timestamp = String((const char*) (doc["t"] | ""));
      if (!t.uid.isEmpty()) queue_.push_back(t);
    }
    f.close();
  }

  void persist() {
    File f = LittleFS.open(FILE_PATH, "w");
    if (!f) { Serial.println("[QUEUE] Persist failed!"); return; }
    for (const auto& t : queue_) {
      JsonDocument doc;
      doc["s"] = t.sessionId;
      doc["u"] = t.uid;
      doc["t"] = t.timestamp;
      serializeJson(doc, f);
      f.print('\n');
    }
    f.close();
  }
};
