<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Models\Setting;

final class SettingController extends Controller
{
    /** Editable settings and their validation constraints. */
    private const EDITABLE = [
        'school_name'                      => ['max' => 150],
        'attendance_window_minutes'        => ['int' => [1, 120]],
        'late_threshold_minutes'           => ['int' => [1, 120]],
        'absent_cutoff_minutes'            => ['int' => [1, 240]],
        'heartbeat_interval_seconds'       => ['int' => [10, 300]],
        'heartbeat_offline_after_seconds'  => ['int' => [30, 900]],
        'api_timestamp_window_seconds'     => ['int' => [5, 300]],
        'fingerprint_max_failures'         => ['int' => [3, 20]],
        'timezone'                         => ['max' => 60],
        'backup_schedule'                  => ['in' => ['daily', 'weekly', 'manual']],
        'theme'                            => ['in' => ['light', 'dark']],
    ];

    public function index(Request $request): never
    {
        $rows = Database::pdo()->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll();
        $this->view('settings/index', ['pageTitle' => 'System Settings', 'settings' => $rows]);
    }

    /**
     * Sensitive settings require the administrator to re-enter their
     * password (re-authentication rule from the security spec).
     */
    public function update(Request $request): never
    {
        $password = (string) $request->input('confirm_password', '');
        $user = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $user->execute([Auth::id()]);
        $hash = $user->fetchColumn();
        if ($hash === false || !password_verify($password, (string) $hash)) {
            $this->fail('Password confirmation failed. Settings were not changed.', 403);
        }

        $settings = new Setting();
        $changed = [];
        foreach (self::EDITABLE as $key => $rule) {
            $value = $request->input($key);
            if ($value === null) {
                continue;
            }
            $value = trim((string) $value);
            if (isset($rule['int'])) {
                [$min, $max] = $rule['int'];
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $min || (int) $value > $max) {
                    $this->fail("'{$key}' must be an integer between {$min} and {$max}.", 422);
                }
            }
            if (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
                $this->fail("'{$key}' exceeds the maximum length.", 422);
            }
            if (isset($rule['in']) && !in_array($value, $rule['in'], true)) {
                $this->fail("'{$key}' has an invalid value.", 422);
            }
            if ($settings->value($key) !== $value) {
                $changed[$key] = ['old' => $settings->value($key), 'new' => $value];
                $settings->set($key, $value, Auth::id());
            }
        }

        if ($changed !== []) {
            Audit::log('settings_updated', 'settings', null,
                array_map(static fn($c) => $c['old'], $changed),
                array_map(static fn($c) => $c['new'], $changed));
        }
        $this->ok($changed === [] ? 'No changes detected.' : 'Settings updated successfully.');
    }
}
