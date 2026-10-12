<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequestPhoto extends Model
{
    protected $fillable = [
        'excuse_request_id',
        'photo_path',
    ];

    public function excuseRequest()
    {
        return $this->belongsTo(ExcuseRequest::class);
    }
}