-- =====================================================================
-- L-SIAMS — Complete MySQL 8 schema (InnoDB, utf8mb4, 3NF)
-- Run:  mysql -u root -p < database/migrations/001_create_schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS lsiams_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lsiams_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Authentication
-- ---------------------------------------------------------------------

CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id         INT UNSIGNED NOT NULL,
    username        VARCHAR(60)  NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_at       DATETIME NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login      DATETIME NULL,
    photo           VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
    INDEX idx_users_username (username),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_pr_token (token_hash)
) ENGINE=InnoDB;

CREATE TABLE login_history (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    status     ENUM('success','failed','locked','inactive') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_lh_user_time (user_id, created_at),
    INDEX idx_lh_time (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- School structure
-- ---------------------------------------------------------------------

CREATE TABLE grade_levels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status     ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sections (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_level_id INT UNSIGNED NOT NULL,
    name           VARCHAR(60) NOT NULL,
    capacity       INT UNSIGNED NOT NULL DEFAULT 40,
    status         ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section (grade_level_id, name),
    CONSTRAINT fk_sections_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels (id)
) ENGINE=InnoDB;

CREATE TABLE students (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_number   VARCHAR(30)  NOT NULL UNIQUE,
    section_id       INT UNSIGNED NULL,
    grade_level_id   INT UNSIGNED NULL,
    first_name       VARCHAR(80)  NOT NULL,
    middle_name      VARCHAR(80)  NULL,
    last_name        VARCHAR(80)  NOT NULL,
    gender           ENUM('male','female') NOT NULL,
    birthdate        DATE NULL,
    address          VARCHAR(255) NULL,
    email            VARCHAR(150) NULL,
    guardian_name    VARCHAR(150) NULL,
    guardian_contact VARCHAR(30)  NULL,
    photo            VARCHAR(255) NULL,
    status           ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL,
    CONSTRAINT fk_students_section FOREIGN KEY (section_id) REFERENCES sections (id),
    CONSTRAINT fk_students_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels (id),
    INDEX idx_students_number (student_number),
    INDEX idx_students_name (last_name, first_name),
    INDEX idx_students_section (section_id),
    INDEX idx_students_status (status)
) ENGINE=InnoDB;

CREATE TABLE teachers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(30) NOT NULL UNIQUE,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    department      VARCHAR(100) NULL,
    first_name      VARCHAR(80)  NOT NULL,
    middle_name     VARCHAR(80)  NULL,
    last_name       VARCHAR(80)  NOT NULL,
    email           VARCHAR(150) NULL,
    phone           VARCHAR(30)  NULL,
    photo           VARCHAR(255) NULL,
    status          ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users (id),
    INDEX idx_teachers_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE subjects (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(30)  NOT NULL UNIQUE,
    name         VARCHAR(120) NOT NULL,
    description  VARCHAR(255) NULL,
    units        DECIMAL(4,1) NOT NULL DEFAULT 1.0,
    status       ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subjects_code (subject_code)
) ENGINE=InnoDB;

CREATE TABLE teacher_subjects (
    teacher_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE classrooms (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(30) NOT NULL UNIQUE,
    building    VARCHAR(80) NULL,
    floor       VARCHAR(20) NULL,
    capacity    INT UNSIGNED NOT NULL DEFAULT 40,
    status      ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_classrooms_room (room_number)
) ENGINE=InnoDB;

CREATE TABLE schedules (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id                INT UNSIGNED NOT NULL,
    subject_id                INT UNSIGNED NOT NULL,
    section_id                INT UNSIGNED NOT NULL,
    classroom_id              INT UNSIGNED NOT NULL,
    day_of_week               TINYINT UNSIGNED NOT NULL COMMENT '1=Mon ... 7=Sun',
    start_time                TIME NOT NULL,
    end_time                  TIME NOT NULL,
    attendance_window_minutes INT UNSIGNED NOT NULL DEFAULT 20,
    late_threshold_minutes    INT UNSIGNED NOT NULL DEFAULT 20,
    status                    ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sched_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_sched_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
    CONSTRAINT fk_sched_section FOREIGN KEY (section_id) REFERENCES sections (id),
    CONSTRAINT fk_sched_room FOREIGN KEY (classroom_id) REFERENCES classrooms (id),
    INDEX idx_sched_teacher_day (teacher_id, day_of_week, start_time),
    INDEX idx_sched_room_day (classroom_id, day_of_week, start_time),
    INDEX idx_sched_time (day_of_week, start_time, end_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- IoT devices
-- ---------------------------------------------------------------------

CREATE TABLE devices (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code      VARCHAR(30)  NOT NULL UNIQUE COMMENT 'e.g. DEV-001',
    device_name      VARCHAR(100) NOT NULL,
    mac_address      VARCHAR(17)  NOT NULL UNIQUE,
    classroom_id     INT UNSIGNED NULL UNIQUE COMMENT 'one device per classroom',
    firmware_version VARCHAR(20)  NULL,
    ip_address       VARCHAR(45)  NULL,
    wifi_ssid        VARCHAR(64)  NULL,
    status           ENUM('active','disabled','archived') NOT NULL DEFAULT 'active',
    is_online        TINYINT(1)   NOT NULL DEFAULT 0,
    last_heartbeat   DATETIME NULL,
    registered_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_devices_room FOREIGN KEY (classroom_id) REFERENCES classrooms (id),
    INDEX idx_devices_code (device_code),
    INDEX idx_devices_mac (mac_address),
    INDEX idx_devices_heartbeat (last_heartbeat)
) ENGINE=InnoDB;

CREATE TABLE api_keys (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id  INT UNSIGNED NOT NULL,
    key_hash   CHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 of the 256-bit key; plaintext shown once',
    key_hint   CHAR(6)  NOT NULL COMMENT 'first 6 chars, for identification only',
    status     ENUM('active','revoked') NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_keys_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    INDEX idx_keys_device (device_id, status)
) ENGINE=InnoDB;

CREATE TABLE api_nonces (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id  INT UNSIGNED NOT NULL,
    nonce      VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nonce (device_id, nonce),
    CONSTRAINT fk_nonce_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    INDEX idx_nonce_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE device_heartbeats (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id       INT UNSIGNED NOT NULL,
    signal_strength SMALLINT NULL COMMENT 'RSSI dBm',
    queue_count     INT UNSIGNED NOT NULL DEFAULT 0,
    firmware        VARCHAR(20) NULL,
    uptime_seconds  BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hb_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    INDEX idx_hb_device_time (device_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE device_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id  INT UNSIGNED NOT NULL,
    event      ENUM('boot','shutdown','restart','sync','auth','error','offline','online','config') NOT NULL,
    details    VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dl_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    INDEX idx_dl_device_time (device_id, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RFID
-- ---------------------------------------------------------------------

CREATE TABLE rfid_cards (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id     INT UNSIGNED NOT NULL,
    uid            VARCHAR(32) NOT NULL UNIQUE,
    issue_date     DATE NOT NULL,
    replacement_of INT UNSIGNED NULL COMMENT 'previous card id when replaced',
    status         ENUM('active','inactive','lost','blacklisted') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rfid_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_rfid_prev FOREIGN KEY (replacement_of) REFERENCES rfid_cards (id),
    INDEX idx_rfid_uid (uid),
    INDEX idx_rfid_student (student_id, status)
) ENGINE=InnoDB;

CREATE TABLE rfid_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid        VARCHAR(32) NOT NULL,
    student_id INT UNSIGNED NULL,
    device_id  INT UNSIGNED NULL,
    result     ENUM('accepted','late','duplicate','unknown','disabled','rejected') NOT NULL,
    details    VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rl_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT fk_rl_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE SET NULL,
    INDEX idx_rl_uid_time (uid, created_at),
    INDEX idx_rl_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE unknown_rfid_cards (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid        VARCHAR(32) NOT NULL UNIQUE,
    device_id  INT UNSIGNED NULL,
    seen_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status     ENUM('new','ignored','blacklisted','assigned') NOT NULL DEFAULT 'new',
    CONSTRAINT fk_urc_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fingerprints (teachers only)
-- ---------------------------------------------------------------------

CREATE TABLE fingerprint_templates (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id         INT UNSIGNED NOT NULL UNIQUE COMMENT 'one fingerprint per teacher',
    sensor_template_id INT UNSIGNED NOT NULL COMMENT 'slot id inside the R307 sensor',
    enrolled_device_id INT UNSIGNED NULL,
    enrolled_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status             ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fp_slot (enrolled_device_id, sensor_template_id),
    CONSTRAINT fk_fp_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_fp_device FOREIGN KEY (enrolled_device_id) REFERENCES devices (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE fingerprint_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NULL,
    device_id  INT UNSIGNED NULL,
    result     ENUM('verified','failed','unknown') NOT NULL,
    details    VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fl_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL,
    CONSTRAINT fk_fl_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE SET NULL,
    INDEX idx_fl_time (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Attendance
-- ---------------------------------------------------------------------

CREATE TABLE attendance_statuses (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE attendance_sessions (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_code VARCHAR(30) NOT NULL UNIQUE,
    teacher_id   INT UNSIGNED NOT NULL,
    device_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NOT NULL,
    schedule_id  INT UNSIGNED NOT NULL,
    subject_id   INT UNSIGNED NOT NULL,
    section_id   INT UNSIGNED NOT NULL,
    started_at   DATETIME NOT NULL,
    ended_at     DATETIME NULL,
    status       ENUM('open','closed','archived') NOT NULL DEFAULT 'open',
    opened_ip    VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_as_teacher  FOREIGN KEY (teacher_id)  REFERENCES teachers (id),
    CONSTRAINT fk_as_device   FOREIGN KEY (device_id)   REFERENCES devices (id),
    CONSTRAINT fk_as_room     FOREIGN KEY (classroom_id) REFERENCES classrooms (id),
    CONSTRAINT fk_as_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id),
    CONSTRAINT fk_as_subject  FOREIGN KEY (subject_id)  REFERENCES subjects (id),
    CONSTRAINT fk_as_section  FOREIGN KEY (section_id)  REFERENCES sections (id),
    INDEX idx_as_room_status (classroom_id, status),
    INDEX idx_as_teacher_time (teacher_id, started_at),
    INDEX idx_as_time (started_at)
) ENGINE=InnoDB;

CREATE TABLE attendance_records (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id  BIGINT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    teacher_id  INT UNSIGNED NOT NULL,
    device_id   INT UNSIGNED NULL,
    rfid_uid    VARCHAR(32) NULL,
    status_id   TINYINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL,
    source      ENUM('rfid','sync','system','manual') NOT NULL DEFAULT 'rfid',
    ip_address  VARCHAR(45) NULL,
    mac_address VARCHAR(17) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (session_id, student_id) COMMENT 'duplicate prevention',
    CONSTRAINT fk_ar_session FOREIGN KEY (session_id) REFERENCES attendance_sessions (id),
    CONSTRAINT fk_ar_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_ar_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_ar_device  FOREIGN KEY (device_id)  REFERENCES devices (id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_status  FOREIGN KEY (status_id)  REFERENCES attendance_statuses (id),
    INDEX idx_ar_student_time (student_id, recorded_at),
    INDEX idx_ar_session (session_id),
    INDEX idx_ar_time (recorded_at),
    INDEX idx_ar_uid (rfid_uid)
) ENGINE=InnoDB;

CREATE TABLE attendance_modifications (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id BIGINT UNSIGNED NOT NULL,
    admin_id      INT UNSIGNED NOT NULL,
    old_status_id TINYINT UNSIGNED NOT NULL,
    new_status_id TINYINT UNSIGNED NOT NULL,
    reason        VARCHAR(500) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_am_attendance FOREIGN KEY (attendance_id) REFERENCES attendance_records (id),
    CONSTRAINT fk_am_admin FOREIGN KEY (admin_id) REFERENCES users (id),
    CONSTRAINT fk_am_old FOREIGN KEY (old_status_id) REFERENCES attendance_statuses (id),
    CONSTRAINT fk_am_new FOREIGN KEY (new_status_id) REFERENCES attendance_statuses (id),
    INDEX idx_am_attendance (attendance_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Security & auditing
-- ---------------------------------------------------------------------

CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    action          VARCHAR(100) NOT NULL,
    module          VARCHAR(50)  NOT NULL,
    affected_record VARCHAR(50)  NULL,
    old_value       JSON NULL,
    new_value       JSON NULL,
    ip_address      VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_al_time (created_at),
    INDEX idx_al_user (user_id, created_at),
    INDEX idx_al_module (module)
) ENGINE=InnoDB;

CREATE TABLE security_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event       VARCHAR(60) NOT NULL,
    severity    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    description VARCHAR(500) NOT NULL,
    source_ip   VARCHAR(45) NULL,
    device_id   INT UNSIGNED NULL,
    resolved    TINYINT(1) NOT NULL DEFAULT 0,
    resolved_by INT UNSIGNED NULL,
    notes       VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sl_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE SET NULL,
    CONSTRAINT fk_sl_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_sl_time (created_at),
    INDEX idx_sl_severity (severity, resolved)
) ENGINE=InnoDB;

CREATE TABLE blocked_ips (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    reason       VARCHAR(255) NULL,
    blocked_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unblocked_at DATETIME NULL,
    CONSTRAINT fk_bi_user FOREIGN KEY (blocked_by) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_bi_ip (ip_address, unblocked_at)
) ENGINE=InnoDB;

CREATE TABLE blocked_devices (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id    INT UNSIGNED NOT NULL,
    reason       VARCHAR(255) NULL,
    blocked_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unblocked_at DATETIME NULL,
    CONSTRAINT fk_bd_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    CONSTRAINT fk_bd_user FOREIGN KEY (blocked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
    rl_key       VARCHAR(190) NOT NULL PRIMARY KEY,
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    last_hit     DATETIME NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- System
-- ---------------------------------------------------------------------

CREATE TABLE settings (
    setting_key   VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    description   VARCHAR(255) NULL,
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    priority   ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE generated_reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    report_type  VARCHAR(50)  NOT NULL,
    filters      JSON NULL,
    file_path    VARCHAR(255) NULL,
    generated_by INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gr_user FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE backups (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    size_bytes BIGINT UNSIGNED NULL,
    status     ENUM('completed','failed','restored') NOT NULL DEFAULT 'completed',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backup_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
