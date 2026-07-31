<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Models\RfidCard;
use App\Models\UnknownRfid;

/**
 * RFID utility endpoint: lets an authenticated terminal look up a card
 * outside a session (registration/diagnostic mode). Attendance taps go to
 * /api/attendance/record instead.
 */
final class RfidApiController extends Controller
{
    /** POST /api/rfid/scan  Body: { rfid_uid } */
    public function scan(Request $request): never
    {
        $uid = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) $request->input('rfid_uid', '')) ?? '');
        if ($uid === '' || strlen($uid) > 32) {
            $this->fail('Invalid RFID.');
        }

        $card = (new RfidCard())->findByUid($uid);
        if ($card === null) {
            // Surface it in the admin "unknown cards" queue for assignment.
            (new UnknownRfid())->recordSighting($uid, (int) $request->device()['id']);
            $this->ok('Unknown card recorded for administrator review.', [
                'known' => false, 'uid' => $uid,
            ]);
        }

        $this->ok('Card recognized.', [
            'known'   => true,
            'uid'     => $uid,
            'student' => $card['student_name'],
            'status'  => $card['status'],
        ]);
    }
}
