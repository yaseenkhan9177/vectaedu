<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class TeacherAttendance extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'school_id',
        'teacher_id',
        'attendance_date',
        'status',
        'remarks',
    ];
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}