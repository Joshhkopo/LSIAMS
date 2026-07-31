<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Models\Device;
use App\Models\Teacher;
use App\Services\DashboardService;
use App\Services\DeviceService;

final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        if (Auth::isAdmin()) {
            $this->view('dashboard/admin', ['pageTitle' => 'Dashboard']);
        }
        $this->view('dashboard/teacher', ['pageTitle' => 'My Dashboard']);
    }

    /** JSON payload polled by the dashboard (live cards + charts). */
    public function data(Request $request): never
    {
        // Keep device state fresh even without a cron on the server.
        (new DeviceService())->sweepOffline();
        (new \App\Services\AttendanceService())->autoCloseExpired();

        $service = new DashboardService();
        if (Auth::isAdmin()) {
            $this->ok('OK', [
                'summary' => $service->adminSummary(),
                'devices' => (new Device())->listWithHealth(),
            ]);
        }

        $teacher = (new Teacher())->findByUserId((int) Auth::id());
        if ($teacher === null) {
            $this->fail('No teacher profile is linked to this account.', 404);
        }
        $this->ok('OK', ['summary' => $service->teacherSummary((int) $teacher['id'])]);
    }
}
