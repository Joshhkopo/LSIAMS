<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Schedule;

/**
 * Schedule business rules: overlap prevention for both the teacher and the
 * classroom, plus sane time-range validation.
 */
final class ScheduleService
{
    public function __construct(private readonly Schedule $schedules = new Schedule())
    {
    }

    /**
     * @return array{0: bool, 1: string} [ok, errorMessage]
     */
    public function validate(array $data, ?int $exceptId = null): array
    {
        if ($data['start_time'] >= $data['end_time']) {
            return [false, 'Start time must be earlier than end time.'];
        }

        $conflicts = $this->schedules->findConflicts(
            (int) $data['teacher_id'],
            (int) $data['classroom_id'],
            (int) $data['day_of_week'],
            $data['start_time'],
            $data['end_time'],
            $exceptId,
        );

        if ($conflicts !== []) {
            $c = $conflicts[0];
            $what = (int) $c['teacher_id'] === (int) $data['teacher_id'] ? 'Teacher' : 'Classroom';
            return [false, sprintf(
                '%s conflict with %s (%s, %s–%s in %s).',
                $what, $c['subject_code'], $c['teacher_name'],
                substr((string) $c['start_time'], 0, 5), substr((string) $c['end_time'], 0, 5), $c['room_number'],
            )];
        }
        return [true, ''];
    }
}
