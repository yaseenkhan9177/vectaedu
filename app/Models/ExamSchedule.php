<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamSchedule extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'school_id',
        'term_id',
        'class_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'room',
        'is_published',
        'section',
        'paper_type',
        'supervisor_id',
        'total_marks',
        'passing_marks',
        'description',
        'publish_status',
        'is_locked',
    ];
    protected $casts = [
        'exam_date' => 'date',
        'is_locked' => 'boolean',
    ];
    public function term()
    {
        return $this->belongsTo(ExamTerm::class);
    }
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
    public function supervisor()
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id');
    }
}