<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    // -------------------------------------------------------------------------
    // Family List - works for both Admin and Accountant
    // -------------------------------------------------------------------------
    public function index()
    {
        $families = Family::withCount('students')
            ->latest()
            ->paginate(20);

        $guard = $this->activeGuard();

        return view('shared.families.index', compact('families', 'guard'));
    }

    // -------------------------------------------------------------------------
    // Family Detail Page
    // -------------------------------------------------------------------------
    public function show($id)
    {
        $family = Family::with(['students.schoolClass'])->findOrFail($id);
        $guard  = $this->activeGuard();

        return view('shared.families.show', compact('family', 'guard'));
    }

    // -------------------------------------------------------------------------
    // AJAX: Check if a family exists for a given father email + school
    // Called from the student registration form on father_email blur
    // -------------------------------------------------------------------------
    public function checkFamily(Request $request)
    {
        $request->validate(['father_email' => 'required|email']);

        // Determine school_id from active guard
        $user     = $request->user();
        if (!$user) {
            if (Auth::guard('web')->check())        $user = Auth::guard('web')->user();
            elseif (Auth::guard('accountant')->check()) $user = Auth::guard('accountant')->user();
        }

        $schoolId = $user instanceof \App\Models\User ? $user->id : ($user->school_id ?? null);

        if (!$schoolId) {
            return response()->json(['exists' => false]);
        }

        $family = Family::where('email', $request->father_email)
            ->where('school_id', $schoolId)
            ->with(['students.schoolClass'])
            ->first();

        if (!$family) {
            return response()->json(['exists' => false]);
        }

        $children = $family->students->map(function ($student) {
            return [
                'name'  => $student->name,
                'class' => optional($student->schoolClass)->name ?? 'N/A',
            ];
        });

        return response()->json([
            'exists'      => true,
            'family_id'   => $family->id,
            'family_code' => $family->family_code,
            'father_name' => $family->father_name,
            'children'    => $children,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: identify active guard name for view routing
    // -------------------------------------------------------------------------
    private function activeGuard(): string
    {
        if (Auth::guard('web')->check())        return 'admin';
        if (Auth::guard('accountant')->check()) return 'accountant';
        return 'admin';
    }

    // -------------------------------------------------------------------------
    // Delete a family and nullify references on linked students
    // -------------------------------------------------------------------------
    public function destroy($id)
    {
        $family = Family::findOrFail($id);

        // Unlink students belonging to this family
        \App\Models\Student::where('family_id', $family->id)->update(['family_id' => null]);

        $family->delete();

        return redirect()->back()->with('success', 'Family deleted successfully.');
    }
}
