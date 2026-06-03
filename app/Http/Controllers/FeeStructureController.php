<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\FeeCategory;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class FeeStructureController extends Controller
{
    public function index()
    {
        $structures = FeeStructure::with(['schoolClass', 'feeCategory'])->get();
        $classes = SchoolClass::all();
        $categories = FeeCategory::all();

        if (request()->routeIs('admin.*')) {
            return view('school admin.fees.structure.index', compact('structures', 'classes', 'categories'));
        }
        return view('accountant.fees.structure.index', compact('structures', 'classes', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:tenant.school_classes,id',
            'fee_category_id' => 'required|exists:tenant.fee_categories,id',
            'amount' => 'required|numeric|min:0',
            'academic_year' => 'required|string',
        ]);

        $data = $request->all();
        if (auth()->guard('web')->check()) {
            $data['school_id'] = auth()->guard('web')->user()->role === 'admin' ? auth()->guard('web')->id() : auth()->guard('web')->user()->school_id;
        } elseif (auth()->guard('accountant')->check()) {
            $data['school_id'] = auth()->guard('accountant')->user()->school_id;
        }

        FeeStructure::create($data);

        if ($request->routeIs('admin.*')) {
            return redirect()->route('admin.fees.structure.index')->with('success', 'Fee Structure created successfully.');
        }
        return redirect()->route('accountant.fees.structure.index')->with('success', 'Fee Structure created successfully.');
    }

    public function update(Request $request, FeeStructure $feeStructure)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'fee_category_id' => 'required|exists:fee_categories,id',
            'amount' => 'required|numeric|min:0',
            'academic_year' => 'required|string',
        ]);

        $feeStructure->update($request->all());

        if ($request->routeIs('admin.*')) {
            return redirect()->route('admin.fees.structure.index')->with('success', 'Fee Structure updated successfully.');
        }
        return redirect()->route('accountant.fees.structure.index')->with('success', 'Fee Structure updated successfully.');
    }

    public function destroy(Request $request, FeeStructure $feeStructure)
    {
        $feeStructure->delete();

        if ($request->routeIs('admin.*')) {
            return redirect()->route('admin.fees.structure.index')->with('success', 'Fee Structure deleted successfully.');
        }
        return redirect()->route('accountant.fees.structure.index')->with('success', 'Fee Structure deleted successfully.');
    }
}
