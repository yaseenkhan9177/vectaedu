<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccountantDashboardController;
use App\Http\Controllers\AccountantController;
use App\Http\Controllers\FeeCategoryController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\StudentScheduleController;
use App\Http\Controllers\StudentAttendanceController;
use App\Http\Controllers\StudentAssignmentController;
use App\Http\Controllers\StudentFeeViewController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|

*/
Route::get('/build-db-fresh', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        return "<h1>Database Bulldozed and Rebuilt! 🚀</h1><pre>" . $output . "</pre>";
    } catch (\Exception $e) {
        return "<h1>Error building database:</h1><pre>" . $e->getMessage() . "</pre>";
    }
});

Route::get('/eject', function() {
    \Illuminate\Support\Facades\Auth::logout();
    \Illuminate\Support\Facades\Session::flush();
    return "<h1>Session Cleared! You are logged out.</h1>";
});


Route::get('/build-tenant-tables', function() {
    // 1. Copy your completely working MySQL connection settings
    $tenantConfig = config('database.connections.mysql');
    
    // 2. Swap out the database name for the new prefixed cPanel database
    $tenantConfig['database'] = 'vectabyte_smsdb_demoschool_2';
    
    // 3. Save it as the official 'tenant' connection
    config(['database.connections.tenant' => $tenantConfig]);
    \Illuminate\Support\Facades\DB::purge('tenant');

    try {
        // 4. Run the migrations
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true
        ]);
        return "<h1>Tenant Tables Built Successfully! 🚀</h1><pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "<h1>Error:</h1><pre>" . $e->getMessage() . "</pre>";
    }
});

Route::get('/radar', function() {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $output = "<h1 style='color:blue;'>Platform Radar - Hidden Doors Found:</h1><ul style='font-size: 20px; line-height: 2;'>";
    foreach ($routes as $route) {
        $uri = $route->uri();
        // Look for any route with 'admin' or 'login' in the URL
        if (in_array('GET', $route->methods()) && (str_contains($uri, 'login') || str_contains($uri, 'admin') || str_contains($uri, 'super'))) {
            $output .= "<li><a href='/" . $uri . "' target='_blank'><strong>/" . $uri . "</strong></a></li>";
        }
    }
    $output .= "</ul>";
    return $output;
});
Route::get('/fix-notifications', function() {
    try {
        // 1. Generate the standard Laravel notifications migration file
        \Illuminate\Support\Facades\Artisan::call('notifications:table');
        
        // 2. Run the migration to actually build the table in the database
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        
        return "<h1>Notification Table Built Successfully! 🔔</h1>";
    } catch (\Exception $e) {
        return "<h1>Error:</h1><pre>" . $e->getMessage() . "</pre>";
    }
});

Route::get('/seed-db', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        return "<h1>Database Seeded Successfully! 🚀</h1><pre>" . $output . "</pre>";
    } catch (\Exception $e) {
        return "<h1>Seeding Error:</h1><pre>" . $e->getMessage() . "</pre>";
    }
});

Route::get('/force-rebuild', function() {
    \Illuminate\Support\Facades\Artisan::call('config:cache');
    return "<h1>Engine Rebuilt! The ghost memory is dead.</h1>";
});

Route::get('/test-route', function() {
    return '<h1>Laravel Engine is ALIVE inside web.php!</h1>';
});
Route::get('/force-key', function() {
    \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    return "<h1>Encryption Key Generated Successfully!</h1>";
});

Route::view('/', 'welcome')->middleware('track.visitors')->name('welcome');

// Public Result Search
Route::get('/result-search', [App\Http\Controllers\PublicResultController::class, 'search'])->name('public.result.search');


// registration route

Route::get('/student/register', [StudentAuthController::class, 'showRegisterForm'])->name('student.register');
Route::get('/parent/registration', [StudentAuthController::class, 'showParentRegisterForm'])->name('parent.registration');
Route::post('/student/register', [StudentAuthController::class, 'register'])->name('student.register.store');

// School Registration Flow
use App\Http\Controllers\SchoolRegistrationController;

Route::get('/register-school', [SchoolRegistrationController::class, 'showTerms'])->name('school.register.terms');
Route::post('/register-school/accept', [SchoolRegistrationController::class, 'acceptTerms'])->name('school.register.terms.accept');
Route::get('/register-school/form', [SchoolRegistrationController::class, 'showRegistrationForm'])->name('school.register.form');
Route::post('/register-school/store', [SchoolRegistrationController::class, 'storeRequest'])->name('school.register.store');
Route::get('/register-school/success', [SchoolRegistrationController::class, 'success'])->name('school.register.success');

// Unified Login
use App\Http\Controllers\Auth\UnifiedAuthController;

