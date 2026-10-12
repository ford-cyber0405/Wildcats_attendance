<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'sport_id',
        'first_name',
        'middle_initial',
        'last_name',
        'course',
        'year_level',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function excuseRequests()
    {
        return $this->hasMany(ExcuseRequest::class);
    }

    public function specialTrainingRequests()
    {
        return $this->hasMany(SpecialTrainingRequest::class);
    }
}