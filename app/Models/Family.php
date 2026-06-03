<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_code',
        'father_name',
        'email',
        'phone',
        'address',
        'school_id',
    ];

    /**
     * Apply school scope so each school only sees its own families.
     */
    protected $connection = 'tenant';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate the next sequential FAM-XXXX code.
     * Example: FAM-0001, FAM-0002, FAM-0023, FAM-1000
     */
    public static function generateCode(): string
    {
        // Use withoutGlobalScopes so count is reliable even if school scope is active
        $total = self::withoutGlobalScopes()->count();
        return 'FAM-' . str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    }
}
