<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSchedule extends Model
{
    protected $fillable = [
        'sport_id',
        'coach_id',
        'training_date',
        'start_time',
        'end_time',
        'location',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function excuseRequests()
    {
        return $this->hasMany(ExcuseRequest::class);
    }
}