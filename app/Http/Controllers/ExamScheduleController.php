<?php

namespace App\Http\Controllers;

use App\Models\ExamSchedule;
use App\Models\ExamTerm;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Notifications\ExamScheduleReleased;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamScheduleController extends Controller
{
    /**
     * Get the route prefix based on role.
     */
    private function getRoutePrefix()
    {
        return request()->routeIs('accountant.*') ? 'accountant' : 'admin';
    }

    /**
     * Display the Master Schedule.
     */
    public function index(Request $request)
    {
        // Filter by Term and Class
        $activeTerm = ExamTerm::where('is_active', true)->first();
        $selectedTermId = $request->input('term_id', $activeTerm ? $activeTerm->id : null);
        $selectedClassId = $request->input('class_id');

        $terms = ExamTerm::orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::all();

        $query = ExamSchedule::with(['class', 'subject', 'term'])
            ->orderBy('exam_date')
            ->orderBy('start_time');

        if ($selectedTermId) {
            $query->where('term_id', $selectedTermId);
        }

        if ($selectedClassId) {
            $query->where('class_id', $selectedClassId);
        }

        $schedules = $query->get();
        $routePrefix = $this->getRoutePrefix();

        return view('admin.exam_schedules.index', compact('schedules', 'terms', 'classes', 'selectedTermId', 'selectedClassId', 'routePrefix'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $terms = ExamTerm::where('is_active', true)->orderBy('start_date', 'desc')->get();
        if ($terms->isEmpty()) {
            $terms = ExamTerm::orderBy('start_date', 'desc')->get(); // Fallback
        }
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $teachers = \App\Models\Teacher::all();
        $routePrefix = $this->getRoutePrefix();

        return view('admin.exam_schedules.create', compact('terms', 'classes', 'subjects', 'teachers', 'routePrefix'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Get the common data
        $classId = $request->class_id;
        $termId = $request->term_id;
        $section = $request->section; // New Field
        $schoolId = auth()->user()->school_id ?? 1;

        // Validation for common fields
        $request->validate([
            'term_id' => 'required|exists:tenant.exam_terms,id',
            'class_id' => 'required|exists:tenant.school_classes,id',
            'subjects' => 'nullable|array',
            'dates' => 'nullable|array',
            'start_times' => 'nullable|array',
            'end_times' => 'nullable|array',
            'rooms' => 'nullable|array',
            'paper_types' => 'nullable|array',
            'total_marks' => 'nullable|array',
            'passing_marks' => 'nullable|array',
        ]);

        $conflicts = [];

        // 2. Loop through the Subjects Array
        if ($request->subjects) {
            foreach ($request->subjects as $key => $subjectId) {

                // Skip empty rows
                if (!$subjectId || !isset($request->dates[$key])) {
                    continue;
                }

                $date = $request->dates[$key];
                $startTime = $request->start_times[$key];
                $endTime = $request->end_times[$key];
                $room = $request->rooms[$key] ?? null;

                // --- VALIDATION: ROOM CONFLICT ---
                if (!empty($room)) {
                    $roomConflict = ExamSchedule::where('school_id', $schoolId)
                        ->where('exam_date', $date)
                        ->where('room', $room)
                        ->where(function ($q) use ($startTime, $endTime) {
                            $q->whereBetween('start_time', [$startTime, $endTime])
                                ->orWhereBetween('end_time', [$startTime, $endTime])
                                ->orWhere(function ($sub) use ($startTime, $endTime) {
                                    $sub->where('start_time', '<=', $startTime)
                                        ->where('end_time', '>=', $endTime);
                                });
                        })
                        ->exists();

                    if ($roomConflict) {
                        $conflicts[] = "Room $room is already booked on $date between $startTime and $endTime.";
                    }
                }

                // --- VALIDATION: CLASS CONFLICT (Same time for same class) ---
                // If section is specific, we check against that section OR 'all sections' (null). 
                // However, complexity arises if current schedule is for 'A' and existing is for 'null' (All). 
                // For simplicity: Check overlapping times for this Class ID.
                $classConflict = ExamSchedule::where('school_id', $schoolId)
                    ->where('class_id', $classId)
                    ->where('exam_date', $date)
                    ->where(function ($q) use ($startTime, $endTime) {
                        $q->whereBetween('start_time', [$startTime, $endTime])
                            ->orWhereBetween('end_time', [$startTime, $endTime])
                            ->orWhere(function ($sub) use ($startTime, $endTime) {
                                $sub->where('start_time', '<=', $startTime)
                                    ->where('end_time', '>=', $endTime);
                            });
                    })
                    ->exists();

                if ($classConflict) {
                    $conflicts[] = "Class already has an exam on $date between $startTime and $endTime.";
                }
            }
        }

        // Return with errors if conflicts found
        if (!empty($conflicts)) {
            return back()->withErrors(['conflicts' => $conflicts])->withInput();
        }

        // 3. Save Each Row if no conflicts
        if ($request->subjects) {
            foreach ($request->subjects as $key => $subjectId) {
                if (!$subjectId || !isset($request->dates[$key])) continue;

                ExamSchedule::create([
                    'school_id'    => $schoolId,
                    'class_id'     => $classId,
                    'term_id'      => $termId,
                    'subject_id'   => $subjectId,
                    'exam_date'    => $request->dates[$key],
                    'start_time'   => $request->start_times[$key],
                    'end_time'     => $request->end_times[$key],
                    'room'         => $request->rooms[$key] ?? null,
                    'section'      => $section,
                    'paper_type'   => $request->paper_types[$key] ?? 'Theory',
                    'supervisor_id' => $request->supervisors[$key] ?? null,
                    'total_marks'  => $request->total_marks[$key] ?? 100,
                    'passing_marks' => $request->passing_marks[$key] ?? 33,
                    'publish_status' => 'draft',
                ]);
            }
        }

        $routePrefix = $this->getRoutePrefix();
        return redirect()->route($routePrefix . '.exam-schedules.index', [
            'term_id' => $request->term_id,
            'class_id' => $request->class_id
        ])->with('success', 'Master Schedule Saved Successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExamSchedule $examSchedule)
    {
        $examSchedule->delete();
        return back()->with('success', 'Schedule deleted.');
    }


    /**
     * Publish the schedule for a specific class.
     */
    public function publish($class_id)
    {
        // Update is_published = 1
        ExamSchedule::where('class_id', $class_id)->update(['is_published' => true]);

        // Notification Logic
        $activeTerm = ExamTerm::where('is_active', true)->first();
        if ($activeTerm) {
            $students = Student::where('class_id', $class_id)->get();
            if ($students->count() > 0) {
                Notification::send($students, new ExamScheduleReleased($activeTerm->name));
            }
        }

        return redirect()->back()->with('success', 'Exam Schedule Published! Students can now see their slips.');
    }

    /**
     * View Admit Card (Admin Override)
     */
    public function viewAdmitCard($studentId)
    {
        $student = Student::findOrFail($studentId);
        $activeTerm = ExamTerm::where('is_active', true)->first();

        if (!$activeTerm) {
            return redirect()->back()->with('error', 'No active exam term found.');
        }

        // Fetch Schedule without "is_published" check (Admins can preview)
        $schedules = ExamSchedule::where('class_id', $student->class_id)
            ->where('term_id', $activeTerm->id)
            ->with('subject')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        return view('student.exams.admit_card', compact('student', 'activeTerm', 'schedules'));
    }
}
