<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Validator;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ScheduleService;

final class ScheduleController extends Controller
{
    private const RULES = [
        'teacher_id'   => 'required|int',
        'subject_id'   => 'required|int',
        'section_id'   => 'required|int',
        'classroom_id' => 'required|int',
        'day_of_week'  => 'required|int|in:1,2,3,4,5,6,7',
        'start_time'   => 'required|time',
        'end_time'     => 'required|time',
        'attendance_window_minutes' => 'required|int',
        'late_threshold_minutes'    => 'required|int',
    ];

    public function index(Request $request): never
    {
        $this->view('schedules/index', [
            'pageTitle'  => 'Schedules',
            'teachers'   => (new Teacher())->all(['deleted_at' => null, 'status' => 'active'], 'last_name'),
            'subjects'   => (new Subject())->all(['status' => 'active'], 'subject_code'),
            'sections'   => (new Section())->listWithGrade(),
            'classrooms' => (new Classroom())->all(['status' => 'active'], 'room_number'),
        ]);
    }

    public function data(Request $request): never
    {
        $filters = [
            'teacher_id'   => $request->query('teacher_id'),
            'classroom_id' => $request->query('classroom_id'),
            'section_id'   => $request->query('section_id'),
            'day_of_week'  => $request->query('day_of_week'),
        ];
        // Teachers only ever see their own schedule.
        if (!Auth::isAdmin()) {
            $teacher = (new Teacher())->findByUserId((int) Auth::id());
            if ($teacher === null) {
                $this->fail('No teacher profile linked to this account.', 404);
            }
            $filters['teacher_id'] = (int) $teacher['id'];
        }
        $this->ok('OK', ['rows' => (new Schedule())->listDetailed($filters)]);
    }

    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        $data = $v->validated();
        [$ok, $error] = (new ScheduleService())->validate($data);
        if (!$ok) {
            $this->fail($error, 422);
        }
        $id = (new Schedule())->create($data + ['status' => 'active']);
        Audit::log('schedule_created', 'schedules', $id, null, $data);
        $this->ok('Schedule created successfully.', ['id' => $id]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $schedules = new Schedule();
        $existing = $schedules->find($id);
        if ($existing === null) {
            $this->fail('Schedule not found.', 404);
        }
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        $data = $v->validated();
        [$ok, $error] = (new ScheduleService())->validate($data, $id);
        if (!$ok) {
            $this->fail($error, 422);
        }
        $schedules->update($id, $data);
        Audit::log('schedule_updated', 'schedules', $id, $existing, $data);
        $this->ok('Schedule updated successfully.');
    }

    public function archive(Request $request): never
    {
        $id = (int) $request->param('id');
        $schedules = new Schedule();
        if ($schedules->find($id) === null) {
            $this->fail('Schedule not found.', 404);
        }
        $schedules->update($id, ['status' => 'archived']);
        Audit::log('schedule_archived', 'schedules', $id);
        $this->ok('Schedule archived.');
    }
}
