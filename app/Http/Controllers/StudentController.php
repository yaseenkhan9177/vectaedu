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

    public function downloadSample()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1: Headers
        $headers = [
            'name', 'email', 'gender', 'dob', 'phone', 
            'parent_name', 'parent_phone', 'parent_email', 
            'class_name', 'roll_number'
        ];
        
        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
        }

        // Rows 2-4: 3 sample rows
        $sampleData = [
            [
                'Ahmed Khan', 'ahmed@example.com', 'male', '2010-05-15', '03001234567', 
                'Muhammad Khan', '03001234568', 'father@example.com', 'class 1 A', '1001'
            ],
            [
                'Sara Ali', 'sara@example.com', 'female', '2011-03-20', '03009876543', 
                'Ali Hassan', '03009876544', 'ali@example.com', 'class 1 A', '1002'
            ],
            [
                'Usman Ahmed', 'usman@example.com', 'male', '2009-11-10', '03005555555', 
                'Ahmed Raza', '03005555556', 'raza@example.com', 'class 2 B', '2001'
            ]
        ];

        foreach ($sampleData as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $val) {
                $cell = $sheet->getCellByColumnAndRow($colIndex + 1, $rowIndex + 2);
                $cell->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }

        // Autofit column widths
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, 'student_import_sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        $schoolId = Auth::id();
        if (!$schoolId) {
            return back()->with('error', 'Unauthorized.');
        }

        $file = $request->file('file');
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to read Excel file: ' . $e->getMessage());
        }

        // Get the starting sequence for roll numbers
        $studentQuery = \App\Models\Student::query();
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
            $studentQuery->where('school_id', $schoolId);
        }
        $lastStudent = $studentQuery->latest('id')->first();

        $sequence = 1001;

        if ($lastStudent && $lastStudent->roll_number) {
            $prefix = $schoolId . '00';
            if (strpos($lastStudent->roll_number, $prefix) === 0) {
                $lastSequence = (int) substr($lastStudent->roll_number, strlen($prefix));
                $sequence = $lastSequence + 1;
            }
        }

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue; // Skip header row
            }

            // Check if the row is entirely empty
            $isEmpty = true;
            foreach ($row as $cell) {
                if ($cell !== null && trim($cell) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) {
                continue;
            }

            $rowNum = $index + 1;

            $data = [
                'name' => trim($row[0] ?? ''),
                'email' => trim($row[1] ?? ''),
                'gender' => trim(strtolower($row[2] ?? '')),
                'dob' => trim($row[3] ?? ''),
                'phone' => trim($row[4] ?? ''),
                'parent_name' => trim($row[5] ?? ''),
                'parent_phone' => trim($row[6] ?? ''),
                'parent_email' => trim($row[7] ?? ''),
                'class_name' => trim($row[8] ?? ''),
                'roll_number' => trim($row[9] ?? ''),
            ];

            $validator = \Illuminate\Support\Facades\Validator::make($data, [
                'name' => 'required',
                'email' => 'required|email|unique:tenant.students,email',
                'gender' => 'required|in:male,female',
                'class_name' => 'required|exists:tenant.school_classes,name',
                'parent_phone' => 'required',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNum}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            try {
                // Find class
                $class = \App\Models\SchoolClass::where('name', $data['class_name'])->first();

                // Roll number
                $rollNumber = $data['roll_number'];
                if (empty($rollNumber)) {
                    $rollNumber = $schoolId . '00' . $sequence;
                    $sequence++;
                }

                // Password generation: class_id + roll_number
                $plainPassword = $class->id . $rollNumber;

                // Parent lookup or create
                $parentPhone = $data['parent_phone'];
                $parentId = null;
                if ($parentPhone) {
                    $parentQuery = \App\Models\SchoolParent::where('phone', $parentPhone);
                    if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                        $parentQuery->where('school_id', $schoolId);
                    }
                    $parent = $parentQuery->first();

                    if ($parent) {
                        $parentId = $parent->id;
                        if (!$parent->email && $data['parent_email']) {
                            $parent->email = $data['parent_email'];
                            $parent->save();
                        }
                    } else {
                        $parentData = [
                            'name' => $data['parent_name'] ?: 'Parent',
                            'email' => $data['parent_email'] ?: null,
                            'phone' => $parentPhone,
                            'password' => \Illuminate\Support\Facades\Hash::make($parentPhone),
                        ];
                        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('parents', 'school_id')) {
                            $parentData['school_id'] = $schoolId;
                        }
                        $parent = \App\Models\SchoolParent::create($parentData);
                        $parentId = $parent->id;
                    }
                }

                // Family lookup or create
                $familyId = null;
                $fatherEmail = $data['parent_email'];
                if ($fatherEmail) {
                    $family = \App\Models\Family::where('email', $fatherEmail)
                        ->where('school_id', $schoolId)
                        ->first();

                    if (!$family) {
                        $family = \App\Models\Family::create([
                            'family_code' => \App\Models\Family::generateCode(),
                            'father_name' => $data['parent_name'] ?: 'Guardian',
                            'email'       => $fatherEmail,
                            'phone'       => $data['parent_phone'] ?: '',
                            'school_id'   => $schoolId,
                        ]);
                    }
                    $familyId = $family->id;
                }

                // Create student
                $studentData = [
                    'name'          => $data['name'],
                    'email'         => $data['email'],
                    'gender'        => $data['gender'],
                    'dob'           => $data['dob'] ?: null,
                    'password'      => \Illuminate\Support\Facades\Hash::make($plainPassword),
                    'plain_password'=> $plainPassword,
                    'phone'         => $data['phone'] ?: null,
                    'roll_number'   => $rollNumber,
                    'status'        => 'approved',
                    'parent_phone'  => $data['parent_phone'],
                    'parent_name'   => $data['parent_name'] ?: null,
                    'parent_id'     => $parentId,
                    'class_id'      => $class->id,
                    'family_id'     => $familyId,
                ];
                if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('students', 'school_id')) {
                    $studentData['school_id'] = $schoolId;
                }
                \App\Models\Student::create($studentData);

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: Exception occurred: " . $e->getMessage();
            }
        }

        return redirect()->back()->with([
            'import_success_count' => $successCount,
            'import_errors' => $errors,
            'import_completed' => true
        ]);
    }
}
