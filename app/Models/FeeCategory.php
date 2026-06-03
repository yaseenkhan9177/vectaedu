<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $fillable = ['name', 'description', 'school_id'];

 protected $connection = 'tenant';

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
