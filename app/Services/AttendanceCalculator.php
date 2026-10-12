<?php

namespace App\Services;

use App\Models\TrainingSchedule;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * @return array{attendance_opening_time: Carbon, present_until: Carbon, attendance_closing_time: Carbon}
     */
    public function calculateTimes(TrainingSchedule $schedule): array
    {
        $date = $schedule->training_date instanceof Carbon
            ? $schedule->training_date->format('Y-m-d')
            : Carbon::parse($schedule->training_date)->format('Y-m-d');

        $startTime = $this->scheduleDateTime($date, $schedule->start_time);
        $endTime = $this->scheduleDateTime($date, $schedule->end_time);

        return [
            'attendance_opening_time' => $startTime->copy()->subHour(),
            'present_until' => $startTime->copy()->addMinutes(30),
            'attendance_closing_time' => $endTime->copy()->addMinutes(30),
        ];
    }

    public function determineStatus(TrainingSchedule $schedule, Carbon $checkInTime): string
    {
        $times = $this->calculateTimes($schedule);

        if ($checkInTime->lt($times['attendance_opening_time'])
            || $checkInTime->gt($times['attendance_closing_time'])) {
            return 'closed';
        }

        if ($checkInTime->lte($times['present_until'])) {
            return 'present';
        }

        return 'late';
    }

    private function scheduleDateTime(string $date, mixed $time): Carbon
    {
        $timeValue = $time instanceof Carbon ? $time->format('H:i:s') : (string) $time;

        return Carbon::parse("{$date} {$timeValue}");
    }
}