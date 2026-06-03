<?php

namespace App\Http\Controllers;

use App\Models\StudentFee;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentFeeController extends Controller
{
    public function index(Request $request)
    {
        // Query to get unique invoices (grouped by invoice_no)
        $query = StudentFee::query()
            ->select(
                'invoice_no',
                'student_id',
                'month',
                'due_date',
                DB::raw('SUM(amount) as db_total_amount'),
                DB::raw('SUM(late_fee) as total_late_fee'),
                DB::raw('SUM(discount) as total_discount'),
                DB::raw('COUNT(id) as items_count'),
                DB::raw('MIN(status) as status') // Simple aggregation, we'll refine status check below if needed
            )
            ->with(['student.schoolClass']) // Eager load student
            ->groupBy('invoice_no', 'student_id', 'month', 'due_date');

        if ($request->has('class_id') && $request->class_id) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'pending') {
                $query->whereIn('status', ['unpaid', 'partial']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('invoice_no', 'like', "%{$search}%");
        }

        $invoices = $query->latest('due_date')->paginate(20);

        // Populate specific fee subtypes for each invoice to display in the view (for the breakdown)
        // We can do this by fetching all fees for these visible invoice numbers
        $invoiceNumbers = $invoices->pluck('invoice_no')->filter()->toArray();

        $allFees = collect();
        if (!empty($invoiceNumbers)) {
            $allFees = StudentFee::whereIn('invoice_no', $invoiceNumbers)
                ->with('feeStructure.feeCategory', 'payments')
                ->get()
                ->groupBy('invoice_no');
        }

        // Attach the collection of fees to each invoice object
        foreach ($invoices as $invoice) {
            if ($invoice->invoice_no && isset($allFees[$invoice->invoice_no])) {
                $invoice->sub_fees = $allFees[$invoice->invoice_no];
            } else {
                // Fallback: If invoice_no is null or not found, match by grouping criteria
                $query = StudentFee::where('student_id', $invoice->student_id)
                    ->where('month', $invoice->month)
                    ->where('due_date', $invoice->due_date)
                    ->with('feeStructure.feeCategory', 'payments');

                if ($invoice->invoice_no) {
                    $query->where('invoice_no', $invoice->invoice_no);
                } else {
                    $query->whereNull('invoice_no');
                }

                $invoice->sub_fees = $query->get();
            }

            // Refine Status Logic
            if ($invoice->sub_fees->isNotEmpty()) {
                $statuses = $invoice->sub_fees->pluck('status')->unique();
                if ($statuses->contains('partial')) {
                    $invoice->status = 'partial';
                } elseif ($statuses->contains('unpaid')) {
                    if ($statuses->contains('paid')) {
                        $invoice->status = 'partial'; // Mixed
                    } else {
                        $invoice->status = 'unpaid';
                    }
                } else {
                    $invoice->status = 'paid';
                }

                // Recalculate totals
                $invoice->final_total = $invoice->sub_fees->sum('amount');
                $invoice->total_late_fee = $invoice->sub_fees->sum('late_fee');
                $invoice->total_discount = $invoice->sub_fees->sum('discount');
                $invoice->total_paid = $invoice->sub_fees->flatMap->payments->sum('amount_paid');
            } else {
                // If no sub fees found, rely on DB aggregates but initialize total_paid
                $invoice->final_total = $invoice->db_total_amount;

                if (!isset($invoice->total_paid)) {
                    $invoice->total_paid = 0;
                }
            }
        }

        $classes = SchoolClass::orderBy('name')->get(['id', 'name']);
        $feeCategories = \App\Models\FeeCategory::orderBy('name')->get(['id', 'name']);

        if (request()->routeIs('admin.*')) {
            return view('school admin.fees.collect.index', compact('invoices', 'classes', 'feeCategories'));
        }
        return view('accountant.fees.collect.index', compact('invoices', 'classes', 'feeCategories'));
    }
    public function create()
    {
        $classes = SchoolClass::orderBy('name')->get(['id', 'name']);
        $feeStructures = FeeStructure::with('feeCategory')->get();
        // Limit initial load to 100 latest students to prevent memory exhaustion in large schools. 
        // Search functionality in the view should ideally handle finding others via AJAX.
        $students = Student::with('schoolClass')->orderBy('name')->take(100)->get();

        if (request()->routeIs('admin.*')) {
            return view('school admin.fees.collect.create', compact('classes', 'feeStructures', 'students'));
        }
        return view('accountant.fees.collect.create', compact('classes', 'feeStructures', 'students'));
    }

    public function storeSingle(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'fee_structure_id' => 'required|exists:tenant.fee_structures,id',
            'month' => 'required|date_format:Y-m',
            'due_date' => 'required|date',
            'base_amount' => 'required|numeric|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'exam_fee' => 'nullable|numeric|min:0',
            'transport_fee' => 'nullable|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $invoiceNo = 'INV-' . strtoupper(uniqid());

        StudentFee::create([
            'student_id' => $request->student_id,
            'fee_structure_id' => $request->fee_structure_id,
            'invoice_no' => $invoiceNo,
            'month' => $request->month . '-01',
            'amount' => $request->base_amount,
            'due_date' => $request->due_date,
            'status' => 'unpaid',
            'admission_fee' => $request->admission_fee ?? 0,
            'exam_fee' => $request->exam_fee ?? 0,
            'transport_fee' => $request->transport_fee ?? 0,
            'late_fee' => $request->late_fee ?? 0,
            'discount' => $request->discount ?? 0,
            'note' => $request->note,
        ]);

        return redirect()->route(request()->routeIs('admin.*') ? 'admin.fees.collect.index' : 'accountant.fees.collect.index')
            ->with('success', 'Fee record created and Fee Card generated successfully.');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'month' => 'required|date_format:Y-m',
            'due_date' => 'required|date',
            'categories' => 'required|array', // Validate multi-select
            'categories.*' => 'exists:fee_categories,id',
        ]);

        // Get structures matching class AND selected categories
        $structures = FeeStructure::where('class_id', $request->class_id)
            ->whereIn('fee_category_id', $request->categories)
            ->get();

        if ($structures->isEmpty()) {
            return back()->with('error', 'No fee structures found for selected categories.');
        }

        // Logic for Admission Fee Category ID
        $admissionCategory = \App\Models\FeeCategory::where('name', 'like', '%Admission%')->first();
        $admissionCategoryId = $admissionCategory ? $admissionCategory->id : null;

        // Logic for Transport Fee Category/Structure Retrieval
        $transportCategory = \App\Models\FeeCategory::where('name', 'like', '%Transport%')->first();
        $transportStructure = null;
        if ($transportCategory) {
            $transportStructure = \App\Models\FeeStructure::where('fee_category_id', $transportCategory->id)
                ->where(function ($q) use ($request) {
                    $q->where('class_id', $request->class_id)
                        ->orWhereNull('class_id');
                })
                ->orderBy('class_id', 'desc')
                ->first();
        }

        $processedCount = 0;
        $smsService = new \App\Services\SmsService();

        // Process students in chunks of 50 to prevent memory exhaustion
        Student::where('class_id', $request->class_id)->chunk(50, function ($students) use ($structures, $request, $admissionCategoryId, $transportStructure, $smsService, &$processedCount) {
            DB::transaction(function () use ($students, $structures, $request, $admissionCategoryId, $transportStructure, $smsService, &$processedCount) {
                $studentIds = $students->pluck('id')->toArray();

                // 1. Existing Invoices for this batch
                $existingFees = StudentFee::whereIn('student_id', $studentIds)
                    ->where('month', $request->month)
                    ->whereNotNull('invoice_no')
                    ->get()
                    ->keyBy('student_id');

                // 2. Existing Admission Fees for this batch
                $alreadyPaidAdmissionIds = [];
                if ($admissionCategoryId) {
                    $alreadyPaidAdmissionIds = StudentFee::whereIn('student_id', $studentIds)
                        ->whereHas('feeStructure', function ($q) use ($admissionCategoryId) {
                            $q->where('fee_category_id', $admissionCategoryId);
                        })
                        ->pluck('student_id')
                        ->toArray();
                }

                // 3. Existing Specific Fees for this batch
                $existingSelectedFees = StudentFee::whereIn('student_id', $studentIds)
                    ->whereIn('fee_structure_id', $structures->pluck('id'))
                    ->where('month', $request->month)
                    ->get()
                    ->map(function ($fee) {
                        return $fee->student_id . '_' . $fee->fee_structure_id;
                    })
                    ->toArray();

                // 4. Active Transport Records for this batch
                $activeTransports = [];
                if ($transportStructure) {
                    $activeTransports = \App\Models\StudentTransport::whereIn('student_id', $studentIds)
                        ->where('status', 'active')
                        ->where(function ($q) use ($request) {
                            $q->whereNull('start_month')
                                ->orWhereRaw("DATE_FORMAT(start_month, '%Y-%m') <= ?", [$request->month]);
                        })
                        ->get()
                        ->keyBy('student_id');
                }

                $batchFees = [];
                $now = now();

                foreach ($students as $student) {
                    $invoiceNo = isset($existingFees[$student->id])
                        ? $existingFees[$student->id]->invoice_no
                        : 'INV-' . str_replace('-', '', $request->month) . '-' . $student->id . '-' . rand(1000, 9999);

                    foreach ($structures as $structure) {
                        if ($admissionCategoryId && $structure->fee_category_id == $admissionCategoryId) {
                            if (in_array($student->id, $alreadyPaidAdmissionIds)) {
                                continue;
                            }
                        }

                        $feeKey = $student->id . '_' . $structure->id;
                        if (!in_array($feeKey, $existingSelectedFees)) {
                            $feeData = [
                                'student_id' => $student->id,
                                'fee_structure_id' => $structure->id,
                                'month' => $request->month,
                                'amount' => $structure->amount,
                                'due_date' => $request->due_date,
                                'status' => 'unpaid',
                                'invoice_no' => $invoiceNo,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_fees', 'school_id')) {
                                $feeData['school_id'] = $student->school_id;
                            }
                            $batchFees[] = $feeData;
                            $existingSelectedFees[] = $feeKey;
                        }
                    }

                    if (isset($activeTransports[$student->id]) && $transportStructure) {
                        $transportKey = $student->id . '_' . $transportStructure->id;
                        if (!in_array($transportKey, $existingSelectedFees)) {
                            $feeData = [
                                'student_id' => $student->id,
                                'fee_structure_id' => $transportStructure->id,
                                'month' => $request->month,
                                'amount' => $activeTransports[$student->id]->monthly_fee,
                                'due_date' => $request->due_date,
                                'status' => 'unpaid',
                                'invoice_no' => $invoiceNo,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_fees', 'school_id')) {
                                $feeData['school_id'] = $student->school_id;
                            }
                            $batchFees[] = $feeData;
                        }
                    }
                }

                if (!empty($batchFees)) {
                    StudentFee::insert($batchFees);
                    $processedCount += count($batchFees);
                }

                // Send SMS for this specific chunk
                try {
                    $smsService->sendConsolidatedFeeNotification($studentIds, $request->month);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("SMS Notification Failed for chunk: " . $e->getMessage());
                }
            });
        });

        // Log Activity
        $classModel = \App\Models\SchoolClass::find($request->class_id);
        $className = $classModel ? $classModel->name : 'Unknown Class';
        \App\Helpers\ActivityLogger::log('fee', "Fees generated for Class {$className} ({$request->month}). Total items: $processedCount.");

        return back()->with('success', "Fees generated successfully. Total $processedCount items processed.");
    }

    // Manual Invoice Editing: Add Item
    public function addInvoiceItem(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string|exists:student_fees,invoice_no',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'amount' => 'required|numeric|min:0',
        ]);

        // Get one record to retrieve shared details (student_id, month, due_date)
        $baseFee = StudentFee::where('invoice_no', $request->invoice_no)->firstOrFail();

        // Check if this structure already exists in this invoice to avoid duplicates (optional, might vary)
        // But for manual add, maybe they WANT to add another 'Late Fee' even if one exists?
        // Let's allow duplicates for specialized items, or unique? 
        // Best practice: allow unique structure-student-month tuple. 
        // BUT if it's a "Fine" structure, maybe multiple fines? 
        // For now, let's assuming strict uniqueness for structure-student-month to prevent mess.

        $exists = StudentFee::where('student_id', $baseFee->student_id)
            ->where('fee_structure_id', $request->fee_structure_id)
            ->where('month', $baseFee->month)
            ->where('invoice_no', $request->invoice_no) // Ensure check is scoped to this invoice
            ->exists();

        if ($exists) {
            return back()->with('error', 'This fee item already exists in this invoice.');
        }

        $feeData = [
            'student_id' => $baseFee->student_id,
            'fee_structure_id' => $request->fee_structure_id,
            'month' => $baseFee->month,
            'amount' => $request->amount, // Allow override
            'due_date' => $baseFee->due_date,
            'status' => 'unpaid',
            'invoice_no' => $request->invoice_no,
        ];
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_fees', 'school_id')) {
            $feeData['school_id'] = $baseFee->student->school_id;
        }
        StudentFee::create($feeData);

        return back()->with('success', 'Fee item added successfully to Invoice ' . $request->invoice_no);
    }

    // Manual Invoice Editing: Remove Item
    public function removeInvoiceItem($id)
    {
        $fee = StudentFee::findOrFail($id);

        if ($fee->status == 'paid' || $fee->status == 'partial') {
            return back()->with('error', 'Cannot remove an item that has already been paid or partially paid.');
        }

        $fee->delete();

        return back()->with('success', 'Fee item removed successfully.');
    }

    public function edit($invoice_no)
    {
        // Fetch all fees for this invoice
        if (str_starts_with($invoice_no, 'id-')) {
            $id = substr($invoice_no, 3);
            $fee = StudentFee::findOrFail($id);
            // Fallback logic
            $fees = StudentFee::where('student_id', $fee->student_id)
                ->where('month', $fee->month)
                ->where('due_date', $fee->due_date)
                ->whereNull('invoice_no')
                ->with(['student.schoolClass', 'feeStructure.feeCategory'])
                ->get();
        } else {
            $fees = StudentFee::where('invoice_no', $invoice_no)
                ->with(['student.schoolClass', 'feeStructure.feeCategory'])
                ->get();
        }

        if ($fees->isEmpty()) {
            return back()->with('error', 'Invoice not found.');
        }

        // Use the first fee to get student context
        $student = $fees->first()->student;
        $invoiceNo = $invoice_no;

        if (request()->routeIs('admin.*')) {
            return view('school admin.fees.edit', compact('fees', 'student', 'invoiceNo'));
        }
        return view('accountant.fees.edit', compact('fees', 'student', 'invoiceNo'));
    }

    public function updateInvoiceItem(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $fee = StudentFee::findOrFail($id);

        if ($fee->status == 'paid' || $fee->status == 'partial') {
            return back()->with('error', 'Cannot update amount for a paid/partial fee.');
        }

        $fee->amount = $request->amount;
        $fee->save();

        return back()->with('success', 'Fee amount updated successfully.');
    }

    // This method replaces the single ID collect
    public function storePayment(Request $request, $invoice_no)
    {
        // 1. Fetch all unpaid/partial fees for this invoice
        if (str_starts_with($invoice_no, 'id-')) {
            $feeId = substr($invoice_no, 3);
            $baseFee = StudentFee::find($feeId);

            if (!$baseFee) {
                return back()->with('error', 'Fee record not found.');
            }

            // Find all related fees (same grouping, null invoice_no)
            $fees = StudentFee::where('student_id', $baseFee->student_id)
                ->where('month', $baseFee->month)
                ->where('due_date', $baseFee->due_date)
                ->whereNull('invoice_no')
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('payments')
                ->get();
        } else {
            $fees = StudentFee::where('invoice_no', $invoice_no)
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('payments')
                ->get();
        }

        if ($fees->isEmpty()) {
            return back()->with('error', 'No unpaid fees found for this invoice.');
        }

        $request->validate([
            'late_fee' => 'nullable|numeric|min:0', // Should be distributed or applied to first?
            'waiver' => 'nullable|numeric|min:0',
            'amount_paid' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        // Calculate Totals for Validation
        $grandTotalAmount = $fees->sum('amount');
        $grandTotalLate = $fees->sum('late_fee');
        $grandTotalDiscount = $fees->sum('discount');
        $grandTotalPaidPrevious = $fees->flatMap->payments->sum('amount_paid');

        // Inputs
        // Note: late_fee input in modal is likely "Total Late Fee" for the invoice? 
        // Or is it incremental? The UI usually shows current. 
        // Let's assume the user enters the DESIRED total late fee for the whole invoice.
        // We will distribute the DIFFERENCE (New Late - Old Late) across the first item? 
        // Or better, just apply to the first item to keep it simple.

        // Wait, if I have Tuition 5000 and Transport 2000. 
        // I add Late Fee 500. It doesn't matter which one gets it, the total is what matters.
        // I'll apply Late Fee and Waiver to the FIRST fee in the list for simplicity.

        $waiverInput = $request->input('waiver', 0);
        //$lateFeeInput = $request->input('late_fee', 0); // This logic needs to know the PREVIOUS late fee to increment/replace? 
        // The modal likely passes the CURRENT total + user edit.
        // Actually, easiest to just update the existing late fees?
        // Let's assume 'waiver' is INCREMENTAL (as decided before).

        // Distribution Logic:
        // 1. Apply Waiver to the first fee (or distribute until 0).
        // 2. Distribute Payment Amount across fees (paying off one by one).

        $paymentAmount = $request->amount_paid;
        $remainingWaiver = $waiverInput;

        // Validating max payable
        $netPayable = ($grandTotalAmount + $grandTotalLate - $grandTotalDiscount) - $grandTotalPaidPrevious - $waiverInput;
        // Note: grandTotalAmount includes previously added late fees/discounts? 
        // No, 'amount' is base. 'late_fee' and 'discount' are separate columns.

        if ($paymentAmount > round($netPayable, 2) + 0.05) { // Small buffer
            return back()->with('error', "Amount paid cannot exceed the net payable balance of PKR " . number_format($netPayable, 2));
        }

        DB::transaction(function () use ($fees, $request, $remainingWaiver, $paymentAmount) {

            // 1. Apply Waiver (to first fee for simplicity)
            if ($remainingWaiver > 0) {
                $firstFee = $fees->first();
                $firstFee->update([
                    'discount' => $firstFee->discount + $remainingWaiver,
                    'discount_reason' => $request->remarks ? $request->remarks . ' (Waiver)' : 'Waiver Applied',
                ]);
            }

            // 2. Distribute Payment
            $moneyBucket = $paymentAmount;

            foreach ($fees as $fee) {
                if ($moneyBucket <= 0) break;

                // Calculate how much this specific fee OWS
                $feeTotal = $fee->amount + $fee->late_fee - $fee->discount;
                $feePaid = $fee->payments->sum('amount_paid');
                $feeOwed = max(0, $feeTotal - $feePaid);

                if ($feeOwed <= 0) continue;

                // Determine how much to pay for this fee
                $toPay = min($moneyBucket, $feeOwed);

                // Record Payment
                FeePayment::create([
                    'student_fee_id' => $fee->id,
                    'amount_paid' => $toPay,
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'remarks' => $request->remarks,
                    'school_id' => $fee->school_id,
                ]);

                // Update Status & Amounts
                $moneyBucket -= $toPay;

                // Check if fully paid
                // Refresh feePaid
                $newFeePaid = $feePaid + $toPay;

                if ($newFeePaid >= $feeTotal - 0.01) {
                    // Full Payment logic
                    // If we need to "Update the current invoice to equal exactly what they paid" as per previous requirement:
                    // That requirement was for partial payment.
                    // If I pay FULL, I just mark as paid.
                    $fee->update(['status' => 'paid']);
                } else {
                    // Partial Payment Logic
                    // "Update the current invoice amount to equal exactly what they paid (e.g., change 2000 to 1500) and mark it as Paid."
                    // "Create NEW invoice row for the difference"

                    // We need to apply this logic for EACH sub-fee that is partially paid.
                    // Usually only the LAST affected fee in the loop will be partially paid. The others are fully paid.

                    // Calculate new base amount for the Paid portion
                    $originalAmount = $fee->amount;

                    // We want: (NewAmount + Late - Discount) = TotalPaid
                    // NewAmount = TotalPaid - Late + Discount
                    $newBaseAmount = $newFeePaid - $fee->late_fee + $fee->discount;

                    $fee->update([
                        'amount' => $newBaseAmount,
                        'status' => 'paid'
                    ]);

                    // Create Remaining Balance Record
                    $remainingForThisFee = $feeTotal - $newFeePaid;

                    if ($remainingForThisFee > 0) {
                        $feeData = [
                            'student_id' => $fee->student_id,
                            'fee_structure_id' => $fee->fee_structure_id,
                            'month' => 'Remaining Balance',
                            'amount' => $remainingForThisFee,
                            'due_date' => $fee->due_date,
                            'status' => 'unpaid',
                            'invoice_no' => $fee->invoice_no,
                            'late_fee' => 0,
                            'discount' => 0,
                        ];
                        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_fees', 'school_id')) {
                            $feeData['school_id'] = $fee->school_id;
                        }
                        StudentFee::create($feeData);
                    }
                }
            }
        });

        if ($request->has('print_receipt') && $request->print_receipt) {
            // Redirect to print for the first fee's ID (which leads to the invoice view that fetches by invoice_no)
            return redirect()->route('accountant.fees.invoice', $fees->first()->id);
        }

        // Log Activity
        // Use first fee student ID for log
        $studentId = $fees->first()->student_id;
        $amountFormat = number_format($paymentAmount);
        \App\Helpers\ActivityLogger::log('fee', "Fee collected from Student {$studentId} (PKR {$amountFormat}).");

        if (request()->routeIs('admin.*')) {
            return redirect()->route('admin.fees.collect.index')->with('success', 'Consolidated Payment recorded successfully.');
        }
        return redirect()->route('accountant.fees.collect.index')->with('success', 'Consolidated Payment recorded successfully.');
    }

    public function collect($id)
    {
        $fee = StudentFee::with(['student', 'feeStructure.feeCategory', 'payments'])->findOrFail($id);
        if (request()->routeIs('admin.*')) {
            return view('school admin.fees.collect.pay', compact('fee'));
        }
        return view('accountant.fees.collect.pay', compact('fee'));
    }

    public function invoice($id)
    {
        $fee = StudentFee::findOrFail($id);

        // If fee has an invoice number, fetch all fees with that number
        if ($fee->invoice_no) {
            $fees = StudentFee::where('invoice_no', $fee->invoice_no)
                ->with(['student.schoolClass', 'feeStructure.feeCategory', 'payments'])
                ->get();
        } else {
            // Fallback for fees without invoice number: group by student, month, due_date
            $fees = StudentFee::where('student_id', $fee->student_id)
                ->where('month', $fee->month)
                ->where('due_date', $fee->due_date)
                ->whereNull('invoice_no')
                ->with(['student.schoolClass', 'feeStructure.feeCategory', 'payments'])
                ->get();
        }

        // Pass the FIRST fee as the main object for student details, and the collection for the table
        return view('accountant.fees.invoice', ['fee' => $fees->first(), 'fees' => $fees]);
    }

    public function invoiceStudent($studentId)
    {
        $student = Student::with('schoolClass')->findOrFail($studentId);
        $fees = StudentFee::with(['feeStructure.feeCategory', 'payments'])
            ->where('student_id', $studentId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();

        return view('accountant.fees.consolidated_invoice', compact('student', 'fees'));
    }

    public function bulkPrint(Request $request)
    {
        $request->validate([
            'selected_fees' => 'required|array|max:100', // Limit to 100 to prevent memory exhaustion
            'selected_fees.*' => 'exists:student_fees,id',
        ]);

        $fees = StudentFee::whereIn('id', $request->selected_fees)
            ->with(['student.schoolClass', 'feeStructure.feeCategory', 'payments'])
            ->get();

        // Used a new view for bulk printing based on the single invoice view
        // It will just loop over the fees and render the invoice template with page-break-after
        return view('accountant.fees.bulk_invoice', compact('fees'));
    }

    public function feeCard($studentId)
    {
        $student = Student::with('schoolClass')->findOrFail($studentId);

        // Fetch all fees for the student
        $allFees = StudentFee::where('student_id', $studentId)
            ->with('payments')
            ->orderBy('due_date', 'desc')
            ->get();

        // Group by invoice_no (or month if invoice_no is null)
        $groupedFees = $allFees->groupBy(function ($fee) {
            return $fee->invoice_no ?: $fee->month;
        })->take(12);

        $feeCards = collect();

        foreach ($groupedFees as $groupId => $fees) {
            $totalAmount = $fees->sum('amount');
            $totalLateFee = $fees->sum('late_fee');
            $totalDiscount = $fees->sum('discount');
            $totalPaid = $fees->flatMap->payments->sum('amount_paid');

            $latestPayment = $fees->flatMap->payments->sortByDesc('payment_date')->first();

            $netPayable = $totalAmount + $totalLateFee - $totalDiscount;

            $status = 'unpaid';
            if ($totalPaid >= ($netPayable - 0.05) && $netPayable > 0) {
                $status = 'paid';
            } elseif ($totalPaid > 0) {
                $status = 'partial';
            } else if ($totalPaid == 0 && $netPayable <= 0) {
                $status = 'paid';
            }

            $feeCards->push((object)[
                'invoice_no' => $groupId,
                'month' => \Carbon\Carbon::parse($fees->first()->month . '-01')->format('M Y'),
                'total_amount' => $netPayable,
                'total_paid' => $totalPaid,
                'late_fine' => $totalLateFee,
                'status' => $status,
                'payment_date' => $latestPayment ? \Carbon\Carbon::parse($latestPayment->payment_date)->format('d M, Y') : null,
                'receipt_no' => $latestPayment ? 'REC-' . str_pad($latestPayment->id, 5, '0', STR_PAD_LEFT) : 'N/A'
            ]);
        }

        $layout = request()->routeIs('admin.*') ? 'layouts.admin' : 'layouts.accountant';
        return view('shared.fee_card', compact('student', 'feeCards', 'layout'));
    }
}
