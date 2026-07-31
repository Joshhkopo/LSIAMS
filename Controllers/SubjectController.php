<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\Subject;

final class SubjectController extends Controller
{
    private const RULES = [
        'subject_code' => 'required|max:30|alnumdash',
        'name'         => 'required|max:120',
        'description'  => 'nullable|max:255',
        'units'        => 'required|numeric',
        'status'       => 'required|in:active,archived',
    ];

    public function index(Request $request): never
    {
        $this->view('subjects/index', ['pageTitle' => 'Subjects']);
    }

    public function data(Request $request): never
    {
        $this->ok('OK', ['rows' => (new Subject())->listWithTeachers()]);
    }

    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        $subjects = new Subject();
        if ($subjects->codeExists((string) $request->input('subject_code'))) {
            $this->fail('Duplicate Subject Code.', 422);
        }
        $id = $subjects->create($v->validated());
        Audit::log('subject_created', 'subjects', $id, null, $v->validated());
        $this->ok('Subject created successfully.', ['id' => $id]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $subjects = new Subject();
        $existing = $subjects->find($id);
        if ($existing === null) {
            $this->fail('Subject not found.', 404);
        }
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        if ($subjects->codeExists((string) $request->input('subject_code'), $id)) {
            $this->fail('Duplicate Subject Code.', 422);
        }
        $subjects->update($id, $v->validated());
        Audit::log('subject_updated', 'subjects', $id, $existing, $v->validated());
        $this->ok('Subject updated successfully.');
    }

    public function archive(Request $request): never
    {
        $id = (int) $request->param('id');
        $subjects = new Subject();
        if ($subjects->find($id) === null) {
            $this->fail('Subject not found.', 404);
        }
        $subjects->update($id, ['status' => 'archived']);
        Audit::log('subject_archived', 'subjects', $id);
        $this->ok('Subject archived.');
    }
}
