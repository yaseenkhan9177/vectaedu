<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'action_type',
        'message',
    ];

    public function user()
    {
        // Polymorphic or simple relation? User table usually holds admins/staff?
        // Actually the system has separate tables for Student, Teacher, Accountant.
        // For simplicity we might just store the name or ID, but relations are better.
        // Given complexity of auth (Student vs Teacher vs Accountant vs User),
        // we might just store user_id and maybe user_type if needed, or just rely on message
        // For now, let's keep it simple as per plan: user_id (nullable)
        // If we want to link to specific models, we'd need morphs.
        // Let's settle for just storing the ID for reference, or maybe not even defining a hard relation
        // if we don't need to link back dynamically in this view.
        // But let's try to link to the main 'User' (Admin) or just store the ID.
        // Ideally we make it polymorphic: monitorable_type, monitorable_id.
        // Plan said: user_id (foreign key, nullable). Let's stick to simple ID.
    }

   protected $connection = 'tenant';
}
