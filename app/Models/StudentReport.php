<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'reporter_id',
        'reporter_role',
        'severity',
        'reason',
        'status',
        'resolution_note',
    ];

    protected $connection = 'tenant';
    
    
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Relationships for different reporter types
    public function teacherReporter()
    {
        return $this->belongsTo(Teacher::class, 'reporter_id');
    }

    public function accountantReporter()
    {
        return $this->belongsTo(Accountant::class, 'reporter_id');
    }

    public function adminReporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    // Helper to get the reporter instance
    public function getReporterNameAttribute()
    {
        // Using relationships. Make sure to eager load these in the controller 
        // using ->with(['teacherReporter', 'accountantReporter', 'adminReporter'])
        if ($this->reporter_role == 'teacher') {
            return $this->teacherReporter->name ?? 'Unknown Teacher';
        } elseif ($this->reporter_role == 'accountant') {
            return $this->accountantReporter->name ?? 'Unknown Accountant';
        } elseif ($this->reporter_role == 'admin') {
            return $this->adminReporter->name ?? 'Admin';
        }
        return 'Unknown';
    }
}
