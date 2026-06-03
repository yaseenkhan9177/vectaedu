<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'rules',
    ];

   protected $connection = 'tenant';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function papers()
    {
        return $this->hasMany(ExamPaper::class, 'term_id');
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class, 'term_id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_term_classes', 'exam_term_id', 'school_class_id');
    }
}
