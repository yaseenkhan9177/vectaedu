<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class TransportRoute extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    protected $guarded = [];

    public function scopeForSchool($query)
    {
        if (auth()->check() && auth()->user()->school_id) {
            return $query->where('school_id', auth()->user()->school_id);
        }
        return $query;
    }
}