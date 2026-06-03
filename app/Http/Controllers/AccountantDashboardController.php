<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountantDashboardController extends Controller
{
    public function index()
    {
        $accountant = \Illuminate\Support\Facades\Auth::guard('accountant')->user();
        $schoolName = $accountant->school->school_name ?? 'My School';

        $schoolId = $accountant->school_id;

        // 1. Total Revenue (Sum of all payments collected)
        $totalRevenue = \App\Models\FeePayment::where('school_id', $schoolId)->sum('amount_paid');

        // Revenue Growth Calculation
        $currentMonthRevenue = \App\Models\FeePayment::where('school_id', $schoolId)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount_paid');

        $lastMonthRevenue = \App\Models\FeePayment::where('school_id', $schoolId)
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount_paid');

        if ($lastMonthRevenue > 0) {
            $revenueGrowth = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
        } else {
            $revenueGrowth = $currentMonthRevenue > 0 ? 100 : 0;
        }

        // 2. Pending Fees (Total Expected with adjustments - Total paid)
        // Use DB-level aggregation to avoid loading all records into memory
        $paidSoFar = \Illuminate\Support\Facades\DB::table('fee_payments')
            ->join('student_fees', 'fee_payments.student_fee_id', '=', 'student_fees.id')
            ->where('student_fees.school_id', $schoolId)
            ->whereIn('student_fees.status', ['unpaid', 'partial'])
            ->sum('fee_payments.amount_paid');
        $pendingFees = \App\Models\StudentFee::where('school_id', $schoolId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('SUM(amount + late_fee - discount) as gross')
            ->value('gross') - $paidSoFar;

        // Unpaid Invoices Count (Count of invoices not fully paid)
        $unpaidInvoicesCount = \App\Models\StudentFee::where('school_id', $schoolId)->where('status', '!=', 'paid')->count();

        // 3. Total Expenses
        // Assuming Expense model exists. I saw ExpenseController so likely Expense model exists.
        $totalExpenses = \App\Models\Expense::where('school_id', $schoolId)->sum('amount');

        // 4. New Invoices (Count of StudentFee records created this month)
        $newInvoices = \App\Models\StudentFee::where('school_id', $schoolId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // 5. Today's Collection
        $todaysCollection = \App\Models\FeePayment::where('school_id', $schoolId)->whereDate('payment_date', now())->sum('amount_paid');

        // 5. Recent Activity
        // Combine recent payments and expenses
        $recentPayments = \App\Models\FeePayment::where('school_id', $schoolId)
            ->with(['studentFee.student'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'income',
                    'title' => 'Fee Collection',
                    'subtitle' => 'Received from ' . ($payment->studentFee->student->name ?? 'Student'),
                    'amount' => $payment->amount_paid,
                    'time' => $payment->created_at->diffForHumans(),
                    'icon' => 'fa-arrow-down',
                    'color' => 'green'
                ];
            });

        $recentExpenses = \App\Models\Expense::where('school_id', $schoolId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($expense) {
                return [
                    'type' => 'expense',
                    'title' => $expense->title ?? 'Expense', // Assuming title or description column
                    'subtitle' => $expense->description ?? 'Office Expense',
                    'amount' => $expense->amount,
                    'time' => $expense->created_at->diffForHumans(),
                    'icon' => 'fa-arrow-up',
                    'color' => 'red'
                ];
            });

        // Merge and sort
        $recentActivity = $recentPayments->concat($recentExpenses)->sortByDesc('created_at')->take(5);

        // 6. Upcoming Events
        $upcomingEvents = \App\Models\Event::whereJsonContains('target_audience', 'accountant')
            ->where('event_date', '>=', now())
            ->orderBy('event_date', 'asc')
            ->take(3)
            ->get();

        // 7. Upcoming Staff Meetings (Accountant)
        $schoolId = $accountant->school_id;
        $upcomingMeetings = \App\Models\TeacherMeeting::where('school_id', $schoolId)
            ->whereIn('status', ['scheduled', 'started'])
            ->where('start_time', '>=', now()->subHours(2))
            ->orderBy('start_time', 'asc')
            ->take(3)
            ->get();

        // 6. Chart Data (Monthly Revenue & Expenses for current year)
        $months = [];
        $revenueData = [];
        $lastYearRevenueData = [];
        $expenseData = [];
        $chartViewType = 'Monthly';

        $dayOfYear = now()->dayOfYear;

        if ($dayOfYear <= 7) {
            $chartViewType = 'Daily';
            // Daily View (Jan 1 to Current Day)
            for ($i = 1; $i <= $dayOfYear; $i++) {
                $date = \Carbon\Carbon::createFromDate(now()->year, 1, $i);
                $months[] = $date->format('M d'); // e.g., Jan 01

                $revenueData[] = \App\Models\FeePayment::where('school_id', $schoolId)->whereDate('payment_date', $date)->sum('amount_paid');

                // Last Year Comparison (Same Date)
                $lastYearDate = \Carbon\Carbon::createFromDate(now()->subYear()->year, 1, $i);
                $lastYearRevenueData[] = \App\Models\FeePayment::where('school_id', $schoolId)->whereDate('payment_date', $lastYearDate)->sum('amount_paid');

                $expenseData[] = \App\Models\Expense::where('school_id', $schoolId)->whereDate('expense_date', $date)->sum('amount');
            }
        } elseif ($dayOfYear <= 31) {
            $chartViewType = 'Weekly';
            // Weekly View (Weeks of Jan)
            $currentWeek = ceil($dayOfYear / 7); // Simple 1-5 week calculation

            for ($i = 1; $i <= $currentWeek; $i++) {
                $months[] = 'Week ' . $i;

                $startDay = ($i - 1) * 7 + 1;
                $endDay = min($i * 7, 31); // Cap at 31 for Jan

                // Define Date Range for this "Week"
                $startDate = \Carbon\Carbon::createFromDate(now()->year, 1, $startDay)->startOfDay();
                $endDate = \Carbon\Carbon::createFromDate(now()->year, 1, $endDay)->endOfDay();

                $revenueData[] = \App\Models\FeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$startDate, $endDate])->sum('amount_paid');

                // Last Year
                $lastYearStartDate = \Carbon\Carbon::createFromDate(now()->subYear()->year, 1, $startDay)->startOfDay();
                $lastYearEndDate = \Carbon\Carbon::createFromDate(now()->subYear()->year, 1, $endDay)->endOfDay();
                $lastYearRevenueData[] = \App\Models\FeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$lastYearStartDate, $lastYearEndDate])->sum('amount_paid');

                $expenseData[] = \App\Models\Expense::where('school_id', $schoolId)->whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
            }
        } else {
            $chartViewType = 'Monthly';
            $currentMonthIndex = now()->month; // 1 to 12

            // 3 queries instead of (currentMonthIndex * 3) queries
            $thisYearIncome = \App\Models\FeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', now()->year)
                ->selectRaw('MONTH(payment_date) as m, SUM(amount_paid) as total')
                ->groupBy('m')->pluck('total', 'm');

            $lastYearIncome = \App\Models\FeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', now()->subYear()->year)
                ->selectRaw('MONTH(payment_date) as m, SUM(amount_paid) as total')
                ->groupBy('m')->pluck('total', 'm');

            $thisYearExpense = \App\Models\Expense::where('school_id', $schoolId)
                ->whereYear('expense_date', now()->year)
                ->selectRaw('MONTH(expense_date) as m, SUM(amount) as total')
                ->groupBy('m')->pluck('total', 'm');

            for ($i = 1; $i <= $currentMonthIndex; $i++) {
                $months[] = date('M', mktime(0, 0, 0, $i, 1));
                $revenueData[] = $thisYearIncome[$i] ?? 0;
                $lastYearRevenueData[] = $lastYearIncome[$i] ?? 0;
                $expenseData[] = $thisYearExpense[$i] ?? 0;
            }
        }

        // 7. Daily Financial Data (for "This Month" view default)
        $daysInMonth = \Carbon\Carbon::now()->daysInMonth;
        $currentMonthDays = collect(range(1, $daysInMonth))->map(function ($i) {
            return \Carbon\Carbon::createFromDate(now()->year, now()->month, $i)->format('d M');
        });

        $schoolId = $accountant->school_id;
        $monthStart = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = \Carbon\Carbon::now()->endOfMonth()->toDateString();

        // 2 queries instead of 62 (31 days x 2)
        $dailyIncomeRaw = \App\Models\FeePayment::where('school_id', $schoolId)
            ->selectRaw('DAY(payment_date) as day, SUM(amount_paid) as total')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('day')->pluck('total', 'day');

        $dailyExpenseRaw = \App\Models\Expense::where('school_id', $schoolId)
            ->selectRaw('DAY(expense_date) as day, SUM(amount) as total')
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->groupBy('day')->pluck('total', 'day');

        $dailyIncomeData = collect(range(1, $daysInMonth))->map(fn($i) => $dailyIncomeRaw[$i] ?? 0);
        $dailyExpenseData = collect(range(1, $daysInMonth))->map(fn($i) => $dailyExpenseRaw[$i] ?? 0);

        return view('accountant.dashboard', compact(
            'totalRevenue',
            'pendingFees',
            'totalExpenses',
            'newInvoices',
            'recentActivity',
            'months',
            'revenueData',
            'lastYearRevenueData',
            'expenseData',
            'revenueGrowth',
            'unpaidInvoicesCount',
            'chartViewType',
            'schoolName',
            'todaysCollection',
            'upcomingEvents',
            'upcomingMeetings',
            'currentMonthDays',
            'dailyIncomeData',
            'dailyExpenseData'
        ));
    }

    public function students()
    {
        $students = \App\Models\Student::with('schoolClass')->latest()->paginate(15);
        return view('accountant.students.index', compact('students'));
    }

    public function createStudent()
    {
        $classes = \App\Models\SchoolClass::orderBy('name')->get(['id', 'name']);
        return view('accountant.students.create', compact('classes'));
    }

    public function parents()
    {
        // Use the Parent model instead of grouping students manually for better performance and pagination
        $parents = \App\Models\SchoolParent::with('students')->latest()->paginate(15);

        return view('accountant.parents', compact('parents'));
    }

    public function storeReport(Request $request)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('accountant')->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'severity' => 'required|in:low,medium,high',
            'reason' => 'required|string',
        ]);

        $accountant = \Illuminate\Support\Facades\Auth::guard('accountant')->user();

        // Assuming Accountant has school_id or linked via school relationship
        // In AccountantController store, we saw: 'school_id' => Auth::id() (Admin ID)
        // So accountant->school_id should be valid.

        $reportData = [
            'student_id' => $request->student_id,
            'reporter_id' => $accountant->id,
            'reporter_role' => 'accountant',
            'severity' => $request->severity,
            'reason' => $request->reason,
            'status' => 'pending',
        ];
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('student_reports', 'school_id')) {
            $reportData['school_id'] = $accountant->school_id;
        }
        \App\Models\StudentReport::create($reportData);

        return back()->with('success', 'Student report submitted for review.');
    }

    public function resetStudentPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|min:6'
        ]);

        $student = \App\Models\Student::findOrFail($id);

        // Ensure student belongs to accountant's school
        $accountant = \Illuminate\Support\Facades\Auth::guard('accountant')->user();
        if ($student->school_id !== $accountant->school_id) {
            abort(403, 'Unauthorized');
        }

        $student->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $student->save();

        return back()->with('success', 'Student password reset successfully.');
    }
    public function showStudent($id)
    {
        $student = \App\Models\Student::with('schoolClass')->findOrFail($id);
        $accountant = \Illuminate\Support\Facades\Auth::guard('accountant')->user();

        // Ensure student belongs to accountant's school
        if ($student->school_id !== $accountant->school_id) {
            abort(403, 'Unauthorized');
        }

        // Get last 12 months fee history
        $twelveMonthsAgo = \Carbon\Carbon::now()->subMonths(12)->startOfMonth()->format('Y-m');

        $feeHistory = \App\Models\StudentFee::with('payments', 'feeStructure.feeCategory')
            ->where('student_id', $id)
            ->where('month', '>=', $twelveMonthsAgo)
            ->orderBy('month', 'desc')
            ->get();

        $totalPaid = 0;
        $totalPending = 0;

        foreach ($feeHistory as $fee) {
            $paid = $fee->payments->sum('amount_paid');
            $totalPaid += $paid;

            $payable = ($fee->amount + $fee->late_fee) - $fee->discount;
            if ($fee->status !== 'paid') {
                $totalPending += max(0, $payable - $paid);
            }
        }

        $invoices = $feeHistory->groupBy('invoice_no');

        return view('accountant.students.show', compact('student', 'invoices', 'totalPaid', 'totalPending'));
    }
}
