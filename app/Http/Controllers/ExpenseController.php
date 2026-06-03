<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'creator']);

        if ($request->has('category_id') && $request->category_id) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->has('date') && $request->date) {
            $query->whereDate('expense_date', $request->date);
        }

        $expenses = $query->latest()->paginate(20);
        $categories = ExpenseCategory::all();

        if (request()->routeIs('admin.*')) {
            return view('school admin.expenses.index', compact('expenses', 'categories'));
        }
        return view('accountant.expenses.index', compact('expenses', 'categories'));
    }

    public function create()
    {
        $categories = ExpenseCategory::all();
        if (request()->routeIs('admin.*')) {
            return view('school admin.expenses.create', compact('categories'));
        }
        return view('accountant.expenses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'expense_category_id' => 'required|exists:tenant.expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        $data = $request->all();

        if (\Illuminate\Support\Facades\Auth::guard('accountant')->check()) {
            $user = \Illuminate\Support\Facades\Auth::guard('accountant')->user();
            $data['created_by'] = $user->id;
            $data['school_id'] = $user->school_id;
        } else {
            $user = \Illuminate\Support\Facades\Auth::user();
            $data['school_id'] = $user->id; // Admin is the school
        }

        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        Expense::create($data);

        if (request()->routeIs('admin.*')) {
            return redirect()->route('admin.expenses.index')->with('success', 'Expense recorded successfully.');
        }
        return redirect()->route('accountant.expenses.index')->with('success', 'Expense recorded successfully.');
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::all();
        if (request()->routeIs('admin.*')) {
            return view('school admin.expenses.edit', compact('expense', 'categories'));
        }
        return view('accountant.expenses.edit', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        $data = $request->all();

        if ($request->hasFile('receipt')) {
            // Delete old receipt if exists
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $path = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        $expense->update($data);

        if (request()->routeIs('admin.*')) {
            return redirect()->route('admin.expenses.index')->with('success', 'Expense updated successfully.');
        }
        return redirect()->route('accountant.expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $expense->delete();
        if (request()->routeIs('admin.*')) {
            return redirect()->route('admin.expenses.index')->with('success', 'Expense deleted successfully.');
        }
        return redirect()->route('accountant.expenses.index')->with('success', 'Expense deleted successfully.');
    }
}
