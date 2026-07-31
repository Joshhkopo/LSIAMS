<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\Device;
use App\Models\Fingerprint;
use App\Models\Teacher;

/**
 * Fingerprint enrollment management. The physical capture happens at the
 * classroom terminal (R307 stores the template in a sensor slot); this
 * module records which slot on which device belongs to which teacher.
 */
final class FingerprintController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('fingerprints/index', [
            'pageTitle' => 'Fingerprints',
            'teachers'  => (new Teacher())->all(['deleted_at' => null, 'status' => 'active'], 'last_name'),
            'devices'   => (new Device())->all(['status' => 'active'], 'device_code'),
        ]);
    }

    public function data(Request $request): never
    {
        $this->ok('OK', ['rows' => (new Fingerprint())->listDetailed()]);
    }

    public function enroll(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'teacher_id'         => 'required|int',
            'device_id'          => 'required|int',
            'sensor_template_id' => 'required|int',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $teacherId = (int) $request->input('teacher_id');
        $fingerprints = new Fingerprint();

        if ((new Teacher())->find($teacherId) === null) {
            $this->fail('Teacher not found.', 404);
        }
        $existing = $fingerprints->findByTeacher($teacherId);

        $data = [
            'teacher_id'         => $teacherId,
            'sensor_template_id' => (int) $request->input('sensor_template_id'),
            'enrolled_device_id' => (int) $request->input('device_id'),
            'enrolled_at'        => date('Y-m-d H:i:s'),
            'status'             => 'active',
        ];

        if ($existing !== null) {
            // Re-enrollment replaces the previous mapping (one per teacher).
            $fingerprints->update((int) $existing['id'], $data);
            Audit::log('fingerprint_reenrolled', 'fingerprints', $existing['id'], $existing, $data);
            $this->ok('Fingerprint re-enrolled successfully.');
        }

        $id = $fingerprints->create($data);
        Audit::log('fingerprint_enrolled', 'fingerprints', $id, null, $data);
        $this->ok('Fingerprint enrolled successfully.', ['id' => $id]);
    }

    public function setStatus(Request $request): never
    {
        $id = (int) $request->param('id');
        $v = Validator::make($request->all(), ['status' => 'required|in:active,disabled']);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }
        $fingerprints = new Fingerprint();
        $existing = $fingerprints->find($id);
        if ($existing === null) {
            $this->fail('Fingerprint record not found.', 404);
        }
        $fingerprints->update($id, ['status' => (string) $request->input('status')]);
        Audit::log('fingerprint_status_changed', 'fingerprints', $id,
            ['status' => $existing['status']], ['status' => $request->input('status')]);
        $this->ok('Fingerprint status updated.');
    }

    public function delete(Request $request): never
    {
        $id = (int) $request->param('id');
        $fingerprints = new Fingerprint();
        $existing = $fingerprints->find($id);
        if ($existing === null) {
            $this->fail('Fingerprint record not found.', 404);
        }
        $fingerprints->archive($id); // hard delete allowed: template mapping only
        Audit::log('fingerprint_deleted', 'fingerprints', $id, $existing, null);
        $this->ok('Fingerprint enrollment removed. Remember to clear the sensor slot on the device.');
    }
}