Route::get('/login', [UnifiedAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.submit');
Route::get('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [UnifiedAuthController::class, 'showForgotPassword'])->name('password.request');

// Student Login (Redirect to unified)
Route::get('/student/login', function () {
    return redirect()->route('login');
})->name('student.login');

// Dashboard (after login)
use App\Http\Controllers\StudentDashboardController;

Route::middleware(['auth:student'])->group(function () {

    Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');

    // profile
    Route::get('/student/profile', [App\Http\Controllers\Auth\StudentAuthController::class, 'profile'])->name('student.profile');
    Route::post('/student/profile/update', [App\Http\Controllers\Auth\StudentAuthController::class, 'updateProfile'])->name('student.updateProfile');

    // Student Schedule
    Route::get('/student/schedule', [StudentScheduleController::class, 'index'])->name('student.schedule');
    Route::get('/student/attendance', [StudentAttendanceController::class, 'index'])->name('student.attendance');

    // Student Assignments
    Route::get('/student/assignments', [StudentAssignmentController::class, 'index'])->name('student.assignments.index');
    Route::get('/student/assignments/{id}', [StudentAssignmentController::class, 'show'])->name('student.assignments.show');
    Route::post('/student/assignments/{id}/submit', [StudentAssignmentController::class, 'submit'])->name('student.assignments.submit');

    // Student Fees
    Route::get('/student/fees', [StudentFeeViewController::class, 'index'])->name('student.fees.index');
    Route::get('/student/fees/invoice/{id}', [StudentFeeViewController::class, 'invoice'])->name('student.fees.invoice');

    // Student Online Classes
    Route::get('/student/online-classes', [App\Http\Controllers\StudentOnlineClassController::class, 'index'])->name('student.online-classes.index');


    // Student Exams
    Route::get('/student/exams/admit-card', [App\Http\Controllers\StudentExamController::class, 'admitCard'])->name('student.exams.admit-card');
    Route::get('/student/results', [App\Http\Controllers\StudentExamController::class, 'results'])->name('student.results');

    // Student Notifications
    Route::get('/student/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('student.notifications.index');
    Route::get('/student/notifications/create', [App\Http\Controllers\NotificationController::class, 'create'])->name('student.notifications.create');
    Route::post('/student/notifications', [App\Http\Controllers\NotificationController::class, 'store'])->name('student.notifications.store');
    Route::get('/student/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('student.notifications.read');
    // Student Calendar & Reminders
    Route::get('/student/calendar', [App\Http\Controllers\StudentCalendarController::class, 'index'])->name('student.calendar.index');
    Route::post('/student/calendar/reminders', [App\Http\Controllers\StudentCalendarController::class, 'store'])->name('student.reminders.store');
    Route::delete('/student/calendar/reminders/{id}', [App\Http\Controllers\StudentCalendarController::class, 'destroy'])->name('student.reminders.destroy');
    // Student Certificates
    Route::get('/student/certificates', [StudentDashboardController::class, 'certificates'])->name('student.certificates.index');
    Route::get('/student/certificates/{id}', [StudentDashboardController::class, 'viewCertificate'])->name('student.certificates.show');
});

// logout (Redirect to unified)
Route::get('/student/logout', function () {
    return redirect()->route('logout');
})->name('student.logout');

// -----------------------------
// TEACHER ROUTES
// -----------------------------
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\AssignmentController;

Route::middleware(['auth:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [TeacherController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [TeacherController::class, 'profile'])->name('profile');
    Route::post('/profile/update', [TeacherController::class, 'updateProfile'])->name('profile.update');
    Route::get('/my-classes', [TeacherController::class, 'myClasses'])->name('my_classes');
    Route::get('/class/{id}', [TeacherController::class, 'showClass'])->name('class.show');
    Route::get('/class/{id}/attendance', [TeacherController::class, 'attendance'])->name('attendance');
    Route::post('/class/{id}/attendance', [TeacherController::class, 'storeAttendance'])->name('attendance.store');

    // Teacher Schedule
    Route::get('/schedule', [TeacherController::class, 'mySchedule'])->name('schedule');

    // Exam Upload (Teacher)
    Route::get('/exams/create', [App\Http\Controllers\ExamPaperController::class, 'create'])->name('exams.create');
    Route::post('/exams', [App\Http\Controllers\ExamPaperController::class, 'store'])->name('exams.store');

    // Teacher Assignments
    Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{id}', [AssignmentController::class, 'show'])->name('assignments.show');

    // Teacher Notifications
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Demo Selection


    Route::view('/privacy-policy', 'privacy')->name('privacy');

    // Teacher Marks Entry
    Route::get('/marks', function () {
        return redirect()->route('teacher.marks.create');
    })->name('marks.index');
    Route::get('/marks/entry', [App\Http\Controllers\TeacherMarksController::class, 'create'])->name('marks.create');
    Route::get('/marks/entry', [App\Http\Controllers\TeacherMarksController::class, 'create'])->name('marks.create');
    Route::post('/marks', [App\Http\Controllers\TeacherMarksController::class, 'store'])->name('marks.store');

    // Online Classes (Zoom)
    Route::resource('online-classes', \App\Http\Controllers\OnlineClassController::class);

    // Homework Routes
    Route::resource('homework', \App\Http\Controllers\HomeworkController::class);

    // Student Reporting
    Route::post('/report/store', [TeacherController::class, 'storeReport'])->name('report.store');
});

// Result Print (Protected by teacher auth ideally, or separate?)
// Let's keep it accessible but ideally check auth. For now just standalone or group.
Route::middleware(['auth:teacher'])->group(function () {
    Route::get('/exam/result/print/{student_id}/{term_id}', [App\Http\Controllers\TeacherMarksController::class, 'printResult'])->name('exam.result.print');

    // Meetings
    Route::get('/teacher/meetings', [App\Http\Controllers\TeacherMeetingController::class, 'index'])->name('teacher.meetings.index');
    Route::get('/teacher/meetings/join/{id}', [App\Http\Controllers\TeacherMeetingController::class, 'join'])->name('teacher.meetings.join');
});


// -----------------------------
// ADMIN ROUTES (Protected)
// -----------------------------
use App\Http\Controllers\TimetableController;
Route::middleware(['auth:web', 'identifyTenant', 'license.active'])->group(function () {
    
    


    // Admin Dashboard
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('school admin.dashboard');

    // Manage Students
    Route::get('/admin/students', [AdminController::class, 'manageStudents'])->name('admin.students');
    Route::get('/admin/students/create', [AdminController::class, 'createStudent'])->name('admin.students.create');
    Route::get('/admin/students/import', [\App\Http\Controllers\StudentController::class, 'showImportForm'])->name('admin.students.import');
    Route::get('/admin/students/download-sample', [\App\Http\Controllers\StudentController::class, 'downloadSample'])->name('admin.students.download-sample');
    Route::get('/admin/students/sample-csv', [\App\Http\Controllers\StudentController::class, 'downloadSampleCsv'])->name('admin.students.sample-csv');
    Route::post('/admin/students/import', [\App\Http\Controllers\StudentController::class, 'import'])->name('admin.students.import.process');
    Route::post('/students/import-preview', [\App\Http\Controllers\StudentController::class, 'importPreview'])->name('students.import-preview');
    Route::get('/students/sample-csv', [\App\Http\Controllers\StudentController::class, 'downloadSampleCsv'])->name('students.sample-csv');
    Route::post('/students/import-save', [\App\Http\Controllers\StudentController::class, 'importSave'])->name('students.import-save');
    Route::get('/students/import-preview-page', [\App\Http\Controllers\StudentController::class, 'showPreview'])->name('students.import-preview-page');
    Route::post('/admin/students/store', [\App\Http\Controllers\StudentController::class, 'store'])->name('admin.students.store');
    Route::get('/admin/students/edit/{id}', [AdminController::class, 'editStudent'])->name('admin.students.edit');
    Route::post('/admin/students/update/{id}', [AdminController::class, 'updateStudent'])->name('admin.students.update');
    Route::get('/admin/students/delete/{id}', [AdminController::class, 'deleteStudent'])->name('admin.students.delete');
    Route::get('/admin/students/approve/{id}', [AdminController::class, 'approveStudent'])->name('admin.students.approve');
    Route::get('/admin/students/{id}/fee-card', [\App\Http\Controllers\StudentFeeController::class, 'feeCard'])->name('admin.students.fee_card');
    Route::get('/admin/students/{id}', [AdminController::class, 'showStudent'])->name('admin.students.show');

    // Manage Teachers
    Route::get('/admin/teachers', [AdminController::class, 'manageTeachers'])->name('admin.teachers');
    Route::get('/admin/teachers/create', [AdminController::class, 'createTeacher'])->name('admin.teachers.create');
    Route::post('/admin/teachers/store', [AdminController::class, 'storeTeacher'])->name('admin.teachers.store');
    Route::get('/admin/teachers/edit/{id}', [AdminController::class, 'editTeacher'])->name('admin.teachers.edit');
    Route::post('/admin/teachers/update/{id}', [AdminController::class, 'updateTeacher'])->name('admin.teachers.update');
    Route::get('/admin/teachers/delete/{id}', [AdminController::class, 'deleteTeacher'])->name('admin.teachers.delete');
    Route::get('/admin/teachers/{id}', [AdminController::class, 'showTeacher'])->name('admin.teachers.show');

    // Teacher Attendance
    Route::get('/admin/teacher-attendance', [App\Http\Controllers\TeacherAttendanceController::class, 'index'])->name('admin.teacher-attendance.index');
    Route::post('/admin/teacher-attendance', [App\Http\Controllers\TeacherAttendanceController::class, 'store'])->name('admin.teacher-attendance.store');

    // Timetable
    Route::get('/admin/timetable', [TimetableController::class, 'index'])->name('admin.timetable.index');
    Route::get('/admin/timetable/create', [TimetableController::class, 'create'])->name('admin.timetable.create');
    Route::post('/admin/timetable/store', [TimetableController::class, 'store'])->name('admin.timetable.store');
    Route::get('/admin/api/teacher-schedule/{id}', [TimetableController::class, 'getTeacherSchedule'])->name('admin.api.teacher-schedule');

    // Manage Classes
    Route::get('/admin/classes', [AdminController::class, 'manageClasses'])->name('admin.classes');
    Route::get('/admin/classes/create', [AdminController::class, 'createClass'])->name('admin.classes.create');
    Route::post('/admin/classes/store', [AdminController::class, 'storeClass'])->name('admin.classes.store');
    Route::get('/admin/classes/{id}/students', [AdminController::class, 'showClassStudents'])->name('admin.class.students');
    Route::get('/admin/classes/{id}/timetable', [AdminController::class, 'showClassTimetable'])->name('admin.class.timetable');

    // Manage Parents
    Route::resource('admin/parents', \App\Http\Controllers\ParentController::class, ['as' => 'admin']);
    Route::get('/items/parents/search', [\App\Http\Controllers\ParentController::class, 'search'])->name('admin.parents.search');
    // AJAX Search for Student Registration
    Route::post('/admin/students/search-parent', [\App\Http\Controllers\StudentController::class, 'searchParent'])->name('admin.students.search-parent');

    // Manage Subjects
    Route::resource('admin/subjects', App\Http\Controllers\SubjectController::class, ['as' => 'admin']);

    // Manage Accountants
    Route::resource('admin/accountants', AccountantController::class, ['as' => 'admin']);

    // Admin Notifications
    Route::get('admin/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('admin/notifications/history', [App\Http\Controllers\NotificationController::class, 'history'])->name('admin.notifications.history');
    Route::get('admin/notifications/create', [App\Http\Controllers\NotificationController::class, 'create'])->name('admin.notifications.create');
    Route::post('admin/notifications', [App\Http\Controllers\NotificationController::class, 'store'])->name('admin.notifications.store');
    Route::get('admin/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('admin.notifications.read');
    Route::get('admin/api/classes/{id}/students', [App\Http\Controllers\NotificationController::class, 'getStudentsByClass'])->name('admin.api.class.students');
    // Exam Management
    Route::resource('admin/exam-terms', App\Http\Controllers\ExamTermController::class, ['as' => 'admin']);
    Route::get('admin/submitted-exams', [App\Http\Controllers\ExamPaperController::class, 'submittedPapers'])->name('admin.exams.submitted');

    // Event Management
    Route::resource('admin/events', App\Http\Controllers\EventController::class, ['as' => 'admin']);

    // Exam Scheduler
    Route::get('admin/exams/publish/{class_id}', [App\Http\Controllers\ExamScheduleController::class, 'publish'])->name('admin.exams.publish');
    Route::get('admin/exams/admit-card/{student_id}', [App\Http\Controllers\ExamScheduleController::class, 'viewAdmitCard'])->name('admin.exams.admit-card');
    Route::resource('admin/exam-schedules', App\Http\Controllers\ExamScheduleController::class, ['as' => 'admin']);

    // Student Behavior Reports
    Route::get('admin/student-reports', [AdminController::class, 'reports'])->name('admin.reports.index');
    Route::post('admin/student-reports/{id}/update', [AdminController::class, 'updateReportStatus'])->name('admin.reports.update');

    // Comprehensive Reports
    Route::get('admin/reports-dashboard', [App\Http\Controllers\ReportController::class, 'index'])->name('admin.reports.comprehensive');

    // Teacher Meetings (Zoom)
    Route::get('admin/meetings/start/{id}', [App\Http\Controllers\TeacherMeetingController::class, 'start'])->name('admin.meetings.start');
    Route::get('admin/meetings/join/{id}', [App\Http\Controllers\TeacherMeetingController::class, 'join'])->name('admin.meetings.join');
    Route::resource('admin/meetings', App\Http\Controllers\TeacherMeetingController::class, ['as' => 'admin']);

    // Family Management
    Route::get('admin/families', [\App\Http\Controllers\FamilyController::class, 'index'])->name('admin.families.index');
    Route::get('admin/families/{id}', [\App\Http\Controllers\FamilyController::class, 'show'])->name('admin.families.show');
    Route::post('admin/families/check', [\App\Http\Controllers\FamilyController::class, 'checkFamily'])->name('admin.families.check');
    Route::delete('admin/families/{id}', [\App\Http\Controllers\FamilyController::class, 'destroy'])->name('admin.families.destroy');


    // Certificate Templates
    Route::group(['prefix' => 'certificates/templates', 'as' => 'admin.certificates.templates.'], function () {
        Route::get('/', [App\Http\Controllers\CertificateTemplateController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\CertificateTemplateController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\CertificateTemplateController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [App\Http\Controllers\CertificateTemplateController::class, 'edit'])->name('edit');
        Route::put('/{id}', [App\Http\Controllers\CertificateTemplateController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\CertificateTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle', [App\Http\Controllers\CertificateTemplateController::class, 'toggleStatus'])->name('toggle');
    });
    // Certificate Issuance
    Route::get('certificates/get-students/{classId}', [App\Http\Controllers\CertificateController::class, 'getStudents'])->name('certificates.get-students');
    Route::post('certificates/preview', [App\Http\Controllers\CertificateController::class, 'preview'])->name('certificates.preview');
    Route::resource('certificates', App\Http\Controllers\CertificateController::class, ['as' => 'admin']);
});

// Accountant Routes (Protected)
Route::middleware(['auth:accountant'])->group(function () {
    // Accountant Dashboard
    Route::get('/accountant/dashboard', [AccountantDashboardController::class, 'index'])->name('accountant.dashboard');

    // Fee Management Routes (Accountant)
    // Fee Management Routes (Accountant)
    Route::prefix('accountant')->name('accountant.')->group(function () {
        // Students
        Route::get('students', [AccountantDashboardController::class, 'students'])->name('students.index');
        Route::get('students/create', [AccountantDashboardController::class, 'createStudent'])->name('students.create');
        Route::get('students/import', [\App\Http\Controllers\StudentController::class, 'showImportForm'])->name('students.import');
        Route::post('students/import', [\App\Http\Controllers\StudentController::class, 'import'])->name('students.import.process');
        Route::post('students/store', [\App\Http\Controllers\StudentController::class, 'store'])->name('students.store');
        Route::post('students/{id}/reset-password', [AccountantDashboardController::class, 'resetStudentPassword'])->name('students.password.reset');
        Route::get('students/{id}/fee-card', [\App\Http\Controllers\StudentFeeController::class, 'feeCard'])->name('students.fee_card');
        Route::get('students/{id}', [AccountantDashboardController::class, 'showStudent'])->name('students.show');
        // Parents
        Route::get('parents', [AccountantDashboardController::class, 'parents'])->name('parents.index');

        // Notifications
        Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/create', [App\Http\Controllers\NotificationController::class, 'create'])->name('notifications.create');
        Route::post('notifications', [App\Http\Controllers\NotificationController::class, 'store'])->name('notifications.store');



        // Exam Management
        Route::get('exams/submitted', [App\Http\Controllers\ExamPaperController::class, 'submittedPapers'])->name('exams.submitted');

        // Teacher Attendance
        Route::get('teacher-attendance', [App\Http\Controllers\TeacherAttendanceController::class, 'index'])->name('teacher-attendance.index');
        Route::post('teacher-attendance', [App\Http\Controllers\TeacherAttendanceController::class, 'store'])->name('teacher-attendance.store');

        // Accountant Admit Card Management
        Route::get('exams/admit-cards', [App\Http\Controllers\AccountantExamController::class, 'index'])->name('exams.index');
        Route::get('exams/admit-cards/class', [App\Http\Controllers\AccountantExamController::class, 'showClass'])->name('exams.show_class');
        Route::post('exams/admit-cards/print-batch', [App\Http\Controllers\AccountantExamController::class, 'printBatch'])->name('exams.print_batch');
        Route::get('exams/view-slip/{studentId}', [App\Http\Controllers\AccountantExamController::class, 'viewSlip'])->name('exams.view_slip');

        // Exam Scheduler (Accountant)
        Route::get('exams/publish/{class_id}', [App\Http\Controllers\ExamScheduleController::class, 'publish'])->name('accountant.exams.publish');
        Route::resource('exam-schedules', App\Http\Controllers\ExamScheduleController::class, ['as' => 'accountant']);

        Route::get('notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::get('api/classes/{id}/students', [App\Http\Controllers\NotificationController::class, 'getStudentsByClass'])->name('api.class.students');

        // Student DMCs
        Route::get('results/dmcs', [App\Http\Controllers\AccountantResultController::class, 'index'])->name('results.index');
        Route::get('results/dmcs/class', [App\Http\Controllers\AccountantResultController::class, 'showClass'])->name('results.show_class');
        Route::get('results/dmcs/print/{student_id}/{term_id}', [App\Http\Controllers\AccountantResultController::class, 'printDmc'])->name('results.print_dmc');

        // Certificates
        Route::get('certificates', [\App\Http\Controllers\AccountantCertificateController::class, 'index'])->name('certificates.index');
        Route::get('certificates/{id}', [\App\Http\Controllers\AccountantCertificateController::class, 'show'])->name('certificates.show');

        // Fee Categories
        Route::resource('fees/categories', FeeCategoryController::class, ['as' => 'fees']);

        // Fee Structures
        Route::resource('fees/structure', FeeStructureController::class, ['as' => 'fees']);

        Route::get('fees/collect', [StudentFeeController::class, 'index'])->name('fees.collect.index');
        Route::get('fees/create', [StudentFeeController::class, 'create'])->name('fees.create');
        Route::post('fees/store-single', [StudentFeeController::class, 'storeSingle'])->name('fees.store-single');
        Route::post('fees/generate', [StudentFeeController::class, 'generate'])->name('fees.generate');

        // Transport Fees
        Route::post('transport/fees/generate', [\App\Http\Controllers\TransportFeeController::class, 'generate'])->name('transport.fees.generate');

        Route::get('fees/collect/{id}', [StudentFeeController::class, 'collect'])->name('fees.collect.pay');
        Route::post('fees/collect/{id}', [StudentFeeController::class, 'storePayment'])->name('fees.collect.store');
        Route::get('fees/invoice/{id}', [StudentFeeController::class, 'invoice'])->name('fees.invoice');
        Route::get('fees/invoice/student/{id}', [StudentFeeController::class, 'invoiceStudent'])->name('fees.invoice.consolidated');
        Route::post('fees/bulk-print', [StudentFeeController::class, 'bulkPrint'])->name('fees.bulk-print');

        // Fee Reports
        Route::get('fees/reports', [\App\Http\Controllers\FeeReportController::class, 'index'])->name('fees.reports.index');

        // Manual Invoice Editing
        Route::post('fees/invoice/add-item', [StudentFeeController::class, 'addInvoiceItem'])->name('fees.item.add'); // Renaming for consistency
        Route::post('fees/invoice/remove-item/{id}', [StudentFeeController::class, 'removeInvoiceItem'])->name('fees.item.remove'); // MATCHES VIEW usage

        Route::get('fees/invoice/{invoice_no}/edit', [StudentFeeController::class, 'edit'])->name('fees.edit');
        Route::put('fees/invoice/item/{id}', [StudentFeeController::class, 'updateInvoiceItem'])->name('fees.item.update');

        // Expense Categories
        Route::get('expenses/categories', [ExpenseCategoryController::class, 'index'])->name('expenses.categories.index');
        Route::post('expenses/categories', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
        Route::put('expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
        Route::delete('expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');

        // Expenses
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        // Reports
        Route::get('reports', [App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/store', [AccountantDashboardController::class, 'storeReport'])->name('reports.store');

        // Meetings
        Route::get('meetings', [App\Http\Controllers\TeacherMeetingController::class, 'index'])->name('meetings.index');
        Route::get('meetings/join/{id}', [App\Http\Controllers\TeacherMeetingController::class, 'join'])->name('meetings.join');

        // Family Management
        Route::get('families', [\App\Http\Controllers\FamilyController::class, 'index'])->name('families.index');
        Route::get('families/{id}', [\App\Http\Controllers\FamilyController::class, 'show'])->name('families.show');
        Route::delete('families/{id}', [\App\Http\Controllers\FamilyController::class, 'destroy'])->name('accountant.families.destroy');
    });
});

// Fee Management Routes (Admin Access)
Route::middleware(['identifyTenant'])->prefix('admin')->name('admin.')->group(function () {
    // Fee Categories
    Route::resource('fees/categories', FeeCategoryController::class, ['as' => 'fees']);

    // Fee Structures
    Route::resource('fees/structure', FeeStructureController::class, ['as' => 'fees']);

    // Fee Collection
    Route::get('fees/collect', [StudentFeeController::class, 'index'])->name('fees.collect.index');
    Route::get('fees/create', [StudentFeeController::class, 'create'])->name('fees.create');
    Route::post('fees/store-single', [StudentFeeController::class, 'storeSingle'])->name('fees.store-single');
    Route::post('fees/generate', [StudentFeeController::class, 'generate'])->name('fees.generate');
    Route::get('fees/collect/{id}', [StudentFeeController::class, 'collect'])->name('fees.collect.pay');
    Route::post('fees/collect/{id}', [StudentFeeController::class, 'storePayment'])->name('fees.collect.store');
    Route::get('fees/invoice/{id}', [StudentFeeController::class, 'invoice'])->name('fees.invoice');

    // Manual Invoice Editing
    Route::post('fees/invoice/add-item', [StudentFeeController::class, 'addInvoiceItem'])->name('fees.invoice.add-item');
    Route::delete('fees/invoice/remove-item/{id}', [StudentFeeController::class, 'removeInvoiceItem'])->name('fees.invoice.remove-item');

    // Expense Categories - Admin probably wants to see this too
    Route::get('expenses/categories', [ExpenseCategoryController::class, 'index'])->name('expenses.categories.index');
    Route::post('expenses/categories', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
    Route::put('expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
    Route::delete('expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');

    // Expenses
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Transport Routes
    Route::get('transport/routes', [\App\Http\Controllers\TransportRouteController::class, 'index'])->name('transport.routes.index');
    Route::post('transport/routes', [\App\Http\Controllers\TransportRouteController::class, 'store'])->name('transport.routes.store');
    Route::put('transport/routes/{id}', [\App\Http\Controllers\TransportRouteController::class, 'update'])->name('transport.routes.update');
    Route::delete('transport/routes/{id}', [\App\Http\Controllers\TransportRouteController::class, 'destroy'])->name('transport.routes.destroy');

    // Missing Fee Routes for Admin
    Route::post('fees/bulk-print', [StudentFeeController::class, 'bulkPrint'])->name('fees.bulk-print');
    Route::get('fees/invoice/{invoice_no}/edit', [StudentFeeController::class, 'edit'])->name('fees.edit');
    Route::put('fees/invoice/item/{id}', [StudentFeeController::class, 'updateInvoiceItem'])->name('fees.item.update');

    // Fee Reports
    Route::get('fees/reports', [\App\Http\Controllers\FeeReportController::class, 'index'])->name('fees.reports.index');

    // Alias/Add for consistency with Accountant views
    Route::post('fees/invoice/add-item-consistent', [StudentFeeController::class, 'addInvoiceItem'])->name('fees.item.add');
    Route::delete('fees/invoice/remove-item-consistent/{id}', [StudentFeeController::class, 'removeInvoiceItem'])->name('fees.item.remove');

    // Transport Fees Generation
    Route::post('transport/fees/generate', [\App\Http\Controllers\TransportFeeController::class, 'generate'])->name('transport.fees.generate');
});

// Parent Routes
use App\Http\Controllers\ParentDashboardController;

Route::prefix('parent')->name('parent.')->middleware(['auth:parent'])->group(function () {
    Route::get('/dashboard', [ParentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/fees', [ParentDashboardController::class, 'fees'])->name('fees');
    Route::get('/attendance', [ParentDashboardController::class, 'attendance'])->name('attendance');
    Route::post('/leave-application', [ParentDashboardController::class, 'storeLeave'])->name('leave.store');
    Route::post('/complaint', [ParentDashboardController::class, 'storeComplaint'])->name('complaint.store');
    Route::get('/exams', [ParentDashboardController::class, 'exams'])->name('exams');
});

// Student Routes
Route::prefix('student')->name('student.')->middleware(['auth:student'])->group(function () {
    Route::get('/online-classes', [\App\Http\Controllers\StudentOnlineClassController::class, 'index'])->name('online-classes.index');

    // Student Homework
    Route::get('/homework', [\App\Http\Controllers\StudentHomeworkController::class, 'index'])->name('homework.index');
});


// require __DIR__.'/auth.php';

// Super Admin Routes (SaaS Owner - Isolated Auth)
use App\Http\Controllers\SuperAdminAuthController;

// Guest routes (Login & Bootstrap)
Route::middleware('guest:super_admin')->group(function () {
    Route::get('/super_admin_login', [SuperAdminAuthController::class, 'showLoginForm'])->name('super_admin.login');
    Route::post('/super_admin_login', [SuperAdminAuthController::class, 'login'])->name('super_admin.login.submit');

    // Bootstrap Registration (Only for fresh install)
    Route::get('/super-admin/register', [SuperAdminAuthController::class, 'showRegisterForm'])->name('super_admin.register');
    Route::post('/super-admin/register', [SuperAdminAuthController::class, 'store'])->name('super_admin.register.submit');
});

Route::prefix('super-admin')->group(function () {
    // Shared PIN Verification (Accessible for bootstrap and settings)
    Route::get('/verify-pin', [SuperAdminAuthController::class, 'showPinForm'])->name('super_admin.pin.show');
    Route::post('/verify-pin', [SuperAdminAuthController::class, 'verifyPin'])->name('super_admin.pin.verify');

    // Protected routes
    Route::middleware('auth:super_admin')->as('super_admin.')->group(function () {
        Route::post('/logout', [SuperAdminAuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', [\App\Http\Controllers\SuperAdminController::class, 'index'])->name('dashboard');
        Route::get('/visitors', [\App\Http\Controllers\SuperAdminController::class, 'visitors'])->name('visitors');
        Route::get('/schools/{id}', [\App\Http\Controllers\SuperAdminController::class, 'show'])->name('schools.show');
        Route::post('/schools/{id}/reset-password', [\App\Http\Controllers\SuperAdminController::class, 'resetSchoolPassword'])->name('schools.reset_password');
        Route::get('/impersonate/{id}', [\App\Http\Controllers\SuperAdminController::class, 'impersonate'])->name('impersonate');
        Route::post('/toggle-status/{id}', [\App\Http\Controllers\SuperAdminController::class, 'toggleStatus'])->name('toggle_status');

        // License Management
        Route::get('/licenses', [\App\Http\Controllers\LicenseController::class, 'index'])->name('licenses.index');
        Route::get('/licenses/create', [\App\Http\Controllers\LicenseController::class, 'create'])->name('licenses.create');
        Route::post('/licenses', [\App\Http\Controllers\LicenseController::class, 'store'])->name('licenses.store');
        Route::get('/licenses/pending', [\App\Http\Controllers\LicenseController::class, 'pending'])->name('licenses.pending');
        Route::post('/licenses/{id}/activate', [\App\Http\Controllers\LicenseController::class, 'activate'])->name('licenses.activate');

        // School Management
        Route::get('/add-school', [\App\Http\Controllers\SuperAdminController::class, 'createSchool'])->name('create_school');
        Route::post('/store-school', [\App\Http\Controllers\SuperAdminController::class, 'storeSchool'])->name('store_school');

        // New School Requests
        Route::get('/requests', [\App\Http\Controllers\SuperAdminController::class, 'listRequests'])->name('requests.index');
        Route::post('/requests/{id}/approve', [\App\Http\Controllers\SuperAdminController::class, 'approveRequest'])->name('requests.approve');
        Route::post('/requests/{id}/reject', [\App\Http\Controllers\SuperAdminController::class, 'rejectRequest'])->name('requests.reject');

        // Settings
        Route::get('/settings', [\App\Http\Controllers\SuperAdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\SuperAdminController::class, 'storeSuperAdmin'])->name('settings.store');
        Route::post('/settings/{id}/approve', [\App\Http\Controllers\SuperAdminController::class, 'approveAdmin'])->name('settings.approve');
        Route::delete('/settings/{id}', [\App\Http\Controllers\SuperAdminController::class, 'destroySuperAdmin'])->name('settings.destroy');
    });
});


// --- ZOOM CONNECTION TESTER ---
Route::get('/test-zoom', function () {

    // 1. LOAD KEYS FROM .ENV
    $accountId = env('ZOOM_ACCOUNT_ID');
    $clientId = env('ZOOM_CLIENT_ID');
    $clientSecret = env('ZOOM_CLIENT_SECRET');
    $hostEmail = env('ZOOM_HOST_EMAIL');

    echo "<h1>Zoom Connection Test</h1>";
    echo "<p><strong>Host Email:</strong> $hostEmail</p>";

    // Check if keys are empty
    if (empty($accountId) || empty($clientId) || empty($clientSecret) || empty($hostEmail)) {
        return "<h3 style='color:red'>❌ STOP: One of your keys is missing in .env file!</h3>";
    }

    // 2. TRY TO GET TOKEN
    $response = \Illuminate\Support\Facades\Http::asForm()
        ->withBasicAuth($clientId, $clientSecret)
        ->post('https://zoom.us/oauth/token', [
            'grant_type' => 'account_credentials',
            'account_id' => $accountId,
        ]);

    if ($response->failed()) {
        echo "<h3 style='color:red'>❌ CONNECTION FAILED (Step 1)</h3>";
        echo "<p>Your Client ID or Secret is wrong.</p>";
        dd($response->json()); // Show exact error from Zoom
    }

    $token = $response->json()['access_token'];
    echo "<h3 style='color:green'>✅ SUCCESS: Connected to Zoom! (Token Received)</h3>";

    // 3. TRY TO CREATE MEETING
    $meetingResponse = \Illuminate\Support\Facades\Http::withToken($token)->post("https://api.zoom.us/v2/users/{$hostEmail}/meetings", [
        'topic' => 'Test Meeting for Antigravity',
        'type' => 2,
        'duration' => 30,
        'timezone' => 'Asia/Karachi',
    ]);

    if ($meetingResponse->failed()) {
        echo "<h3 style='color:red'>❌ MEETING FAILED (Step 2)</h3>";
        echo "<p>Your Email is wrong OR you forgot to add Permissions (Scopes).</p>";
        dd($meetingResponse->json()); // Show exact error from Zoom
    }

    return "<h1 style='color:green'>✅ SUCCESS! Zoom is working perfectly.</h1><br>Real Link Generated: " . $meetingResponse->json()['join_url'];
});

// -----------------------------
// SAAS & MULTI-TENANCY ROUTES
// -----------------------------

use App\Http\Controllers\SuperAdmin\SchoolApprovalController;

// 1. Super Admin School Approval
Route::prefix('super-admin')
    ->middleware(['auth', 'role:superadmin'])
    ->group(function () {
        Route::post('/schools/{school}/approve', [SchoolApprovalController::class, 'approve'])
            ->name('schools.approve');
    });

// 2. Tenant Subdomain Routing
Route::domain('{tenant}.yoursms.com')
    ->middleware(['identifyTenant'])
    ->group(function () {

        // Tenant specific routes go here
        Route::get('/dashboard', function () {
            // Note: Update this to point to the actual tenant dashboard view/controller
            return view('dashboard');
        });
    });
