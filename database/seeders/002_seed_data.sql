-- =====================================================================
-- L-SIAMS — Seed data
-- Run AFTER 001_create_schema.sql:
--   mysql -u root -p lsiams_db < database/seeders/002_seed_data.sql
--
-- Default credentials (CHANGE IMMEDIATELY AFTER FIRST LOGIN):
--   Administrator : admin      / ChangeMe!12345
--   Teacher       : jdelacruz  / Teacher!12345
-- Sample device API key (development only — regenerate in production):
--   DEV-001 : SAMPLE-DEV-KEY-0000000000000000
-- =====================================================================

USE lsiams_db;

-- Roles ---------------------------------------------------------------
INSERT INTO roles (id, name, description) VALUES
(1, 'Administrator', 'Full control over every module'),
(2, 'Teacher', 'Attendance sessions, schedules, reports, own profile');

-- Permissions (RBAC matrix; future roles reuse these) -------------------
INSERT INTO permissions (name, description) VALUES
('manage_users', 'Create, edit, archive users'),
('manage_students', 'Full student management'),
('manage_teachers', 'Full teacher management'),
('manage_devices', 'Register and manage IoT devices'),
('manage_schedules', 'Create and edit schedules'),
('manage_rfid', 'Assign, replace, blacklist RFID cards'),
('manage_fingerprints', 'Enroll and manage fingerprints'),
('manage_settings', 'Change system settings'),
('manage_backups', 'Create and restore backups'),
('correct_attendance', 'Manually correct attendance records'),
('view_security', 'View the security center'),
('view_audit', 'View audit logs'),
('view_reports', 'Generate and export reports'),
('view_own_attendance', 'View attendance for own classes'),
('manage_own_profile', 'Update own profile and password');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE name IN ('view_reports', 'view_own_attendance', 'manage_own_profile');

-- Attendance statuses ---------------------------------------------------
INSERT INTO attendance_statuses (id, code, name) VALUES
(1, 'present', 'Present'),
(2, 'late', 'Late'),
(3, 'absent', 'Absent'),
(4, 'excused', 'Excused'),
(5, 'official_business', 'Official Business'),
(6, 'incomplete', 'Incomplete');

-- Users -----------------------------------------------------------------
-- admin / ChangeMe!12345
INSERT INTO users (id, role_id, username, email, password_hash, status, must_change_password) VALUES
(1, 1, 'admin', 'admin@lsiams.local',
 '$2y$12$X11MoWHLM5suzZx5L5aFT.0yyFmVrJFri.rYnQaX2E2DK3OYcLq0S', 'active', 1),
-- jdelacruz / Teacher!12345
(2, 2, 'jdelacruz', 'jdelacruz@lsiams.local',
 '$2y$12$A8nLaYpO8Dm/2wDRffg6Eu3dCVOQ1/ac0tlLEv3zpMKlWkwbuudAq', 'active', 1);

-- School structure --------------------------------------------------------
INSERT INTO grade_levels (id, name, sort_order) VALUES
(1, 'Grade 7', 1), (2, 'Grade 8', 2), (3, 'Grade 9', 3),
(4, 'Grade 10', 4), (5, 'Grade 11', 5), (6, 'Grade 12', 6);

INSERT INTO sections (id, grade_level_id, name, capacity) VALUES
(1, 1, 'Rizal', 40),
(2, 1, 'Bonifacio', 40),
(3, 2, 'Mabini', 40);

INSERT INTO teachers (id, employee_number, user_id, department, first_name, middle_name, last_name, email, phone) VALUES
(1, 'EMP-0001', 2, 'Mathematics', 'Juan', 'Santos', 'Dela Cruz', 'jdelacruz@lsiams.local', '+63 912 345 6789');

INSERT INTO subjects (id, subject_code, name, description, units) VALUES
(1, 'MATH7', 'Mathematics 7', 'Grade 7 general mathematics', 1.0),
(2, 'SCI7', 'Science 7', 'Grade 7 integrated science', 1.0),
(3, 'ENG7', 'English 7', 'Grade 7 English', 1.0);

INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (1, 1), (1, 2);

INSERT INTO classrooms (id, room_number, building, floor, capacity) VALUES
(1, 'RM-101', 'Main Building', '1st', 40),
(2, 'RM-102', 'Main Building', '1st', 40);

