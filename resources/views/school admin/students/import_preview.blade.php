@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    @php
        $totalCount = count($rows);
        $validCount = collect($rows)->where('is_valid', true)->count();
        $invalidCount = $totalCount - $validCount;
    @endphp

    <form action="{{ route('students.import-save') }}" method="POST">
        @csrf

        <!-- Top summary bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Preview Import</h2>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">
                        Total: {{ $totalCount }} Row(s)
                    </span>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                        {{ $validCount }} Valid
                    </span>
                    @if($invalidCount > 0)
                        <span class="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-semibold">
                            {{ $invalidCount }} Invalid
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <a href="{{ route('admin.students') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors w-1/2 md:w-auto text-center">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold shadow-lg shadow-emerald-600/20 transition-all w-1/2 md:w-auto"
                >
                    Save Valid Students
                </button>
            </div>
        </div>

        @if(session('error'))
            <div class="bg-rose-100 border-l-4 border-rose-500 text-rose-700 p-4 mb-6 rounded-r-lg" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <!-- Instructions warning -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex gap-3 text-amber-800 text-sm">
            <i class="fa-solid fa-circle-info mt-0.5 text-lg text-amber-600"></i>
            <div>
                <span class="font-bold text-amber-900 block mb-0.5">Editable Preview:</span>
                You can edit any cell directly in the table below to correct errors. Once satisfied, click <strong>Save Valid Students</strong>. Only rows marked as <strong>Valid</strong> will be imported; invalid rows will be skipped.
            </div>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 whitespace-nowrap border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3">Full Name *</th>
                            <th class="px-4 py-3">Email *</th>
                            <th class="px-4 py-3 w-32">Gender *</th>
                            <th class="px-4 py-3">Date of Birth</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Parent Name</th>
                            <th class="px-4 py-3">Parent Phone *</th>
                            <th class="px-4 py-3">Parent Email</th>
                            <th class="px-4 py-3">Password *</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $index => $row)
                            @php
                                $isValid = $row['is_valid'];
                                $rowBg = $isValid ? 'bg-white' : 'bg-rose-50/60';
                                $inputBorder = $isValid ? 'border-gray-200 focus:ring-indigo-500 focus:border-indigo-500' : 'border-rose-300 bg-rose-50 focus:ring-rose-500 focus:border-rose-500';
                            @endphp
                            <tr class="{{ $rowBg }} hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-center font-medium text-gray-400">
                                    {{ $index + 1 }}
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        name="rows[{{ $index }}][full_name]" 
                                        value="{{ $row['full_name'] }}" 
                                        class="w-48 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                        required
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="email" 
                                        name="rows[{{ $index }}][email]" 
                                        value="{{ $row['email'] }}" 
                                        class="w-56 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                        required
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <select 
                                        name="rows[{{ $index }}][gender]" 
                                        class="w-28 px-2 py-1.5 text-sm border rounded-lg shadow-sm bg-white transition-all {{ $inputBorder }}"
                                        required
                                    >
                                        <option value="Male" {{ strtolower($row['gender']) == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ strtolower($row['gender']) == 'female' ? 'selected' : '' }}>Female</option>
                                    </select>
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        placeholder="YYYY-MM-DD"
                                        name="rows[{{ $index }}][date_of_birth]" 
                                        value="{{ $row['date_of_birth'] }}" 
                                        class="w-32 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        name="rows[{{ $index }}][phone]" 
                                        value="{{ $row['phone'] }}" 
                                        class="w-36 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        name="rows[{{ $index }}][parent_name]" 
                                        value="{{ $row['parent_name'] }}" 
                                        class="w-44 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        name="rows[{{ $index }}][parent_phone]" 
                                        value="{{ $row['parent_phone'] }}" 
                                        class="w-36 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                        required
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="email" 
                                        name="rows[{{ $index }}][parent_email]" 
                                        value="{{ $row['parent_email'] }}" 
                                        class="w-56 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                    >
                                </td>
                                
                                <td class="px-3 py-2">
                                    <input 
                                        type="text" 
                                        name="rows[{{ $index }}][password]" 
                                        value="{{ $row['password'] }}" 
                                        class="w-36 px-2 py-1.5 text-sm border rounded-lg shadow-sm transition-all {{ $inputBorder }}"
                                        required
                                    >
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if($isValid)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Valid
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-100 tooltip" title="{{ $row['error'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> {{ $row['error'] }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Bottom Action Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-between items-center">
                <span class="text-xs text-gray-500 font-medium">
                    Please review fields carefully. Required fields are marked with (*).
                </span>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.students') }}" class="px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 rounded-lg text-sm font-medium text-gray-700 transition-colors">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold shadow-lg shadow-emerald-600/20 transition-all"
                    >
                        Save Valid Students
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
