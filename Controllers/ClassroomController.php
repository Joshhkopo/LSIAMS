<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\Classroom;

final class ClassroomController extends Controller
{
    private const RULES = [
        'room_number' => 'required|max:30|alnumdash',
        'building'    => 'nullable|max:80',
        'floor'       => 'nullable|max:20',
        'capacity'    => 'required|int',
        'status'      => 'required|in:active,archived',
    ];

    public function index(Request $request): never
    {
        $this->view('classrooms/index', ['pageTitle' => 'Classrooms']);
    }

    public function data(Request $request): never
    {
        $this->ok('OK', ['rows' => (new Classroom())->listWithDevice()]);
    }

    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        $classrooms = new Classroom();
        if ($classrooms->roomNumberExists((string) $request->input('room_number'))) {
            $this->fail('Duplicate Room Number.', 422);
        }
        $id = $classrooms->create($v->validated());
        Audit::log('classroom_created', 'classrooms', $id, null, $v->validated());
        $this->ok('Classroom created successfully.', ['id' => $id]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $classrooms = new Classroom();
        $existing = $classrooms->find($id);
        if ($existing === null) {
            $this->fail('Classroom not found.', 404);
        }
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        if ($classrooms->roomNumberExists((string) $request->input('room_number'), $id)) {
            $this->fail('Duplicate Room Number.', 422);
        }
        $classrooms->update($id, $v->validated());
        Audit::log('classroom_updated', 'classrooms', $id, $existing, $v->validated());
        $this->ok('Classroom updated successfully.');
    }

    public function archive(Request $request): never
    {
        $id = (int) $request->param('id');
        $classrooms = new Classroom();
        if ($classrooms->find($id) === null) {
            $this->fail('Classroom not found.', 404);
        }
        $classrooms->update($id, ['status' => 'archived']);
        Audit::log('classroom_archived', 'classrooms', $id);
        $this->ok('Classroom archived.');
    }
}
