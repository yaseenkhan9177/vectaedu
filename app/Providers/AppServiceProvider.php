<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());

        // Centralized View Composer for all Portal Layouts
        \Illuminate\Support\Facades\View::composer(['layouts.admin', 'layouts.accountant', 'layouts.student', 'layouts.teacher', 'layouts.parent'], function ($view) {
            static $cachedData = [];
            static $cachedUser = null;
            static $cachedResolved = false;
            static $cachedPendingCount = null;

            if (!$cachedResolved) {
                $cachedResolved = true; // Set immediately to prevent recursion if an error occurs below

                try {
                    // Resolve User based on context
                    if (session()->has('teacher_id') && request()->is('teacher*')) {
                        $cachedUser = \App\Models\Teacher::find(session('teacher_id'));
                    } elseif (session()->has('accountant_id') && request()->is('accountant*')) {
                        $cachedUser = \App\Models\Accountant::find(session('accountant_id'));
                    } elseif (session()->has('student_id') && request()->is('student*')) {
                        $cachedUser = \App\Models\Student::find(session('student_id'));
                    } elseif (auth()->guard('parent')->check()) {
                        $cachedUser = auth()->guard('parent')->user();
                    } elseif (auth()->guard('web')->check()) {
                        $cachedUser = auth()->guard('web')->user();
                    }
                } catch (\Exception $e) {
                    // Fail silently to prevent crashing the whole app on layout load
                }
            }

            $currentUser = $cachedUser;
            $adminUser = null;
            $school = null;

            if ($currentUser) {
                // Determine guard type for school_id resolution
                $isWebGuard = ($currentUser instanceof \App\Models\User && !session()->has('teacher_id') && !session()->has('accountant_id') && !session()->has('student_id'));
                $schoolId = $isWebGuard ? $currentUser->id : $currentUser->school_id;

                // Static caching for school and admin info
                if (isset($cachedData[$schoolId])) {
                    $adminUser = $cachedData[$schoolId]['adminUser'];
                    $school = $cachedData[$schoolId]['school'];
                } else {
                    $adminUser = \App\Models\User::find($schoolId);
                    if ($adminUser) {
                        $school = \App\Models\School::where('email', $adminUser->email)
                            ->orWhere('name', $adminUser->school_name)
                            ->first();
                    }
                    $cachedData[$schoolId] = [
                        'adminUser' => $adminUser,
                        'school' => $school
                    ];
                }
            }

           if ($cachedPendingCount === null) {
                try {
                    $cachedPendingCount = \App\Models\Student::where('status', 'pending')->count();
                } catch (\Exception $e) {
                    $cachedPendingCount = 0; // Safely defaults to 0 if the table doesn't exist
                }
            }

            $schoolName = $school ? $school->name : ($adminUser->school_name ?? 'Own Education');
            $schoolLogo = $school && $school->logo ? asset('storage/' . $school->logo) : asset('assets/img/logo-round.jpg');

            $view->with([
                'currentUser' => $currentUser,
                'adminUser' => $adminUser,
                'school' => $school,
                'schoolName' => $schoolName,
                'schoolLogo' => $schoolLogo,
                'pendingStudentCount' => $cachedPendingCount
            ]);
        });
    }
}
