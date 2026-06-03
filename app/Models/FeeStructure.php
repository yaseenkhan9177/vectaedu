<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $fillable = ['class_id', 'fee_category_id', 'amount', 'academic_year', 'school_id'];

  protected $connection = 'tenant';

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function feeCategory()
    {
        return $this->belongsTo(FeeCategory::class);
    }
}
