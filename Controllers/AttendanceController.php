<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Helpers\Validator;
use App\Models\AttendanceModification;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\AttendanceService;

final class AttendanceController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('attendance/index', [
            'pageTitle'  => 'Attendance',
            'teachers'   => Auth::isAdmin() ? (new Teacher())->all(['deleted_at' => null], 'last_name') : [],
            'sections'   => (new Section())->listWithGrade(),
            'subjects'   => (new Subject())->all([], 'subject_code'),
            'classrooms' => (new Classroom())->all([], 'room_number'),
        ]);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $filters = [
            'search'       => trim((string) $request->query('search', '')),
            'teacher_id'   => $request->query('teacher_id'),
            'section_id'   => $request->query('section_id'),
            'subject_id'   => $request->query('subject_id'),
            'classroom_id' => $request->query('classroom_id'),
            'status'       => $request->query('status'),
            'date_from'    => $request->query('date_from'),
            'date_to'      => $request->query('date_to'),
        ];
        $this->applyTeacherScope($filters);
        $this->ok('OK', (new AttendanceRecord())->search($filters, $limit, $offset));
    }

    /** Incremental live feed: returns records newer than since_id. */
    public function live(Request $request): never
    {
        $filters = ['since_id' => (int) $request->query('since_id', 0), 'date_from' => date('Y-m-d')];
        $this->applyTeacherScope($filters);
        $result = (new AttendanceRecord())->search($filters, 50, 0);
        $this->ok('OK', ['rows' => $result['rows']]);
    }

    public function sessions(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $filters = [
            'date'   => $request->query('date'),
            'status' => $request->query('status'),
        ];
        $this->applyTeacherScope($filters);
        $this->ok('OK', (new AttendanceSession())->listDetailed($filters, $limit, $offset));
    }

    public function detail(Request $request): never
    {
        $id = (int) $request->param('id');
        $records = new AttendanceRecord();
        $record = $records->findDetailed($id);
        if ($record === null) {
            $this->fail('Attendance record not found.', 404);
        }
        if (!Auth::isAdmin()) {
            $teacher = (new Teacher())->findByUserId((int) Auth::id());
            if ($teacher === null || (int) $record['teacher_id'] !== (int) $teacher['id']) {
                $this->fail('You do not have permission to view this record.', 403);
            }
        }
        $this->ok('OK', [
            'record'  => $record,
            'history' => (new AttendanceModification())->historyFor($id),
        ]);
    }

    /** Administrator-only manual correction — requires a reason. */
    public function correct(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'status_id' => 'required|int|in:1,2,3,4,5,6',
            'reason'    => 'required|max:500|min:5',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        $result = (new AttendanceService())->correctAttendance(
            (int) $request->param('id'),
            (int) $request->input('status_id'),
            (string) $request->input('reason'),
            (int) Auth::id(),
        );
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Close a session from the web UI. Admins may close any session;
     * a teacher may close only their own.
     */
    public function closeSession(Request $request): never
    {
        $id = (int) $request->param('id');
        $session = (new AttendanceSession())->find($id);
        if ($session === null) {
            $this->fail('Session not found.', 404);
        }
        if (!Auth::isAdmin()) {
            $teacher = (new Teacher())->findByUserId((int) Auth::id());
            if ($teacher === null || (int) $session['teacher_id'] !== (int) $teacher['id']) {
                $this->fail('You can only close your own attendance sessions.', 403);
            }
        }
        $result = (new AttendanceService())->closeSession($id, Auth::isAdmin() ? 'admin' : 'teacher');
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /** Teachers are always restricted to their own classes. */
    private function applyTeacherScope(array &$filters): void
    {
        if (Auth::isAdmin()) {
            return;
        }
        $teacher = (new Teacher())->findByUserId((int) Auth::id());
        if ($teacher === null) {
            $this->fail('No teacher profile linked to this account.', 404);
        }
        $filters['teacher_id'] = (int) $teacher['id'];
    }
}
