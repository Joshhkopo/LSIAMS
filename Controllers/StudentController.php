<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Upload;
use App\Helpers\Validator;
use App\Models\GradeLevel;
use App\Models\RfidCard;
use App\Models\Section;
use App\Models\Student;

final class StudentController extends Controller
{
    private const RULES = [
        'student_number'   => 'required|max:30|alnumdash',
        'first_name'       => 'required|max:80|name',
        'middle_name'      => 'nullable|max:80|name',
        'last_name'        => 'required|max:80|name',
        'gender'           => 'required|in:male,female',
        'birthdate'        => 'nullable|date',
        'address'          => 'nullable|max:255',
        'email'            => 'nullable|email|max:150',
        'guardian_name'    => 'nullable|max:150|name',
        'guardian_contact' => 'nullable|phone',
        'section_id'       => 'nullable|int',
        'grade_level_id'   => 'nullable|int',
        'status'           => 'required|in:active,inactive,archived',
    ];

    public function index(Request $request): never
    {
        $this->view('students/index', [
            'pageTitle' => 'Students',
            'sections'  => (new Section())->listWithGrade(),
            'grades'    => (new GradeLevel())->all(['status' => 'active'], 'sort_order'),
        ]);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $result = (new Student())->search(
            trim((string) $request->query('search', '')),
            [
                'status'         => $request->query('status'),
                'section_id'     => $request->query('section_id'),
                'grade_level_id' => $request->query('grade_level_id'),
            ],
            $limit,
            $offset,
        );
        $this->ok('OK', $result);
    }

    public function detail(Request $request): never
    {
        $students = new Student();
        $student = $students->find((int) $request->param('id'));
        if ($student === null) {
            $this->fail('Student not found.', 404);
        }
        $this->ok('OK', [
            'student'    => $student,
            'rfid'       => (new RfidCard())->activeCardForStudent((int) $student['id']),
            'attendance' => $students->attendanceSummary((int) $student['id']),
        ]);
    }

