@extends('layouts.admin')

@section('title', 'Register Teacher')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Back Button -->
    <a href="{{ route('admin.teachers') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-blue-600 mb-6 transition-colors">
        <i class="fa-solid fa-arrow-left mr-2"></i> Back to Teachers List
    </a>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-8">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 text-xl">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Teacher Details</h3>
                    <p class="text-sm text-gray-500">Fill in the information below to register a new teacher.</p>
                </div>
            </div>

            @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('admin.teachers.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="col-span-2 md:col-span-1">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <div class="relative">
                            <i class="fa-regular fa-user absolute left-3 top-3.5 text-gray-400"></i>
                            <input type="text" name="name" id="name" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                                placeholder="e.g. Dr. Sarah Wilson">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-span-2 md:col-span-1">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <i class="fa-regular fa-envelope absolute left-3 top-3.5 text-gray-400"></i>
                            <input type="email" name="email" id="email" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                                placeholder="e.g. sarah.wilson@astria.edu">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="col-span-2 md:col-span-1">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3 top-3.5 text-gray-400"></i>
                            <input type="password" name="password" id="password" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="col-span-2 md:col-span-1">
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject / Department</label>
                        <div class="relative">
                            <i class="fa-solid fa-book absolute left-3 top-3.5 text-gray-400"></i>
                            <select name="subject" id="subject" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none appearance-none">
                                <option value="" disabled selected>Select Subject</option>
                                @foreach($subjects as $subject)
                                <option value="{{ $subject->name }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-xs text-gray-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Education Level -->
                    <div class="col-span-2 md:col-span-1">
                        <label for="education_level" class="block text-sm font-medium text-gray-700 mb-2">Education Level</label>
                        <div class="relative">
                            <i class="fa-solid fa-graduation-cap absolute left-3 top-3.5 text-gray-400"></i>
                            <select name="education_level" id="education_level" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none appearance-none">
                                <option value="" disabled selected>Select Education Level</option>
                                <option value="Bachelor">Bachelor</option>
                                <option value="Master">Master</option>
                                <option value="PhD">PhD</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Certificate">Certificate</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-xs text-gray-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Assigned Classes -->
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Classes (Optional)</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach($classes as $class)
                            <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" name="classes[]" value="{{ $class->id }}"
                                    class="form-checkbox h-5 w-5 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                                <span class="text-sm text-gray-700">{{ $class->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Profile Image -->
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
                        <div class="flex items-center justify-center w-full">
                            <label for="image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                    <p class="text-xs text-gray-500">PNG, JPG or JPEG (MAX. 2MB)</p>
                                </div>
                                <input id="image" name="image" type="file" class="hidden" accept="image/*"  />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="window.history.back()" class="px-6 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all">
                        Register Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection