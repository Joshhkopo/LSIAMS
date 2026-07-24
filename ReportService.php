<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\GeneratedReport;
use App\Helpers\Auth;

/**
 * Report generation. CSV is generated natively; the same dataset feeds the
 * printable HTML view (browser print → PDF) and the Excel-compatible CSV.
 */
final class ReportService
{
    public function __construct(
        private readonly AttendanceRecord $records = new AttendanceRecord(),
        private readonly GeneratedReport $reports = new GeneratedReport(),
    ) {
    }

    /** Fetch the dataset for a report (capped to protect the server). */
    public function dataset(array $filters, int $cap = 20000): array
    {
        return $this->records->search($filters, $cap, 0)['rows'];
    }

    /**
     * Write a CSV to storage/reports and register it. Returns the file path.
     */
    public function generateCsv(array $filters, string $name): string
    {
        $rows = $this->dataset($filters);
        $dir = BASE_PATH . '/storage/reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) . '_' . date('Ymd_His') . '.csv';

        $fh = fopen($file, 'w');
        fputcsv($fh, ['Date', 'Time', 'Student Number', 'Student Name', 'Section', 'Subject',
                      'Room', 'Teacher', 'Status', 'RFID UID', 'Session']);
        foreach ($rows as $row) {
            fputcsv($fh, [
                substr((string) $row['recorded_at'], 0, 10),
                substr((string) $row['recorded_at'], 11, 8),
                $row['student_number'],
                $row['student_name'],
                $row['section_name'],
                $row['subject_name'],
                $row['room_number'],
                $row['teacher_name'],
                $row['status_name'],
                $row['rfid_uid'],
                $row['session_code'],
            ]);
        }
        fclose($fh);

        $this->reports->create([
            'name'         => $name,
            'report_type'  => 'attendance_csv',
            'filters'      => json_encode($filters),
            'file_path'    => str_replace(BASE_PATH . '/', '', $file),
            'generated_by' => Auth::id(),
        ]);
        \App\Helpers\Audit::log('report_generated', 'reports', null, null, ['name' => $name, 'filters' => $filters]);

        return $file;
    }

    /** Per-status totals for a report header/summary block. */
    public function summary(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $summary[$row['status_name']] = ($summary[$row['status_name']] ?? 0) + 1;
        }
        return $summary;
    }
}
