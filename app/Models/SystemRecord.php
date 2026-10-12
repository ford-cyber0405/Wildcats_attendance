<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRecord extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'related_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}