<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id',
        'training_schedule_id',
        'time_in',
        'status',
        'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'time_in' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function trainingSchedule()
    {
        return $this->belongsTo(TrainingSchedule::class);
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}