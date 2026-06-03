<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'description', 'school_id'];

    protected $connection = 'tenant';

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
