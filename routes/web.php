<?php

declare(strict_types=1);

/**
 * Web routes (session-authenticated). Middleware:
 *   auth  — logged-in user, csrf — state-changing requests,
 *   role:Administrator / role:Teacher — RBAC.
 */

use App\Controllers\AnalyticsController;
use App\Controllers\AttendanceController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ClassroomController;
use App\Controllers\DashboardController;
use App\Controllers\DeviceController;
use App\Controllers\FingerprintController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\RfidController;
use App\Controllers\ScheduleController;
use App\Controllers\SecurityController;
use App\Controllers\SettingController;
use App\Controllers\StudentController;
use App\Controllers\SubjectController;
use App\Controllers\TeacherController;
use App\Core\Router;

return static function (Router $r): void {
    $admin = ['auth', 'role:Administrator'];
    $adminWrite = ['auth', 'role:Administrator', 'csrf'];
    $any = ['auth'];
    $anyWrite = ['auth', 'csrf'];

    // Authentication
    $r->get('/', [AuthController::class, 'home']);
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'], ['csrf']);
    $r->post('/logout', [AuthController::class, 'logout'], $anyWrite);
    $r->get('/force-password', [AuthController::class, 'showForcePassword'], $any);
    $r->post('/force-password', [AuthController::class, 'forcePassword'], $anyWrite);

    // Dashboard (role-aware)
    $r->get('/dashboard', [DashboardController::class, 'index'], $any);
    $r->get('/dashboard/data', [DashboardController::class, 'data'], $any);

    // Students (admin)
    $r->get('/students', [StudentController::class, 'index'], $admin);
    $r->get('/students/data', [StudentController::class, 'data'], $admin);
    $r->get('/students/export', [StudentController::class, 'export'], $admin);
    $r->get('/students/{id}/detail', [StudentController::class, 'detail'], $admin);
    $r->post('/students', [StudentController::class, 'store'], $adminWrite);
    $r->post('/students/{id}/update', [StudentController::class, 'update'], $adminWrite);
    $r->post('/students/{id}/archive', [StudentController::class, 'archive'], $adminWrite);
    $r->post('/students/import', [StudentController::class, 'import'], $adminWrite);

    // Teachers (admin)
    $r->get('/teachers', [TeacherController::class, 'index'], $admin);
    $r->get('/teachers/data', [TeacherController::class, 'data'], $admin);
    $r->post('/teachers', [TeacherController::class, 'store'], $adminWrite);
    $r->post('/teachers/{id}/update', [TeacherController::class, 'update'], $adminWrite);
    $r->post('/teachers/{id}/archive', [TeacherController::class, 'archive'], $adminWrite);
    $r->post('/teachers/{id}/reset-password', [TeacherController::class, 'resetPassword'], $adminWrite);

    // Subjects (admin)
    $r->get('/subjects', [SubjectController::class, 'index'], $admin);
    $r->get('/subjects/data', [SubjectController::class, 'data'], $admin);
    $r->post('/subjects', [SubjectController::class, 'store'], $adminWrite);
    $r->post('/subjects/{id}/update', [SubjectController::class, 'update'], $adminWrite);
    $r->post('/subjects/{id}/archive', [SubjectController::class, 'archive'], $adminWrite);

    // Classrooms (admin)
    $r->get('/classrooms', [ClassroomController::class, 'index'], $admin);
    $r->get('/classrooms/data', [ClassroomController::class, 'data'], $admin);
    $r->post('/classrooms', [ClassroomController::class, 'store'], $adminWrite);
    $r->post('/classrooms/{id}/update', [ClassroomController::class, 'update'], $adminWrite);
    $r->post('/classrooms/{id}/archive', [ClassroomController::class, 'archive'], $adminWrite);

    // Schedules (admin manages; teachers view their own)
    $r->get('/schedules', [ScheduleController::class, 'index'], $any);
    $r->get('/schedules/data', [ScheduleController::class, 'data'], $any);
    $r->post('/schedules', [ScheduleController::class, 'store'], $adminWrite);
    $r->post('/schedules/{id}/update', [ScheduleController::class, 'update'], $adminWrite);
    $r->post('/schedules/{id}/archive', [ScheduleController::class, 'archive'], $adminWrite);

    // RFID (admin)
    $r->get('/rfid', [RfidController::class, 'index'], $admin);
    $r->get('/rfid/data', [RfidController::class, 'data'], $admin);
    $r->get('/rfid/unknown', [RfidController::class, 'unknown'], $admin);
    $r->get('/rfid/{uid}/history', [RfidController::class, 'history'], $admin);
    $r->post('/rfid', [RfidController::class, 'store'], $adminWrite);
    $r->post('/rfid/{id}/replace', [RfidController::class, 'replace'], $adminWrite);
    $r->post('/rfid/{id}/status', [RfidController::class, 'setStatus'], $adminWrite);
    $r->post('/rfid/unknown/{id}/status', [RfidController::class, 'setUnknownStatus'], $adminWrite);

    // Fingerprints (admin)
    $r->get('/fingerprints', [FingerprintController::class, 'index'], $admin);
    $r->get('/fingerprints/data', [FingerprintController::class, 'data'], $admin);
    $r->post('/fingerprints/enroll', [FingerprintController::class, 'enroll'], $adminWrite);
    $r->post('/fingerprints/{id}/status', [FingerprintController::class, 'setStatus'], $adminWrite);
    $r->post('/fingerprints/{id}/delete', [FingerprintController::class, 'delete'], $adminWrite);

    // Devices (admin)
    $r->get('/devices', [DeviceController::class, 'index'], $admin);
    $r->get('/devices/data', [DeviceController::class, 'data'], $admin);
    $r->get('/devices/{id}/logs', [DeviceController::class, 'logs'], $admin);
    $r->post('/devices', [DeviceController::class, 'store'], $adminWrite);
    $r->post('/devices/{id}/update', [DeviceController::class, 'update'], $adminWrite);
    $r->post('/devices/{id}/apikey', [DeviceController::class, 'generateApiKey'], $adminWrite);
    $r->post('/devices/{id}/status', [DeviceController::class, 'setStatus'], $adminWrite);

    // Attendance (admin: all; teachers: own classes)
    $r->get('/attendance', [AttendanceController::class, 'index'], $any);
    $r->get('/attendance/data', [AttendanceController::class, 'data'], $any);
    $r->get('/attendance/live', [AttendanceController::class, 'live'], $any);
    $r->get('/attendance/sessions', [AttendanceController::class, 'sessions'], $any);
    $r->get('/attendance/{id}/detail', [AttendanceController::class, 'detail'], $any);
    $r->post('/attendance/{id}/correct', [AttendanceController::class, 'correct'], $adminWrite);
    $r->post('/attendance/sessions/{id}/close', [AttendanceController::class, 'closeSession'], $anyWrite);

    // Reports
    $r->get('/reports', [ReportController::class, 'index'], $any);
    $r->get('/reports/data', [ReportController::class, 'data'], $any);
    $r->get('/reports/export', [ReportController::class, 'export'], $any);
    $r->get('/reports/print', [ReportController::class, 'printable'], $any);

    // Analytics (admin)
    $r->get('/analytics', [AnalyticsController::class, 'index'], $admin);
    $r->get('/analytics/data', [AnalyticsController::class, 'data'], $admin);

    // Security center (admin)
    $r->get('/security', [SecurityController::class, 'index'], $admin);
    $r->get('/security/data', [SecurityController::class, 'data'], $admin);
    $r->post('/security/logs/{id}/resolve', [SecurityController::class, 'resolve'], $adminWrite);
    $r->post('/security/block-ip', [SecurityController::class, 'blockIp'], $adminWrite);
    $r->post('/security/unblock-ip/{id}', [SecurityController::class, 'unblockIp'], $adminWrite);

    // Audit logs (admin)
    $r->get('/audit', [AuditLogController::class, 'index'], $admin);
    $r->get('/audit/data', [AuditLogController::class, 'data'], $admin);

    // Settings (admin)
    $r->get('/settings', [SettingController::class, 'index'], $admin);
    $r->post('/settings', [SettingController::class, 'update'], $adminWrite);

    // Backup & restore (admin)
    $r->get('/backups', [BackupController::class, 'index'], $admin);
    $r->post('/backups/create', [BackupController::class, 'create'], $adminWrite);
    $r->post('/backups/{id}/restore', [BackupController::class, 'restore'], $adminWrite);

    // Profile (all users)
    $r->get('/profile', [ProfileController::class, 'index'], $any);
    $r->post('/profile/update', [ProfileController::class, 'update'], $anyWrite);
    $r->post('/profile/password', [ProfileController::class, 'changePassword'], $anyWrite);

    // Notifications (all users)
    $r->get('/notifications/data', [NotificationController::class, 'data'], $any);
    $r->post('/notifications/read', [NotificationController::class, 'markRead'], $anyWrite);
};
