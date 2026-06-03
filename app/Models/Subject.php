<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'school_id'];

  protected $connection = 'tenant';

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }
}
