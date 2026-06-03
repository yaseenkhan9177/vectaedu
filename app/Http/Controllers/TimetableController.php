<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;

use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    public function index()
    {
        $timetableData = Timetable::with(['schoolClass', 'subject', 'teacher'])
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        return view('school admin.timetable.index', compact('timetableData'));
    }

    public function create()
    {
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $teachers = Teacher::select('id', 'name', 'subject', DB::raw("CONCAT(name, ' - ', IFNULL(subject, 'No Subject')) as full_label"))->get();

        return view('school admin.timetable.create', compact('classes', 'subjects', 'teachers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'school_class_id' => 'required|exists:tenant.school_classes,id',
            'timetable' => 'required|array',
            'timetable.*.subject_id' => 'required|exists:tenant.subjects,id',
            'timetable.*.teacher_id' => 'required|exists:tenant.teachers,id',
            'timetable.*.day' => 'nullable|string',
            'timetable.*.start_time' => 'nullable|date_format:H:i',
            'timetable.*.end_time' => 'nullable|date_format:H:i|after:timetable.*.start_time',
        ]);

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $errors = [];

        foreach ($request->timetable as $entry) {
            $inputDays = [];
            $startTime = $entry['start_time'] ?? null;
            $endTime = $entry['end_time'] ?? null;
            $teacherId = $entry['teacher_id'];
            $subjectId = $entry['subject_id'];
            $classId = $request->school_class_id;

            // Skip incomplete entries
            if (!$startTime || !$endTime || !$teacherId || !$subjectId) {
                continue;
            }

            // Determine days to process
            if ($request->has('repeat_all_days')) {
                $inputDays = $days;
            } else {
                if (isset($entry['day'])) {
                    $inputDays[] = $entry['day'];
                }
            }

            foreach ($inputDays as $day) {
                // 1. Teacher Conflict Check
                $teacherConflict = Timetable::with('schoolClass')
                    ->where('teacher_id', $teacherId)
                    ->where('day', $day)
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->where(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        });
                    })
                    ->first();

                if ($teacherConflict) {
                    $teacher = Teacher::find($teacherId);
                    $className = $teacherConflict->schoolClass->name ?? 'Unknown Class';
                    $errors[] = "Failed for $day: Teacher {$teacher->name} is already busy in Class {$className} ($startTime - $endTime).";
                    continue; // Skip trying to save this day
                }

                // 2. Class Conflict Check
                $classConflict = Timetable::where('school_class_id', $classId)
                    ->where('day', $day)
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->where(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        });
                    })
                    ->first();

                if ($classConflict) {
                    $errors[] = "Failed for $day: Class already has a subject assigned ($startTime - $endTime).";
                    continue; // Skip trying to save this day
                }

                Timetable::create([
                    'school_class_id' => $classId,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                    'day' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'school_id' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            }
        }

        if (count($errors) > 0) {
            return redirect()->route('admin.timetable.create')->with('success', 'Timetable processing complete with some errors.')->withErrors($errors);
        }

        return redirect()->route('admin.timetable.create')->with('success', 'Timetable created successfully!');
    }
}