-- Mon–Fri (1–5) 8:00-9:00 MATH7 for section Rizal in RM-101
INSERT INTO schedules
(teacher_id, subject_id, section_id, classroom_id, day_of_week, start_time, end_time, attendance_window_minutes, late_threshold_minutes)
VALUES
(1, 1, 1, 1, 1, '08:00:00', '09:00:00', 20, 20),
(1, 1, 1, 1, 2, '08:00:00', '09:00:00', 20, 20),
(1, 1, 1, 1, 3, '08:00:00', '09:00:00', 20, 20),
(1, 1, 1, 1, 4, '08:00:00', '09:00:00', 20, 20),
(1, 1, 1, 1, 5, '08:00:00', '09:00:00', 20, 20),
(1, 2, 1, 1, 1, '09:00:00', '10:00:00', 20, 20),
(1, 2, 1, 1, 3, '09:00:00', '10:00:00', 20, 20);

-- Students ----------------------------------------------------------------
INSERT INTO students (id, student_number, section_id, grade_level_id, first_name, middle_name, last_name, gender, birthdate, guardian_name, guardian_contact) VALUES
(1, '2026-0001', 1, 1, 'John', 'Reyes', 'Doe', 'male', '2013-04-15', 'Jane Doe', '+63 917 000 0001'),
(2, '2026-0002', 1, 1, 'Maria', 'Lopez', 'Santos', 'female', '2013-08-02', 'Ana Santos', '+63 917 000 0002'),
(3, '2026-0003', 1, 1, 'Pedro', NULL, 'Garcia', 'male', '2013-01-20', 'Luis Garcia', '+63 917 000 0003'),
(4, '2026-0004', 1, 1, 'Liza', 'Cruz', 'Manalo', 'female', '2013-11-30', 'Rosa Manalo', '+63 917 000 0004'),
(5, '2026-0005', 2, 1, 'Carlo', NULL, 'Ramos', 'male', '2013-06-11', 'Elena Ramos', '+63 917 000 0005');

INSERT INTO rfid_cards (student_id, uid, issue_date, status) VALUES
(1, '04A7C1935D', '2026-06-01', 'active'),
(2, '04B2D4816E', '2026-06-01', 'active'),
(3, '04C9E2A47F', '2026-06-01', 'active'),
(4, '04D1F3B580', '2026-06-01', 'active'),
(5, '04E4A5C691', '2026-06-01', 'active');

-- Device --------------------------------------------------------------------
INSERT INTO devices (id, device_code, device_name, mac_address, classroom_id, firmware_version, wifi_ssid, status) VALUES
(1, 'DEV-001', 'RM-101 Attendance Terminal', 'AA:BB:CC:DD:EE:01', 1, '1.0.0', 'LSIAMS-NET', 'active');

-- API key for DEV-001 (SHA-256 of 'SAMPLE-DEV-KEY-0000000000000000').
-- Development convenience only; regenerate from Device Management in production.
INSERT INTO api_keys (device_id, key_hash, key_hint, status) VALUES
(1, '0610ca7fc8000f6067a4ae12ca42e319ed2c1ae9c04a1c437cdfd8ae464a207c', 'SAMPLE', 'active');

-- Fingerprint enrollment for the sample teacher (slot 1 on DEV-001)
INSERT INTO fingerprint_templates (teacher_id, sensor_template_id, enrolled_device_id, status) VALUES
(1, 1, 1, 'active');

-- Settings --------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, description) VALUES
('school_name', 'Sample National High School', 'Displayed on reports and headers'),
('school_logo', '', 'Relative path to logo'),
('attendance_window_minutes', '20', 'Default attendance window'),
('late_threshold_minutes', '20', 'Default late threshold'),
('absent_cutoff_minutes', '60', 'Minutes after start when taps are rejected'),
('heartbeat_interval_seconds', '30', 'Expected device heartbeat interval'),
('heartbeat_offline_after_seconds', '90', 'Mark device offline after this silence'),
('api_timestamp_window_seconds', '30', 'Replay-protection timestamp window'),
('fingerprint_max_failures', '5', 'Failed scans before device lock + alert'),
('timezone', 'Asia/Manila', 'System timezone'),
('backup_schedule', 'daily', 'Automatic backup frequency'),
('theme', 'light', 'Default UI theme');
