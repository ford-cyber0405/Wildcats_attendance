<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    protected $fillable = [
        'user_id',
        'sport_id',
        'first_name',
        'middle_initial',
        'last_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function trainingSchedules()
    {
        return $this->hasMany(TrainingSchedule::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}