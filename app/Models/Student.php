<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class Student extends Authenticatable
{
    use HasFactory, Notifiable;
    
    protected $connection = 'tenant';
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'parent_phone',
        'parent_name',
        'class_id',
        'profile_image',
        'status',
        'roll_number',
        'school_id',
        'transport_fee',
        'gender',
        'dob',
        'parent_id',
        'family_id',
        'plain_password',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }
    public function studentFees()
    {
        return $this->hasMany(StudentFee::class);
    }
    public function currentFeeBalance()
    {
        $totalFees = \App\Models\StudentFee::where('student_id', $this->id)
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount + late_fee - discount) as gross')
            ->value('gross') ?? 0;
        $totalPaid = \App\Models\FeePayment::whereIn('student_fee_id', function ($q) {
            $q->select('id')->from('student_fees')->where('student_id', $this->id);
        })->sum('amount_paid') ?? 0;
        return max(0, $totalFees - $totalPaid);
    }
    public function school()
    {
        return $this->belongsTo(User::class, 'school_id');
    }
    public function parent()
    {
        return $this->belongsTo(SchoolParent::class, 'parent_id');
    }
    public function family()
    {
        return $this->belongsTo(Family::class, 'family_id');
    }
}