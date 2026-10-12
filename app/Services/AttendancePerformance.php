<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendancePerformance
{
    public function counts(Student $student, int $year, int $month): array
    {
        $rows = $this->attendanceForMonth($student, $year, $month);

        return [
            'present' => $rows->where('status', 'present')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'excuse' => $rows->where('status', 'excused')->count(),
            'special_training' => 0,
        ];
    }

    public function color(Student $student, int $year, int $month): string
    {
        $counts = $this->counts($student, $year, $month);

        return $counts['absent'] >= 2 ? 'red' : ($counts['late'] >= 3 ? 'yellow' : 'green');
    }

    public function summary(Student $student, int $year, int $month): array
    {
        $user = $student->user;
        $sport = $student->sport;

        return [
            'id' => $student->id,
            'student_id' => $user->student_id_number,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_initial,
            'last_name' => $student->last_name,
            'year_level' => $student->year_level,
            'course' => $student->course,
            'email' => $user->email,
            'sport' => strtolower($sport->name),
            'counts' => $this->counts($student, $year, $month),
            'color' => $this->color($student, $year, $month),
        ];
    }

    public function students(?int $sportId, int $year, int $month): Collection
    {
        return Student::with(['user', 'sport'])
            ->when($sportId, fn ($query) => $query->where('sport_id', $sportId))
            ->get()
            ->map(fn (Student $student) => $this->summary($student, $year, $month));
    }

    public function calendar(Student $student, int $year, int $month): array
    {
        $rows = $this->attendanceForMonth($student, $year, $month)->keyBy(
            fn (Attendance $attendance) => $attendance->trainingSchedule->training_date->toDateString()
        );
        $days = Carbon::create($year, $month, 1)->daysInMonth;
        $calendar = [];

        for ($day = 1; $day <= $days; $day++) {
            $date = Carbon::create($year, $month, $day)->toDateString();
            $attendance = $rows->get($date);
            $calendar[$date] = [
                'status' => $attendance?->status ?? 'none',
                'time' => $attendance?->time_in?->format('H:i') ?? '--',
            ];
        }

        return $calendar;
    }

    private function attendanceForMonth(Student $student, int $year, int $month): Collection
    {
        return Attendance::with('trainingSchedule')
            ->where('student_id', $student->id)
            ->whereHas('trainingSchedule', function ($query) use ($year, $month) {
                $query->whereYear('training_date', $year)->whereMonth('training_date', $month);
            })
            ->get();
    }
}
