<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = ['expense_category_id', 'amount', 'expense_date', 'title', 'description', 'receipt_path', 'created_by', 'school_id'];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(Accountant::class, 'created_by');
    }
}