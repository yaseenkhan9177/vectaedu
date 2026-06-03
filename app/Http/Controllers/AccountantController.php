<?php

namespace App\Http\Controllers;

use App\Models\Accountant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountantController extends Controller
{
    public function index()
    {
        $accountants = Accountant::all();
        return view('admin.accountant.index', compact('accountants'));
    }

    public function create()
    {
        return view('admin.accountant.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:tenant.accountants',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Accountant::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'school_id' => \Illuminate\Support\Facades\Auth::id(), // Assign current Admin ID as School ID
        ]);

        return redirect()->route('admin.accountants.index')->with('success', 'Accountant created successfully.');
    }

    public function edit(Accountant $accountant)
    {
        return view('admin.accountant.edit', compact('accountant'));
    }

    public function update(Request $request, Accountant $accountant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:accountants,email,' . $accountant->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8',
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $accountant->update($data);

        return redirect()->route('admin.accountants.index')->with('success', 'Accountant updated successfully.');
    }
    
    public function __construct()
{
    $this->middleware(function ($request, $next) {
        if (session('tenant_db')) {
            app(\App\Services\TenantService::class)->configureConnection(session('tenant_db'));
        }
        return $next($request);
    });
}

   public function destroy($id)
{
    $accountant = Accountant::findOrFail($id);
    $accountant->delete();
    return redirect()->route('admin.accountants.index')->with('success', 'Accountant deleted successfully.');
}
}
