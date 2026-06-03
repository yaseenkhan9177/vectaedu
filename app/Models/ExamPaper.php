<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamPaper extends Model
{
    use HasFactory;

    protected $fillable = [
        'term_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'file_path',
        'status',
        'submitted_at',
        'school_id',
    ];

   protected $connection = 'tenant';

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function term()
    {
        return $this->belongsTo(ExamTerm::class, 'term_id');
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