    public function store(Request $request): never
    {
        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $students = new Student();
        if ($students->numberExists((string) $request->input('student_number'))) {
            $this->fail('Duplicate Student Number.', 422);
        }

        $data = $v->validated();
        $rfidUid = strtoupper(trim((string) $request->input('rfid_uid', '')));

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path] = Upload::store($_FILES['photo'], 'students');
            if (!$ok) {
                $this->fail($path, 422);
            }
            $data['photo'] = $path;
        }

        $id = Database::transaction(function () use ($students, $data, $rfidUid) {
            $id = $students->create($data);
            if ($rfidUid !== '') {
                $cards = new RfidCard();
                if ($cards->uidExists($rfidUid)) {
                    throw new \RuntimeException('Duplicate RFID: that card is already assigned.');
                }
                $cards->create([
                    'student_id' => $id, 'uid' => $rfidUid,
                    'issue_date' => date('Y-m-d'), 'status' => 'active',
                ]);
            }
            return $id;
        });

        Audit::log('student_created', 'students', $id, null, $data);
        $this->ok('Student registered successfully.', ['id' => $id]);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $students = new Student();
        $existing = $students->find($id);
        if ($existing === null) {
            $this->fail('Student not found.', 404);
        }

        $v = Validator::make($request->all(), self::RULES);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        if ($students->numberExists((string) $request->input('student_number'), $id)) {
            $this->fail('Duplicate Student Number.', 422);
        }

        $data = $v->validated();
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path] = Upload::store($_FILES['photo'], 'students');
            if (!$ok) {
                $this->fail($path, 422);
            }
            $data['photo'] = $path;
        }

        $students->update($id, $data);
        Audit::log('student_updated', 'students', $id, $existing, $data);
        $this->ok('Student updated successfully.');
    }

    /** Soft delete only — attendance history is preserved by design. */
    public function archive(Request $request): never
    {
        $id = (int) $request->param('id');
        $students = new Student();
        $existing = $students->find($id);
        if ($existing === null) {
            $this->fail('Student not found.', 404);
        }
        $students->archive($id);
        Audit::log('student_archived', 'students', $id, ['status' => $existing['status']], ['status' => 'archived']);
        $this->ok('Student archived. Attendance history is preserved.');
    }

    /** CSV import with per-row validation and full rollback on failure. */
    public function import(Request $request): never
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->fail('Please choose a CSV file to import.', 422);
        }
        [$ok, $stored] = Upload::store($_FILES['file'], 'imports');
        if (!$ok) {
            $this->fail($stored, 422);
        }

        $path = BASE_PATH . '/public/' . $stored;
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        $expected = ['student_number', 'first_name', 'middle_name', 'last_name', 'gender',
                     'birthdate', 'guardian_name', 'guardian_contact', 'rfid_uid'];
        if ($header === false || array_map('strtolower', array_map('trim', $header)) !== $expected) {
            fclose($fh);
            $this->fail('Invalid CSV header. Expected: ' . implode(',', $expected), 422);
        }

        $rows = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            $rows[] = ['line' => $line, 'data' => array_combine($expected, array_pad($row, 9, null))];
        }
        fclose($fh);

        $students = new Student();
        $cards = new RfidCard();
        try {
            $imported = Database::transaction(function () use ($rows, $students, $cards) {
                $count = 0;
                foreach ($rows as $entry) {
                    $d = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $entry['data']);
                    $v = Validator::make($d, [
                        'student_number' => 'required|max:30|alnumdash',
                        'first_name'     => 'required|max:80|name',
                        'last_name'      => 'required|max:80|name',
                        'gender'         => 'required|in:male,female',
                        'birthdate'      => 'nullable|date',
                    ]);
                    if ($v->fails()) {
                        throw new \RuntimeException("Row {$entry['line']}: " . $v->firstError());
                    }
                    if ($students->numberExists($d['student_number'])) {
                        throw new \RuntimeException("Row {$entry['line']}: duplicate student number {$d['student_number']}.");
                    }
                    $id = $students->create([
                        'student_number' => $d['student_number'],
                        'first_name' => $d['first_name'], 'middle_name' => $d['middle_name'] ?: null,
                        'last_name' => $d['last_name'], 'gender' => strtolower($d['gender']),
                        'birthdate' => $d['birthdate'] ?: null,
                        'guardian_name' => $d['guardian_name'] ?: null,
                        'guardian_contact' => $d['guardian_contact'] ?: null,
                        'status' => 'active',
                    ]);
                    $uid = strtoupper((string) ($d['rfid_uid'] ?? ''));
                    if ($uid !== '') {
                        if ($cards->uidExists($uid)) {
                            throw new \RuntimeException("Row {$entry['line']}: duplicate RFID {$uid}.");
                        }
                        $cards->create(['student_id' => $id, 'uid' => $uid, 'issue_date' => date('Y-m-d'), 'status' => 'active']);
                    }
                    $count++;
                }
                return $count;
            });
        } catch (\RuntimeException $e) {
            $this->fail('Import rolled back — ' . $e->getMessage(), 422);
        }

        Audit::log('students_imported', 'students', null, null, ['count' => $imported]);
        $this->ok("Imported {$imported} students successfully.");
    }

    /** CSV export of the current filter set. */
    public function export(Request $request): never
    {
        $result = (new Student())->search(
            trim((string) $request->query('search', '')),
            [
                'status'         => $request->query('status'),
                'section_id'     => $request->query('section_id'),
                'grade_level_id' => $request->query('grade_level_id'),
            ],
            20000,
            0,
        );

        Audit::log('students_exported', 'students', null, null, ['count' => count($result['rows'])]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student Number', 'First Name', 'Middle Name', 'Last Name', 'Gender',
                       'Grade', 'Section', 'RFID UID', 'Guardian', 'Guardian Contact', 'Status']);
        foreach ($result['rows'] as $row) {
            fputcsv($out, [
                $row['student_number'], $row['first_name'], $row['middle_name'], $row['last_name'],
                $row['gender'], $row['grade_name'], $row['section_name'], $row['rfid_uid'],
                $row['guardian_name'], $row['guardian_contact'], $row['status'],
            ]);
        }
        fclose($out);
        exit;
    }
}
