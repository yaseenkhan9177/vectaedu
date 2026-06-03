<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        static $isApplying = false;

        if ($isApplying) {
            return;
        }

        $isApplying = true;

        try {
            $schoolId = null;

            // Priority 1: Session based IDs (fastest, prevents Auth recursion)
            if (session()->has('teacher_id') && request()->is('teacher*')) {
                $schoolId = session('school_id'); // Assuming school_id is in session
            } elseif (session()->has('accountant_id') && request()->is('accountant*')) {
                $schoolId = session('school_id');
            } elseif (session()->has('student_id') && request()->is('student*')) {
                $schoolId = session('school_id');
            }

            // Priority 2: Auth Check (Fallback)
            if (!$schoolId) {
                if (Auth::guard('web')->check()) {
                    $user = Auth::guard('web')->user();
                    if ($user && $user->role === 'admin') {
                        $schoolId = $user->id;
                    }
                } elseif (Auth::guard('accountant')->check()) {
                    $schoolId = Auth::guard('accountant')->user()->school_id;
                } elseif (Auth::guard('teacher')->check()) {
                    $schoolId = Auth::guard('teacher')->user()->school_id;
                }
            }

            if ($schoolId) {
                $connection = $model->getConnectionName() ?: 'tenant';
                if (\Illuminate\Support\Facades\Schema::connection($connection)->hasColumn($model->getTable(), 'school_id')) {
                    $builder->where($model->getTable() . '.school_id', $schoolId);
                }
            }
        } finally {
            $isApplying = false;
        }
    }
}
