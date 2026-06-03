@php
$routePrefix = ($guard === 'accountant') ? 'accountant.' : 'admin.';
@endphp

@if($guard === 'accountant')
@extends('layouts.accountant')
@else
@extends('layouts.admin')
@endif

@section('title', 'Family Management')

@section('content')
<div class="px-6 py-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">👨‍👩‍👧 Families</h1>
            <p class="text-gray-500 mt-1">All registered parent families in this school.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-xl bg-indigo-100 text-indigo-700 text-sm font-semibold">
                {{ $families->total() }} Families
            </span>
        </div>
    </div>

    {{-- Families Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">Code</th>
                        <th class="px-6 py-4 font-semibold">Father / Guardian</th>
                        <th class="px-6 py-4 font-semibold">Email</th>
                        <th class="px-6 py-4 font-semibold">Phone</th>
                        <th class="px-6 py-4 font-semibold text-center">Children</th>
                        <th class="px-6 py-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($families as $family)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-bold font-mono">
                                {{ $family->family_code }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($family->father_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">{{ $family->father_name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $family->email }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $family->phone }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $family->students_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }} font-bold text-sm">
                                {{ $family->students_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route($routePrefix . 'families.show', $family->id) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors">
                                    <i class="fa-solid fa-eye"></i>
                                    View
                                </a>
                                <form action="{{ route($guard === 'accountant' ? 'accountant.families.destroy' : 'admin.families.destroy', $family->id) }}"
                                    method="POST"
                                    onsubmit="return confirm('Delete this family? Students linked to it will be unlinked.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors">
                                        <i class="fa-solid fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <i class="fa-solid fa-users text-4xl"></i>
                                <p class="text-sm font-medium">No families registered yet.</p>
                                <p class="text-xs">Families are auto-created when students are added with a father email.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($families->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $families->links() }}
        </div>
        @endif
    </div>

</div>
@endsection