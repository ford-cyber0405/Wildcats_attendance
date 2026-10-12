<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'email',
        'student_id_number',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function coach()
    {
        return $this->hasOne(Coach::class);
    }

    public function superAdmin()
    {
        return $this->hasOne(SuperAdmin::class);
    }

    public function correctedAttendances()
    {
        return $this->hasMany(Attendance::class, 'corrected_by');
    }

    public function reviewedExcuseRequests()
    {
        return $this->hasMany(ExcuseRequest::class, 'reviewed_by');
    }

    public function reviewedSpecialTrainingRequests()
    {
        return $this->hasMany(SpecialTrainingRequest::class, 'reviewed_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function systemRecords()
    {
        return $this->hasMany(SystemRecord::class);
    }
}
