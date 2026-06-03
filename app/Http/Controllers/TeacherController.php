<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    public function dashboard()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::with('schoolClasses')->find($teacherId);

        if (!$teacher) {
            \Illuminate\Support\Facades\Auth::guard('teacher')->logout();
            return redirect()->route('login')->with('error', 'Teacher account not found. Please login again.');
        }

        // Upcoming Events for Teacher
        $upcomingEvents = \App\Models\Event::whereJsonContains('target_audience', 'teacher')
            ->where('event_date', '>=', now())
            ->orderBy('event_date', 'asc')
            ->take(3)
            ->get();

        // Upcoming Staff Meetings
        $upcomingMeetings = \App\Models\TeacherMeeting::where(function ($q) use ($teacherId) {
            $q->whereHas('participants', function ($p) use ($teacherId) {
                $p->where('teacher_id', $teacherId);
            });
        })
            ->whereIn('status', ['scheduled', 'started'])
            ->where('start_time', '>=', now()->subHours(2)) // Show if recently started too
            ->orderBy('start_time', 'asc')
            ->take(3)
            ->get();

        // Fetch Upcoming Online Classes
        $onlineClasses = \App\Models\OnlineClass::where('teacher_id', $teacher->id)
            ->where('start_time', '>=', now())
            ->orderBy('start_time', 'asc')
            ->take(3)
            ->get();

        // Stats Calculation
        $assignmentsCount = \App\Models\Assignment::where('teacher_id', $teacherId)->count();

        $pendingGradingCount = \App\Models\Submission::whereHas('assignment', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })->whereNull('grade')->count();

        $totalAttendanceRecords = \App\Models\Attendance::where('teacher_id', $teacherId)->count();
        $presentCount = \App\Models\Attendance::where('teacher_id', $teacherId)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $averageAttendance = $totalAttendanceRecords > 0
            ? round(($presentCount / $totalAttendanceRecords) * 100)
            : 0;

        // Fetch Recent Activity
        $recentActivities = \App\Models\ActivityLog::where('user_id', $teacherId)
            ->where(function ($q) {
                $q->where('action_type', '!=', 'login'); // Optional: exclude login logs if too noisy
            })
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('teacher.dashboard', compact('teacher', 'upcomingEvents', 'onlineClasses', 'upcomingMeetings', 'assignmentsCount', 'pendingGradingCount', 'averageAttendance', 'recentActivities'));
    }

    public function profile()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::findOrFail($teacherId);

        return view('teacher.profile', compact('teacher'));
    }

    public function updateProfile(Request $request)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::findOrFail($teacherId);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email,' . $teacherId,
            'subject' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'nullable|string|min:6',
        ]);

        $teacher->name = $request->name;
        $teacher->email = $request->email;
        $teacher->subject = $request->subject;

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($teacher->image) {
                Storage::disk('public')->delete($teacher->image);
            }
            $teacher->image = $request->file('image')->store('teachers', 'public');
        }

        if ($request->filled('password')) {
            $teacher->password = Hash::make($request->password);
        }

        $teacher->save();

        // Update session name if changed
        session(['teacher_name' => $teacher->name]);

        return redirect()->route('teacher.profile')->with('success', 'Profile updated successfully.');
    }
    public function myClasses()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::with('schoolClasses')->findOrFail($teacherId);

        return view('teacher.my_classes', compact('teacher'));
    }

    public function showClass($classId)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::findOrFail($teacherId);

        // Verify the teacher is assigned to this class
        if (!$teacher->schoolClasses->contains($classId)) {
            return redirect()->route('teacher.my_classes')->with('error', 'Access denied.');
        }

        $class = \App\Models\SchoolClass::with('timetables')->findOrFail($classId);
        $students = \App\Models\Student::where('class_id', $classId)->get();

        return view('teacher.class_students', compact('class', 'students'));
    }

    // Show attendance form
    public function attendance($classId)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::findOrFail($teacherId);

        // Verify teacher is assigned to this class
        if (!$teacher->schoolClasses->contains($classId)) {
            return redirect()->route('teacher.my_classes')->with('error', 'Access denied.');
        }

        $class = \App\Models\SchoolClass::findOrFail($classId);
        $students = \App\Models\Student::where('class_id', $classId)->get();
        $today = date('Y-m-d');

        // Get existing attendance for today
        $existingAttendance = \App\Models\Attendance::where('school_class_id', $classId)
            ->where('date', $today)
            ->pluck('status', 'student_id')
            ->toArray();

        return view('teacher.attendance', compact('class', 'students', 'today', 'existingAttendance'));
    }

    // Store attendance
    public function storeAttendance(Request $request, $classId)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('teacher.login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();
        $teacher = Teacher::findOrFail($teacherId);

        // Verify teacher is assigned to this class
        if (!$teacher->schoolClasses->contains($classId)) {
            return redirect()->route('teacher.my_classes')->with('error', 'Access denied.');
        }

        $request->validate([
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,excused',
        ]);

        $date = $request->date;
        $attendanceData = $request->attendance;

        $classId = $request->route('id'); // Or getting it from method arg if available
        // Actually, looking at route: Route::post('/class/{id}/attendance'...)
        // The controller method likely is public function storeAttendance(Request $request, $id)

        // Let's assume $classId was already set or we use $id if passed. 
        // Based on previous view, I can't be 100% sure of variable name for ID if I didn't see signature.
        // But line 176 used $classId. So it must be there.
        // I will initialize $updatedCount and capture $attendanceRecord.

        $class = \App\Models\SchoolClass::findOrFail($classId); // Needed for logging
        $updatedCount = 0;

        foreach ($attendanceData as $studentId => $status) {
            $attendanceRecord = \App\Models\Attendance::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'school_class_id' => $classId,
                    'date' => $date,
                ],
                [
                    'teacher_id' => $teacherId,
                    'status' => $status,
                ]
            );
            if ($attendanceRecord->wasRecentlyCreated || $attendanceRecord->wasChanged()) {
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            // Log the attendance action
            \App\Helpers\ActivityLogger::log('attendance', "Mr./Ms. {$teacher->name} marked attendance for Class {$class->name} on {$date}.");
            return redirect()->route('teacher.attendance', $classId)->with('success', 'Attendance recorded successfully.');
        } else {
            return redirect()->route('teacher.attendance', $classId)->with('info', 'Attendance already recorded for today, no changes made.');
        }
    }

    public function mySchedule()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $teacherId = \Illuminate\Support\Facades\Auth::guard('teacher')->id();

        $timetables = \App\Models\Timetable::where('teacher_id', $teacherId)
            ->with(['schoolClass', 'subject'])
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('start_time')
            ->get();

        return view('teacher.schedule.index', compact('timetables'));
    }

    public function storeReport(Request $request)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'severity' => 'required|in:low,medium,high',
            'reason' => 'required|string',
        ]);

        $teacher = \Illuminate\Support\Facades\Auth::guard('teacher')->user();

        $reportData = [
            'student_id' => $request->student_id,
            'reporter_id' => $teacher->id,
            'reporter_role' => 'teacher',
            'severity' => $request->severity,
            'reason' => $request->reason,
            'status' => 'pending',
        ];
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_reports', 'school_id')) {
            $reportData['school_id'] = $teacher->school_id;
        }
        \App\Models\StudentReport::create($reportData);

        return back()->with('success', 'Student report submitted for review.');
    }
}
