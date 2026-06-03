<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class Teacher extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'tenant';
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'subject',
        'semester',
        'image',
        'school_id',
        'id',
    ];

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'school_class_teacher');
    }
}