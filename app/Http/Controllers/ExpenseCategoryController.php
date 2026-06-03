<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::all();
        return view('accountant.expenses.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:tenant.expense_categories',
            'description' => 'nullable|string',
        ]);

        $data = $request->all();
        if (\Illuminate\Support\Facades\Auth::guard('accountant')->check()) {
            $data['school_id'] = \Illuminate\Support\Facades\Auth::guard('accountant')->user()->school_id;
        } else {
            $data['school_id'] = \Illuminate\Support\Facades\Auth::id();
        }

        $category = ExpenseCategory::create($data);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'category' => $category,
                'message' => 'Expense Category created successfully.'
            ]);
        }

        if (request()->routeIs('admin.*')) {
            return redirect()->route('admin.expenses.categories.index')->with('success', 'Expense Category created successfully.');
        }

        return redirect()->route('accountant.expenses.categories.index')->with('success', 'Expense Category created successfully.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:tenant.expense_categories,name,' . $expenseCategory->id,
            'description' => 'nullable|string',
        ]);

        $expenseCategory->update($request->all());

        return redirect()->route('accountant.expenses.categories.index')->with('success', 'Expense Category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        $expenseCategory->delete();
        return redirect()->route('accountant.expenses.categories.index')->with('success', 'Expense Category deleted successfully.');
    }
}
