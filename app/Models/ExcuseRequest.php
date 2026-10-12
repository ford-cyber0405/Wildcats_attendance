<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequest extends Model
{
    protected $fillable = [
        'student_id',
        'training_schedule_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
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

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos()
    {
        return $this->hasMany(ExcuseRequestPhoto::class);
    }
}