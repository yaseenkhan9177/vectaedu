<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'host_id',
        'host_type',
        'topic',
        'description',
        'start_time',
        'duration',
        'zoom_meeting_id',
        'zoom_start_url',
        'zoom_join_url',
        'password',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];
    
    protected $connection = 'tenant';

    public function participants()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_meeting_participants', 'meeting_id', 'teacher_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function school()
    {
        return $this->belongsTo(User::class, 'school_id');
    }
}
