<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LeaveApplication extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'school_id',
        'student_id',
        'parent_id',
        'type',
        'from_date',
        'to_date',
        'reason',
        'status',
    ];
    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function parent()
    {
        return $this->belongsTo(SchoolParent::class);
    }
}