<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Accountant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
      protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'school_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\SchoolScope);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    public function school()
    {
        return $this->belongsTo(User::class, 'school_id');
    }
}
