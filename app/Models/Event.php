<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'school_id',
        'title',
        'description',
        'event_date',
        'target_audience',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'target_audience' => 'array',
    ];

   protected $connection = 'tenant';
}
