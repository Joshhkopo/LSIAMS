<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Auth;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('reports/index', [
            'pageTitle' => 'Reports',
            'teachers'  => Auth::isAdmin() ? (new Teacher())->all(['deleted_at' => null], 'last_name') : [],
            'sections'  => (new Section())->listWithGrade(),
            'subjects'  => (new Subject())->all([], 'subject_code'),
        ]);
    }

    public function data(Request $request): never
    {
        $filters = $this->filters($request);
        $service = new ReportService();
        $rows = $service->dataset($filters, 1000);
        $this->ok('OK', ['rows' => $rows, 'summary' => $service->summary($rows)]);
    }

    public function export(Request $request): never
    {
        $filters = $this->filters($request);
        $file = (new ReportService())->generateCsv($filters, 'attendance_report');
        Response::download($file, basename($file), 'text/csv');
    }

    /** Printable report (browser print → PDF), with school header. */
    public function printable(Request $request): never
    {
        $filters = $this->filters($request);
        $service = new ReportService();
        $rows = $service->dataset($filters, 5000);
        $this->view('reports/print', [
            'rows'       => $rows,
            'summary'    => $service->summary($rows),
            'filters'    => $filters,
            'schoolName' => (new Setting())->value('school_name', 'L-SIAMS'),
        ], ''); // no layout — standalone printable page
    }

    private function filters(Request $request): array
    {
        $filters = [
            'date_from'  => $request->query('date_from', date('Y-m-d')),
            'date_to'    => $request->query('date_to', date('Y-m-d')),
            'section_id' => $request->query('section_id'),
            'subject_id' => $request->query('subject_id'),
            'status'     => $request->query('status'),
            'teacher_id' => $request->query('teacher_id'),
        ];
        if (!Auth::isAdmin()) {
            $teacher = (new Teacher())->findByUserId((int) Auth::id());
            if ($teacher === null) {
                $this->fail('No teacher profile linked to this account.', 404);
            }
            $filters['teacher_id'] = (int) $teacher['id'];
        }
        return $filters;
    }
}
