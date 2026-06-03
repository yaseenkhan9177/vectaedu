<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

class SchoolParent extends Authenticatable
{
    use HasFactory, Notifiable;
    
      protected $connection = 'tenant';

    protected $table = 'parents';

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'password',
        'cnic',
        'address',
        'image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Global Scope for Multi-tenancy
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\SchoolScope);

        // Auto-assign school_id on creation
        static::creating(function ($parent) {
            if (auth()->guard('web')->check()) {
                if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                    $parent->school_id = auth()->id();
                }
            }
        });
    }

    // Relationships
    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function school()
    {
        // Assuming Admin model represents the school/admin
        return $this->belongsTo(User::class, 'school_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'parent_id');
    }
}
