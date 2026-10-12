<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\TrainingSchedule;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkAbsentees extends Command
{
    protected $signature = 'attendance:mark-absentees';

    protected $description = 'Mark students absent after each training schedule closes';

    public function handle(AttendanceCalculator $attendanceCalculator): int
    {
        $now = now();
        $markedCount = 0;

        TrainingSchedule::with('sport')
            ->where('status', 'training')
            ->whereDate('training_date', '<=', $now->toDateString())
            ->chunkById(100, function ($schedules) use ($attendanceCalculator, $now, &$markedCount): void {
                foreach ($schedules as $schedule) {
                    $closingTime = $attendanceCalculator->calculateTimes($schedule)['attendance_closing_time'];

                    if ($now->lt($closingTime)) {
                        continue;
                    }

                    $studentIds = DB::table('students')
                        ->where('sport_id', $schedule->sport_id)
                        ->whereNotExists(function ($query) use ($schedule) {
                            $query->selectRaw('1')
                                ->from('attendances')
                                ->whereColumn('attendances.student_id', 'students.id')
                                ->where('training_schedule_id', $schedule->id);
                        })
                        ->whereNotExists(function ($query) use ($schedule) {
                            $query->selectRaw('1')
                                ->from('special_training_requests')
                                ->whereColumn('special_training_requests.student_id', 'students.id')
                                ->where('status', 'approved')
                                ->whereDate('start_date', '<=', $schedule->training_date)
                                ->whereDate('end_date', '>=', $schedule->training_date);
                        })
                        ->pluck('id');

                    foreach ($studentIds as $studentId) {
                        $inserted = Attendance::query()->insertOrIgnore([
                            'student_id' => $studentId,
                            'training_schedule_id' => $schedule->id,
                            'time_in' => null,
                            'status' => 'absent',
                            'corrected_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $markedCount += $inserted;
                    }
                }
            });

        $this->info("Marked {$markedCount} student(s) absent.");

        return self::SUCCESS;
    }
}
