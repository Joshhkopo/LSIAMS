<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Notifier;
use App\Helpers\Upload;
use App\Helpers\Validator;
use App\Models\Teacher;
use App\Models\User;

final class TeacherController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('teachers/index', [
            'pageTitle' => 'Teachers',
            'subjects'  => (new \App\Models\Subject())->all(['status' => 'active'], 'subject_code'),
        ]);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $this->ok('OK', (new Teacher())->listWithStatus($limit, $offset, trim((string) $request->query('search', ''))));
    }

    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'employee_number' => 'required|max:30|alnumdash',
            'first_name'      => 'required|max:80|name',
            'middle_name'     => 'nullable|max:80|name',
            'last_name'       => 'required|max:80|name',
            'email'           => 'required|email|max:150',
            'phone'           => 'nullable|phone',
            'department'      => 'nullable|max:100',
            'username'        => 'required|max:60|alnumdash',
            'password'        => 'required|password',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $teachers = new Teacher();
        $users = new User();
        if ($teachers->employeeNumberExists((string) $request->input('employee_number'))) {
            $this->fail('Duplicate Employee Number.', 422);
        }
        if ($users->usernameExists((string) $request->input('username'))) {
            $this->fail('Username already taken.', 422);
        }
        if ($users->emailExists((string) $request->input('email'))) {
            $this->fail('Email already registered.', 422);
        }

        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path] = Upload::store($_FILES['photo'], 'teachers');
            if (!$ok) {
                $this->fail($path, 422);
            }
            $photo = $path;
        }

        $data = $v->validated();
        $teacherId = Database::transaction(function () use ($users, $teachers, $data, $photo) {
            $userId = $users->create([
                'role_id'  => 2, // Teacher
                'username' => $data['username'],
                'email'    => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'status'   => 'active',
                'must_change_password' => 1,
                'photo'    => $photo,
            ]);
            return $teachers->create([
                'employee_number' => $data['employee_number'],
                'user_id'     => $userId,
                'department'  => $data['department'] ?? null,
                'first_name'  => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name'   => $data['last_name'],
                'email'       => $data['email'],
                'phone'       => $data['phone'] ?? null,
                'photo'       => $photo,
                'status'      => 'active',
            ]);
        });

        // Optional subject assignments.
        $subjectIds = $request->input('subject_ids');
        if (is_array($subjectIds)) {
            $this->assignSubjects($teacherId, $subjectIds);
        }

        Audit::log('teacher_created', 'teachers', $teacherId, null,
            ['employee_number' => $data['employee_number'], 'name' => $data['first_name'] . ' ' . $data['last_name']]);
        $this->ok('Teacher registered successfully. Fingerprint enrollment is pending.', ['id' => $teacherId]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $teachers = new Teacher();
        $existing = $teachers->find($id);
        if ($existing === null) {
            $this->fail('Teacher not found.', 404);
        }

        $v = Validator::make($request->all(), [
            'employee_number' => 'required|max:30|alnumdash',
            'first_name'      => 'required|max:80|name',
            'middle_name'     => 'nullable|max:80|name',
            'last_name'       => 'required|max:80|name',
            'email'           => 'required|email|max:150',
            'phone'           => 'nullable|phone',
            'department'      => 'nullable|max:100',
            'status'          => 'required|in:active,inactive,archived',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        if ($teachers->employeeNumberExists((string) $request->input('employee_number'), $id)) {
            $this->fail('Duplicate Employee Number.', 422);
        }

        $data = $v->validated();
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path] = Upload::store($_FILES['photo'], 'teachers');
            if (!$ok) {
                $this->fail($path, 422);
            }
            $data['photo'] = $path;
        }
        $teachers->update($id, $data);

        $subjectIds = $request->input('subject_ids');
        if (is_array($subjectIds)) {
            $this->assignSubjects($id, $subjectIds);
        }

        Audit::log('teacher_updated', 'teachers', $id, $existing, $data);
        $this->ok('Teacher updated successfully.');
    }

    public function archive(Request $request): never
    {
        $id = (int) $request->param('id');
        $teachers = new Teacher();
        $existing = $teachers->find($id);
        if ($existing === null) {
            $this->fail('Teacher not found.', 404);
        }
        Database::transaction(function () use ($teachers, $existing, $id) {
            $teachers->archive($id);
            (new User())->update((int) $existing['user_id'], ['status' => 'inactive']);
        });
        Audit::log('teacher_archived', 'teachers', $id);
        $this->ok('Teacher archived and account deactivated.');
    }

    /** Administrator resets a teacher password; forced change at next login. */
    public function resetPassword(Request $request): never
    {
        $id = (int) $request->param('id');
        $teacher = (new Teacher())->find($id);
        if ($teacher === null) {
            $this->fail('Teacher not found.', 404);
        }

        $temporary = 'Tmp-' . bin2hex(random_bytes(6)) . '!9';
        $users = new User();
        $users->setPassword((int) $teacher['user_id'], $temporary);
        $users->update((int) $teacher['user_id'], ['must_change_password' => 1, 'status' => 'active', 'failed_attempts' => 0]);

        Audit::log('teacher_password_reset', 'teachers', $id);
        Notifier::notify((int) $teacher['user_id'], 'Password reset',
            'An administrator reset your password. Change it after your next login.', 'high');
        $this->ok('Password reset. Share the temporary password securely.', ['temporary_password' => $temporary]);
    }

    /** @param array<int|string> $subjectIds */
    private function assignSubjects(int $teacherId, array $subjectIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM teacher_subjects WHERE teacher_id = ?')->execute([$teacherId]);
        $stmt = $pdo->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)');
        foreach ($subjectIds as $subjectId) {
            if (filter_var($subjectId, FILTER_VALIDATE_INT) !== false) {
                $stmt->execute([$teacherId, (int) $subjectId]);
            }
        }
    }
}
