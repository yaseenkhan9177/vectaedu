<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class StudentFee extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'student_id',
        'fee_structure_id',
        'month',
        'amount',
        'due_date',
        'status',
        'late_fee',
        'discount',
        'invoice_no',
        'discount_reason',
        'parent_viewed',
        'school_id',
        'admission_fee',
        'exam_fee',
        'transport_fee',
        'note',
        'transaction_id',
    ];

    protected $appends = ['total_amount'];

    public function getTotalAmountAttribute()
    {
        return $this->amount + $this->admission_fee + $this->exam_fee + $this->transport_fee + $this->late_fee - $this->discount;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }
}