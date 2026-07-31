<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\ApiKey;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Services\DeviceService;

final class DeviceController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('devices/index', [
            'pageTitle'  => 'IoT Devices',
            'classrooms' => (new Classroom())->all(['status' => 'active'], 'room_number'),
        ]);
    }

    public function data(Request $request): never
    {
        (new DeviceService())->sweepOffline();
        $this->ok('OK', ['rows' => (new Device())->listWithHealth()]);
    }

    public function logs(Request $request): never
    {
        $this->ok('OK', ['rows' => (new DeviceLog())->recent((int) $request->param('id'))]);
    }

    /**
     * Register a device. The generated API key is returned ONCE and only its
     * hash is stored — copy it into the firmware immediately.
     */
    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'device_code'  => 'required|max:30|alnumdash',
            'device_name'  => 'required|max:100',
            'mac_address'  => 'required|mac',
            'classroom_id' => 'nullable|int',
            'firmware_version' => 'nullable|max:20',
            'wifi_ssid'    => 'nullable|max:64',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $devices = new Device();
        $mac = strtoupper(str_replace('-', ':', (string) $request->input('mac_address')));
        if ($devices->codeExists((string) $request->input('device_code'))) {
            $this->fail('Duplicate Device ID.', 422);
        }
        if ($devices->macExists($mac)) {
            $this->fail('Duplicate MAC address.', 422);
        }
        $classroomId = $request->input('classroom_id') !== null && $request->input('classroom_id') !== ''
            ? (int) $request->input('classroom_id') : null;
        if ($classroomId !== null && $devices->classroomTaken($classroomId)) {
            $this->fail('That classroom already has a device assigned.', 422);
        }

        $data = $v->validated();
        $data['mac_address'] = $mac;
        $data['classroom_id'] = $classroomId;

        [$deviceId, $plainKey] = Database::transaction(function () use ($devices, $data) {
            $id = $devices->create($data + ['status' => 'active']);
            $key = (new ApiKey())->issueFor($id);
            return [$id, $key];
        });

        Audit::log('device_registered', 'devices', $deviceId, null,
            ['device_code' => $data['device_code'], 'mac' => $mac]);
        $this->ok('Device registered. Copy the API key now — it will not be shown again.', [
            'id' => $deviceId,
            'api_key' => $plainKey,
        ]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $devices = new Device();
        $existing = $devices->find($id);
        if ($existing === null) {
            $this->fail('Device not found.', 404);
        }

        $v = Validator::make($request->all(), [
            'device_name'  => 'required|max:100',
            'classroom_id' => 'nullable|int',
            'wifi_ssid'    => 'nullable|max:64',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }
        $classroomId = $request->input('classroom_id') !== null && $request->input('classroom_id') !== ''
            ? (int) $request->input('classroom_id') : null;
        if ($classroomId !== null && $devices->classroomTaken($classroomId, $id)) {
            $this->fail('That classroom already has a device assigned.', 422);
        }

        $data = $v->validated();
        $data['classroom_id'] = $classroomId;
        $devices->update($id, $data);
        Audit::log('device_updated', 'devices', $id, $existing, $data);
        $this->ok('Device updated successfully.');
    }

    /** Rotate the API key (old key revoked immediately). */
    public function generateApiKey(Request $request): never
    {
        $id = (int) $request->param('id');
        $device = (new Device())->find($id);
        if ($device === null) {
            $this->fail('Device not found.', 404);
        }
        $plainKey = (new ApiKey())->issueFor($id);
        Audit::log('device_api_key_rotated', 'devices', $id);
        (new DeviceLog())->create(['device_id' => $id, 'event' => 'config', 'details' => 'API key rotated by administrator']);
        $this->ok('New API key generated. The previous key is now revoked.', ['api_key' => $plainKey]);
    }

    /** Enable / disable / archive a device. Disabling revokes API access. */
    public function setStatus(Request $request): never
    {
        $id = (int) $request->param('id');
        $v = Validator::make($request->all(), ['status' => 'required|in:active,disabled,archived']);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }
        $devices = new Device();
        $existing = $devices->find($id);
        if ($existing === null) {
            $this->fail('Device not found.', 404);
        }
        $status = (string) $request->input('status');
        $devices->update($id, ['status' => $status, 'is_online' => 0]);
        if ($status !== 'active') {
            (new ApiKey())->revokeFor($id);
        }
        Audit::log('device_status_changed', 'devices', $id, ['status' => $existing['status']], ['status' => $status]);
        $this->ok($status === 'active'
            ? 'Device enabled. Generate a new API key before reconnecting it.'
            : 'Device disabled and its API key revoked.');
    }
}
