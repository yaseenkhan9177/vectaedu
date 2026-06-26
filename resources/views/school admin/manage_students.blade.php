@extends('layouts.admin')

@section('content')
<div
    class="container mx-auto px-4 py-8"
    x-data="{
        showImportModal: false,
        fileName: '',
        handleFileSelect(e) {
            if (e.target.files.length) this.fileName = e.target.files[0].name;
        },
        handleDrop(e) {
            if (e.dataTransfer.files.length) {
                this.$refs.csvInput.files = e.dataTransfer.files;
                this.fileName = e.dataTransfer.files[0].name;
            }
        },
        resetModal() {
            this.fileName = '';
            if (this.$refs.csvInput) this.$refs.csvInput.value = '';
        }
    }"
>
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Manage Students</h2>

        <form action="{{ route('admin.students') }}" method="GET" class="flex-1 max-w-md w-full">
            <div class="relative">
                <input type="text" name="search" placeholder="Search by name or email..." value="{{ request('search') }}" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
        </form>

        <div x-data="{ copied: false }" class="flex gap-3 w-full md:w-auto overflow-x-auto">
            <a href="{{ route('admin.students.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Register Student
            </a>
            <button type="button" @click="showImportModal = true" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Bulk Import
            </button>
            <button @click="navigator.clipboard.writeText('{{ route('parent.registration') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-link'"></i>
                <span x-text="copied ? 'Link Copied!' : 'Admission Link'"></span>
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p>{{ session('success') }}</p>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500">
                    <tr>
                        <th class="px-6 py-4">Image</th>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Class</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($students as $student)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            @if($student->profile_image)
                            <img src="{{ asset($student->profile_image) }}" alt="{{ $student->name }}" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                            @else
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                {{ substr($student->name, 0, 1) }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-900">
                            <div class="flex items-center gap-2">
                                {{ $student->name }}
                                @if($student->status == 'pending')
                                <span class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-red-200">New</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">{{ $student->email }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                {{ $student->schoolClass ? $student->schoolClass->name : 'N/A' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($student->status == 'approved')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                Approved
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">
                                Pending
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($student->status != 'approved')
                                <a href="{{ route('admin.students.approve', $student->id) }}" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Approve">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                                @endif

                                @if($student->status == 'approved')
                                <a href="{{ route('admin.students.show', $student->id) }}" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View Profile & History">
                                    <i class="fa-solid fa-user"></i>
                                </a>
                                <a href="{{ route('admin.students.fee_card', $student->id) }}" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="View Fee Card" target="_blank">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                                <a href="{{ route('admin.exams.admit-card', $student->id) }}" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" title="View Admit Card" target="_blank">
                                    <i class="fa-solid fa-id-card"></i>
                                </a>
                                @endif
                                <a href="{{ route('admin.students.edit', $student->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Update Record">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="{{ route('admin.students.delete', $student->id) }}" onclick="return confirm('Are you sure you want to delete this student?')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $students->links() }}
    </div>

    <!-- CSV Import Modal -->
    <div
        x-show="showImportModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showImportModal = false; resetModal()"></div>

        <!-- Modal -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form
                action="{{ route('students.import-preview') }}"
                method="POST"
                enctype="multipart/form-data"
                class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
            >
                @csrf
                <!-- Header -->
                <div class="bg-white px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg">
                            <i class="fa-solid fa-file-csv text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Bulk Student Import</h3>
                            <p class="text-xs text-gray-500">Import multiple students at once via CSV</p>
                        </div>
                    </div>
                    <button type="button" @click="showImportModal = false; resetModal()" class="text-gray-400 hover:text-gray-500 p-1.5 hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">

                    <!-- Step 1: Download sample -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-50 p-4 rounded-xl">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Step 1 — Download Sample CSV</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Fill in the sample file with your student data, then upload it below.</p>
                        </div>
                        <a
                            href="{{ route('students.sample-csv') }}"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-md shadow-indigo-600/10 transition-all flex items-center gap-2 whitespace-nowrap"
                        >
                            <i class="fa-solid fa-download"></i> Download Sample CSV
                        </a>
                    </div>

                    <!-- Column reference -->
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Required CSV Columns</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['full_name','email','gender','date_of_birth','phone','parent_name','parent_phone','parent_email','password'] as $col)
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium border border-indigo-100">{{ $col }}</span>
                            @endforeach
                        </div>
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3">
                            <i class="fa-solid fa-circle-exclamation mr-1"></i>
                            <strong>gender</strong> must be <code>Male</code> or <code>Female</code> (capital first letter).
                            <strong>date_of_birth</strong> format: <code>YYYY-MM-DD</code>.
                        </p>
                    </div>

                    <!-- Step 2: Upload -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Step 2 — Upload Your CSV</h4>
                        <div
                            x-data="{ isDragging: false }"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false; handleDrop($event)"
                            class="border-2 border-dashed rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer transition-colors"
                            :class="isDragging ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-300 hover:border-indigo-400 bg-gray-50/50'"
                            @click="$refs.csvInput.click()"
                        >
                            <input type="file" name="file" x-ref="csvInput" accept=".csv" class="hidden" @change="handleFileSelect($event)" required>
                            <div class="p-3 bg-white shadow-sm border border-gray-100 rounded-xl mb-3">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl" :class="isDragging ? 'text-indigo-500' : 'text-gray-400'"></i>
                            </div>
                            <span class="text-sm font-semibold text-gray-700" x-text="fileName || 'Drag and drop your CSV here, or click to browse'"></span>
                            <span class="text-xs text-gray-400 mt-1">Accepts .csv files only · Max 5 MB</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" @click="showImportModal = false; resetModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium shadow-md shadow-emerald-600/10 transition-colors flex items-center gap-2"
                    >
                        <span>Upload & Preview</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection