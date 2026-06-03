<?php

namespace App\Http\Controllers;

use App\Models\SchoolParent;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class ParentController extends Controller
{
    public function index()
    {
        $parents = SchoolParent::withCount('students')->get();
        return view('admin.parents.index', compact('parents'));
    }

    public function create()
    {
        return view('admin.parents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:parents,phone', // Unique global or scoped? Schema has component unique index.
            // Ideally unique:parents,phone,NULL,id,school_id,Auth::id() ... but simplified for now
            'password' => 'required|string|min:6',
            'email' => 'nullable|email',
            'student_roll_numbers' => 'nullable|array',
            'student_roll_numbers.*' => 'nullable|string|exists:students,roll_number',
        ]);

        $parent = new SchoolParent();
        $parent->name = $request->name;
        $parent->phone = $request->phone;
        $parent->email = $request->email;
        $parent->password = Hash::make($request->password);
        $parent->address = $request->address;
        $parent->cnic = $request->cnic;
        $parent->save();

        // Automatic Student Linking
        if ($request->filled('student_roll_numbers')) {
            $linkedCount = 0;
            $notFound = [];

            foreach ($request->student_roll_numbers as $rollNumber) {
                if (empty($rollNumber)) continue;

                $student = Student::where('roll_number', $rollNumber)->first();

                if ($student) {
                    $student->parent_id = $parent->id;
                    $student->parent_phone = $parent->phone;
                    $student->parent_name = $parent->name;
                    $student->save();
                    $linkedCount++;
                } else {
                    $notFound[] = $rollNumber;
                }
            }

            $message = 'Parent created successfully.';
            if ($linkedCount > 0) {
                $message .= " Linked $linkedCount student(s).";
            }
            if (count($notFound) > 0) {
                $message .= " Could not find roll number(s): " . implode(', ', $notFound);
            }

            return redirect()->route('admin.parents.index')->with('success', $message);
        }

        return redirect()->route('admin.parents.index')->with('success', 'Parent created successfully.');
    }

    // API for Select2 searching
    public function search(Request $request)
    {
        $search = $request->get('q');
        $parents = SchoolParent::where('name', 'like', "%$search%")
            ->orWhere('phone', 'like', "%$search%")
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($parents);
    }

    public function destroy($id)
    {
        $parent = SchoolParent::findOrFail($id);
        
        // Remove references from students linked to this parent
        Student::where('parent_id', $parent->id)->update([
            'parent_id' => null,
            'parent_phone' => null,
            'parent_name' => null
        ]);

        $parent->delete();

        return redirect()->back()->with('success', 'Parent deleted successfully.');
    }
}
