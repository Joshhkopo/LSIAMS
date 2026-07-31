<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Helpers\RateLimiter;
use App\Services\AttendanceService;

/**
 * Teacher fingerprint verification. A successful verification is the ONLY
 * path that opens an attendance session — students never use fingerprints.
 */
final class FingerprintApiController extends Controller
{
    /**
     * POST /api/fingerprint/verify
     * Body: { fingerprint_id: <R307 template slot matched by the sensor> }
     */
    public function verify(Request $request): never
    {
        $device = $request->device();

        [$max, $window] = Config::get('security.rate_limits.fingerprint_verify');
        if (!RateLimiter::hit('fp:' . $device['device_code'], $max, $window)) {
            $this->fail('Too many fingerprint attempts. Device temporarily locked.', 429);
        }

        $slot = filter_var($request->input('fingerprint_id'), FILTER_VALIDATE_INT);
        if ($slot === false || $slot < 1) {
            $this->fail('Invalid fingerprint payload.');
        }

        $result = (new AttendanceService())->openSessionByFingerprint($device, (int) $slot, $request->ip());
        $this->json($result, $result['success'] ? 200 : 403);
    }
}
