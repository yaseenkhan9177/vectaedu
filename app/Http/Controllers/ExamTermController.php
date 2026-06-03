<?php

namespace App\Http\Controllers;

use App\Models\ExamTerm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamTermController extends Controller
{
    public function index()
    {
        // Admin View: List all terms
        $terms = ExamTerm::with('classes')->orderBy('start_date', 'desc')->get();
        // Get all classes for the selection
        $classes = \App\Models\SchoolClass::all();
        return view('admin.exam_terms.index', compact('terms', 'classes'));
    }

    public function store(Request $request)
    {
       $request->validate([
    'name' => [
        'required',
        'string',
        Rule::unique('tenant.exam_terms')->where(function ($query) {
            return $query->where('school_id', \Illuminate\Support\Facades\Auth::id());
        }),
    ],
    'start_date' => 'required|date',
    'end_date' => 'required|date|after_or_equal:start_date',
    'rules' => 'nullable|string',
    'class_ids' => 'required|array',
    'class_ids.*' => 'exists:tenant.school_classes,id',
]);

        // Overlap Check (Multi-Tenant aware)
        $overlap = ExamTerm::where('school_id', \Illuminate\Support\Facades\Auth::id())
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            })
            ->exists();

        if ($overlap) {
            return back()->withErrors(['start_date' => 'This term overlaps with an existing term.'])->withInput();
        }

        $term = ExamTerm::create([
            'school_id' => \Illuminate\Support\Facades\Auth::id(),
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
            'rules' => $request->rules,
        ]);

        // Attach classes
        $term->classes()->sync($request->class_ids);

        return back()->with('success', 'Exam Term created successfully.');
    }

    public function edit(ExamTerm $examTerm)
    {
        $classes = \App\Models\SchoolClass::all();
        $examTerm->load('classes');
        return view('admin.exam_terms.edit', compact('examTerm', 'classes'));
    }

    public function update(Request $request, ExamTerm $examTerm)
    {
        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'rules' => 'nullable|string',
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:school_classes,id',
        ]);

        $examTerm->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'rules' => $request->rules,
        ]);

        $examTerm->classes()->sync($request->class_ids);

        return back()->with('success', 'Exam Term updated successfully.');
    }

    public function destroy(ExamTerm $examTerm)
    {
        $examTerm->delete();
        return back()->with('success', 'Exam Term deleted successfully.');
    }
}
