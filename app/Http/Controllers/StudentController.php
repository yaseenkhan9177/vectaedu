<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    /**
     * Store a newly created student in storage.
     */
    public function store(Request $request)
    {
        // Validation (copied from/aligned with AdminController logic assumption)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant.students,email',
            'phone' => 'nullable|string',
            'parent_phone' => 'nullable|digits:11',
            'parent_email' => 'nullable|email',
            'gender' => 'required|in:male,female,other',
            'dob' => 'nullable|date',
            'transport_required' => 'nullable|in:yes,no',
            'transport_fee' => 'required_if:transport_required,yes|nullable|numeric|min:0',
         'transport_route_id' => 'required_if:transport_required,yes|nullable|exists:tenant.transport_routes,id',
        ]);

        $user = $request->user();
        // Fallback for different guards if not automatically resolved by request
        if (!$user) {
            if (Auth::guard('web')->check()) $user = Auth::guard('web')->user();
            elseif (Auth::guard('accountant')->check()) $user = Auth::guard('accountant')->user();
        }

        $schoolId = null;

        if ($user instanceof \App\Models\User && $user->role === 'admin') {
            $schoolId = $user->id;
        } elseif ($user instanceof \App\Models\Accountant) {
            $schoolId = $user->school_id;
        } elseif ($user instanceof \App\Models\Teacher) {
            $schoolId = $user->school_id;
        } else {
            // Fallback or error if unauthorized type attempts to create
            return redirect()->back()->withErrors(['error' => 'Unauthorized action.']);
        }

        // Custom Roll Number Generation
        // Format: {school_id}00{sequence}
        // Sequence starts at 1001. Max 1999.

        // Find last student for this school to determine sequence
        $studentQuery = Student::query();
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
            $studentQuery->where('school_id', $schoolId);
        }
        $lastStudent = $studentQuery->latest('id')->first();

        $sequence = 1001;

        if ($lastStudent && $lastStudent->roll_number) {
            // Extract sequence from roll number
            // Assumption: Roll number format is rigidly {school_id}00{sequence}
            // Length of school_id varies, but we know the structure.
            // Better approach: Store sequence separately? No, user asked to parse/increment.
            // We can try to extract the last 4 digits.
            // If school_id = 5, roll = 5001001. 
            // school_id . '00' is the prefix.
            $prefix = $schoolId . '00';
            if (strpos($lastStudent->roll_number, $prefix) === 0) {
                $lastSequence = (int) substr($lastStudent->roll_number, strlen($prefix));
                $sequence = $lastSequence + 1;
            }
        }

        if ($sequence > 1999) {
            return redirect()->back()->withErrors(['error' => 'School Capacity Full (Max 1999 Students)']);
        }

        $rollNumber = $schoolId . '00' . $sequence;

        // Logic to Link or Create Parent
        $parentPhone = $request->parent_phone;
        $parentId = null;

        if ($parentPhone) {
            // Check if parent exists
            $parentQuery = \App\Models\SchoolParent::where('phone', $parentPhone);
            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                $parentQuery->where('school_id', $schoolId);
            }
            $parent = $parentQuery->first();

            if ($parent) {
                $parentId = $parent->id;
                // Update existing parent email if provided and not set
                if (!$parent->email && $request->parent_email) {
                    $parent->email = $request->parent_email;
                    $parent->save();
                }
            } else {
                // Create new parent
                $newParent = new \App\Models\SchoolParent();
                if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                    $newParent->school_id = $schoolId;
                }
                $newParent->name = $request->parent_name ?? 'Parent';
                $newParent->email = $request->parent_email; // Save Email
                $newParent->phone = $parentPhone;
                $newParent->password = Hash::make($parentPhone); // Phone as password
                $newParent->save();
                $parentId = $newParent->id;
            }
        }

        // =========================================================
        // FAMILY SYSTEM: Auto-find or create family by father email
        // =========================================================
        $familyId = null;
        $fatherEmail = $request->input('father_email', $request->input('parent_email')); // Fallback to parent_email

        // If a specific family_id was passed from the AJAX popup choice, use it
        if ($request->filled('family_id')) {
            $familyId = $request->input('family_id');
        } elseif ($fatherEmail) {
            $family = \App\Models\Family::where('email', $fatherEmail)
                ->where('school_id', $schoolId)
                ->first();

            if (!$family) {
                $family = \App\Models\Family::create([
                    'family_code' => \App\Models\Family::generateCode(),
                    'father_name' => $request->input('father_name', $request->input('parent_name', 'Guardian')),
                    'email'       => $fatherEmail,
                    'phone'       => $request->input('father_phone', $request->input('parent_phone', '')),
                    'address'     => $request->input('father_address'),
                    'school_id'   => $schoolId,
                ]);
            }

            $familyId = $family->id;
        }

        // Store transport fee in student table as simple reference (legacy/fallback)
        $transportFee = 0;
        if ($request->input('transport_required') === 'yes') {
            $transportFee = $request->input('transport_fee', 0);
        }


        // Generate password from class_id and roll_number as requested
        $plainPassword = $request->class_id . $rollNumber;

        $studentData = [
            'name'          => $request->name,
            'email'         => $request->email,
            'gender'        => $request->gender,
            'dob'           => $request->dob,
            'password'      => Hash::make($plainPassword),
            'plain_password'=> $plainPassword,
            'phone'         => $request->phone,
            'roll_number'   => $rollNumber,
            'status'        => 'approved', // Auto-approve if created by Admin
            'parent_phone'  => $request->parent_phone,
            'parent_name'   => $request->parent_name, // Keep legacy for now
            'parent_id'     => $parentId,             // Link new ID
            'class_id'      => $request->class_id,
            'department'    => $request->department,
            'transport_fee' => $transportFee,
            'family_id'     => $familyId,             // Family System
        ];
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
            $studentData['school_id'] = $schoolId;
        }
        $student = Student::create($studentData);


        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/students'), $filename);
            $student->profile_image = $filename;
            $student->save();
        }

        // Save Transport Details (New Table)
        if ($request->input('transport_required') === 'yes' && $request->transport_route_id) {
            $startMonth = $request->transport_start_month ? $request->transport_start_month . '-01' : now()->toDateString();

            \App\Models\StudentTransport::create([
                'student_id' => $student->id,
                'transport_route_id' => $request->transport_route_id,
                'pickup_point' => $request->pickup_point,
                'monthly_fee' => $request->transport_fee,
                'start_month' => $startMonth,
                'status' => 'active'
            ]);
        }

        if ($user instanceof \App\Models\Accountant) {
            return redirect()->route('accountant.students.index')->with('success', 'Student created successfully. Roll Number: ' . $rollNumber);
        }

        return redirect()->route('admin.students')->with('success', 'Student created successfully. Roll Number: ' . $rollNumber);
    }

    public function searchParent(Request $request)
    {
        $request->validate([
            'phone' => 'required|string'
        ]);

        $parent = \App\Models\SchoolParent::where('phone', $request->phone)->first();

        if ($parent) {
            return response()->json([
                'status' => 'success',
                'parent' => [
                    'name' => $parent->name,
                    'email' => $parent->email,
                    'id' => $parent->id
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Parent not found'
        ]);
    }
    public function showImportForm()
    {
        return view('school admin.students.import');
    }

    /**
     * Download a sample CSV file for student bulk import.
     * Uses pure PHP — no PhpSpreadsheet dependency.
     */
    public function downloadSampleCsv()
    {
        $columns = [
            'full_name', 'email', 'gender', 'date_of_birth',
            'phone', 'parent_name', 'parent_phone', 'parent_email', 'password',
        ];

        $sampleRows = [
            ['Ahmed Khan',   'ahmed.khan@example.com',   'Male',   '2010-05-15', '03001234567', 'Muhammad Khan', '03001234568', 'mkhan@example.com',  'Ahmed@1234'],
            ['Sara Ali',     'sara.ali@example.com',     'Female', '2011-03-20', '03009876543', 'Ali Hassan',    '03009876544', 'ali.h@example.com',  'Sara@5678'],
            ['Usman Ahmed',  'usman.ahmed@example.com',  'Male',   '2009-11-10', '03005555555', 'Ahmed Raza',    '03005555556', 'araza@example.com',  'Usman@9012'],
        ];

        return response()->streamDownload(function () use ($columns, $sampleRows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens it correctly
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $columns);
            foreach ($sampleRows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'students_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Parse uploaded CSV file and redirect to preview page.
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return redirect()->back()->with('error', 'Could not open the uploaded file.');
        }

        // Read header
        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return redirect()->back()->with('error', 'CSV file is empty.');
        }
        // Strip BOM if present
        $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeaders);

        // Map column names to indices
        $col = array_flip($headers);
        $required = ['full_name', 'email', 'gender', 'parent_phone', 'password'];
        $missing = array_diff($required, array_keys($col));
        if ($missing) {
            fclose($handle);
            return redirect()->back()->with('error', 'Missing required columns: ' . implode(', ', $missing));
        }

        $rows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            // Skip empty rows
            if (count(array_filter(array_map('trim', $row))) === 0) {
                continue;
            }

            $get = fn(string $key) => isset($col[$key]) ? trim($row[$col[$key]] ?? '') : '';

            $fullName    = $get('full_name');
            $email       = $get('email');
            $gender      = $get('gender');
            $dob         = $get('date_of_birth');
            $phone       = $get('phone');
            $parentName  = $get('parent_name');
            $parentPhone = $get('parent_phone');
            $parentEmail = $get('parent_email');
            $password    = $get('password');

            // Per-row validation
            $rowErrors = [];

            if ($fullName === '') {
                $rowErrors[] = 'Full name is required';
            }
            if ($email === '') {
                $rowErrors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Invalid email format';
            } elseif (Student::where('email', $email)->exists()) {
                $rowErrors[] = 'Email already registered';
            }
            if (!in_array(strtolower($gender), ['male', 'female'], true)) {
                $rowErrors[] = 'Gender must be Male or Female';
            }
            if ($parentPhone === '') {
                $rowErrors[] = 'Parent phone is required';
            }
            if ($password === '') {
                $rowErrors[] = 'Password is required';
            }

            $isValid = count($rowErrors) === 0;

            $rows[] = [
                'full_name'     => $fullName,
                'email'         => $email,
                'gender'        => $gender,
                'date_of_birth' => $dob,
                'phone'         => $phone,
                'parent_name'   => $parentName,
                'parent_phone'  => $parentPhone,
                'parent_email'  => $parentEmail,
                'password'      => $password,
                'is_valid'      => $isValid,
                'error'         => $isValid ? null : implode('; ', $rowErrors),
            ];
        }

        fclose($handle);

        session(['import_preview' => $rows]);

        return redirect()->route('students.import-preview-page');
    }

    /**
     * Show preview screen for editing before import save.
     */
    public function showPreview()
    {
        $rows = session('import_preview');
        if (empty($rows)) {
            return redirect()->route('admin.students')->with('error', 'No preview data found. Please upload the CSV file again.');
        }

        return view('school admin.students.import_preview', compact('rows'));
    }

    /**
     * Re-validate rows and save valid records to the database.
     */
    public function importSave(Request $request)
    {
        $rows = $request->input('rows');
        if (empty($rows) || !is_array($rows)) {
            return redirect()->route('admin.students')->with('error', 'No data submitted.');
        }

        $schoolId = Auth::id();
        if (!$schoolId) {
            // accountant guard support
            $user = Auth::user();
            if (!$user) {
                if (Auth::guard('accountant')->check()) {
                    $schoolId = Auth::guard('accountant')->user()->school_id;
                }
            } else {
                $schoolId = $user->id;
            }
        }

        if (!$schoolId) {
            return redirect()->route('admin.students')->with('error', 'Unauthorized Action.');
        }

        // Determine starting sequence for roll numbers
        $studentQuery = Student::query();
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
            $studentQuery->where('school_id', $schoolId);
        }
        $lastStudent = $studentQuery->latest('id')->first();
        $sequence = 1001;
        if ($lastStudent && $lastStudent->roll_number) {
            $prefix = $schoolId . '00';
            if (str_starts_with((string) $lastStudent->roll_number, $prefix)) {
                $lastSeq = (int) substr($lastStudent->roll_number, strlen($prefix));
                $sequence = $lastSeq + 1;
            }
        }

        $imported = 0;

        foreach ($rows as $row) {
            $fullName    = trim($row['full_name'] ?? '');
            $email       = trim($row['email'] ?? '');
            $gender      = trim($row['gender'] ?? '');
            $dob         = trim($row['date_of_birth'] ?? '');
            $phone       = trim($row['phone'] ?? '');
            $parentName  = trim($row['parent_name'] ?? '');
            $parentPhone = trim($row['parent_phone'] ?? '');
            $parentEmail = trim($row['parent_email'] ?? '');
            $password    = trim($row['password'] ?? '');

            // Server-side validation
            if ($fullName === '' || $email === '' || $parentPhone === '' || $password === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (Student::where('email', $email)->exists()) {
                continue;
            }
            if (!in_array(strtolower($gender), ['male', 'female'], true)) {
                continue;
            }

            // Create records
            try {
                $rollNumber = $schoolId . '00' . $sequence;
                $sequence++;

                // Parent lookup or create
                $parentId = null;
                $parentQuery = \App\Models\SchoolParent::where('phone', $parentPhone);
                if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                    $parentQuery->where('school_id', $schoolId);
                }
                $parent = $parentQuery->first();

                if ($parent) {
                    $parentId = $parent->id;
                    if (!$parent->email && $parentEmail) {
                        $parent->email = $parentEmail;
                        $parent->save();
                    }
                } else {
                    $parentData = [
                        'name'     => $parentName ?: 'Parent',
                        'email'    => $parentEmail ?: null,
                        'phone'    => $parentPhone,
                        'password' => Hash::make($parentPhone),
                    ];
                    if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                        $parentData['school_id'] = $schoolId;
                    }
                    $parent = \App\Models\SchoolParent::create($parentData);
                    $parentId = $parent->id;
                }

                // Family lookup or create
                $familyId = null;
                if ($parentEmail) {
                    $family = \App\Models\Family::where('email', $parentEmail)
                        ->where('school_id', $schoolId)
                        ->first();

                    if (!$family) {
                        $family = \App\Models\Family::create([
                            'family_code' => \App\Models\Family::generateCode(),
                            'father_name' => $parentName ?: 'Guardian',
                            'email'       => $parentEmail,
                            'phone'       => $parentPhone,
                            'school_id'   => $schoolId,
                        ]);
                    }
                    $familyId = $family->id;
                }

                // Create student
                $studentData = [
                    'name'           => $fullName,
                    'email'          => $email,
                    'gender'         => strtolower($gender),
                    'dob'            => $dob ?: null,
                    'password'       => Hash::make($password),
                    'plain_password' => $password,
                    'phone'          => $phone ?: null,
                    'roll_number'    => $rollNumber,
                    'status'         => 'approved',
                    'parent_phone'   => $parentPhone,
                    'parent_name'    => $parentName ?: null,
                    'parent_id'      => $parentId,
                    'family_id'      => $familyId,
                ];
                if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
                    $studentData['school_id'] = $schoolId;
                }

                Student::create($studentData);
                $imported++;
            } catch (\Throwable $e) {
                // Skip silently or log error
            }
        }

        session()->forget('import_preview');

        return redirect()->route('admin.students')->with('success', $imported . ' student(s) imported successfully.');
    }
}
