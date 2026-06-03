<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\SchoolScope;

class ExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'term_id',
        'class_id',
        'student_id',
        'subject_id',
        'obtained_marks',
        'total_marks',
        'grade',
    ];

  protected $connection = 'tenant';

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(ExamTerm::class);
    }

    public function examTerm()
    {
        return $this->belongsTo(ExamTerm::class, 'term_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    // Helper to calculate grade (can be moved to a service)
    public static function calculateGrade($obtained, $total)
    {
        if ($total == 0) return 'N/A';
        $percentage = ($obtained / $total) * 100;

        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 60) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }
}
