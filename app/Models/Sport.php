<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    protected $fillable = [
        'name',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function coaches()
    {
        return $this->hasMany(Coach::class);
    }

    public function trainingSchedules()
    {
        return $this->hasMany(TrainingSchedule::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}