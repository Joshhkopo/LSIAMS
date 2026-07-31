<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Models\Student;
use App\Models\UnknownRfid;

final class RfidController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('rfid/index', ['pageTitle' => 'RFID Cards']);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $this->ok('OK', (new RfidCard())->listDetailed(trim((string) $request->query('search', '')), $limit, $offset));
    }

    public function unknown(Request $request): never
    {
        $this->ok('OK', ['rows' => (new UnknownRfid())->all([], 'last_seen DESC', 200)]);
    }

    public function history(Request $request): never
    {
        $uid = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) $request->param('uid')) ?? '');
        $this->ok('OK', ['rows' => (new RfidLog())->tapHistory($uid)]);
    }

    /** Register a card for a student (one active card per student). */
    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'student_id' => 'required|int',
            'uid'        => 'required|max:32|hex',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $studentId = (int) $request->input('student_id');
        $uid = strtoupper((string) $request->input('uid'));

        $cards = new RfidCard();
        if ((new Student())->find($studentId) === null) {
            $this->fail('Student not found.', 404);
        }
        if ($cards->uidExists($uid)) {
            $this->fail('Duplicate RFID: that UID is already registered.', 422);
        }
        if ($cards->activeCardForStudent($studentId) !== null) {
            $this->fail('Student already has an active card. Use Replace instead.', 422);
        }

        $id = $cards->create(['student_id' => $studentId, 'uid' => $uid, 'issue_date' => date('Y-m-d'), 'status' => 'active']);
        // If this UID was sitting in the unknown queue, mark it assigned.
        (new UnknownRfid())->markAssigned($uid);
        Audit::log('rfid_assigned', 'rfid', $id, null, ['student_id' => $studentId, 'uid' => $uid]);
        $this->ok('RFID assigned successfully.', ['id' => $id]);
    }

    /**
     * Replace a lost/damaged card. The old card is deactivated, the new one
     * links back via replacement_of so attendance history is preserved.
     */
    public function replace(Request $request): never
    {
        $oldId = (int) $request->param('id');
        $v = Validator::make($request->all(), ['uid' => 'required|max:32|hex']);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }

        $cards = new RfidCard();
        $old = $cards->find($oldId);
        if ($old === null) {
            $this->fail('Card not found.', 404);
        }
        $newUid = strtoupper((string) $request->input('uid'));
        if ($cards->uidExists($newUid)) {
            $this->fail('Duplicate RFID: that UID is already registered.', 422);
        }

        $newId = Database::transaction(function () use ($cards, $old, $oldId, $newUid) {
            $cards->update($oldId, ['status' => 'inactive']);
            return $cards->create([
                'student_id'     => (int) $old['student_id'],
                'uid'            => $newUid,
                'issue_date'     => date('Y-m-d'),
                'replacement_of' => $oldId,
                'status'         => 'active',
            ]);
        });

        Audit::log('rfid_replaced', 'rfid', $newId, ['old_uid' => $old['uid']], ['new_uid' => $newUid]);
        $this->ok('Card replaced. Attendance history is preserved.', ['id' => $newId]);
    }

    /** Activate / deactivate / mark lost / blacklist. */
    public function setStatus(Request $request): never
    {
        $id = (int) $request->param('id');
        $v = Validator::make($request->all(), ['status' => 'required|in:active,inactive,lost,blacklisted']);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }

        $cards = new RfidCard();
        $card = $cards->find($id);
        if ($card === null) {
            $this->fail('Card not found.', 404);
        }
        $status = (string) $request->input('status');
        if ($status === 'active' && $cards->activeCardForStudent((int) $card['student_id']) !== null && $card['status'] !== 'active') {
            $this->fail('Student already has another active card.', 422);
        }

        $cards->update($id, ['status' => $status]);
        Audit::log('rfid_status_changed', 'rfid', $id, ['status' => $card['status']], ['status' => $status]);
        $this->ok('Card status updated.');
    }

    public function setUnknownStatus(Request $request): never
    {
        $id = (int) $request->param('id');
        $v = Validator::make($request->all(), ['status' => 'required|in:ignored,blacklisted,new']);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }
        $unknown = new UnknownRfid();
        if ($unknown->find($id) === null) {
            $this->fail('Unknown card entry not found.', 404);
        }
        $unknown->update($id, ['status' => (string) $request->input('status')]);
        Audit::log('unknown_rfid_updated', 'rfid', $id, null, ['status' => $request->input('status')]);
        $this->ok('Unknown card updated.');
    }
}
