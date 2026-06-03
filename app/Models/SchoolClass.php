<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class SchoolClass extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    
    protected $fillable = ['name', 'school_id'];

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'school_class_teacher');
    }
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'timetables', 'school_class_id', 'subject_id')->withPivot('teacher_id')->distinct();
    }
}