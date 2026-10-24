<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Add Student
        </h2>
    </x-slot>

    <div class="p-6">
        <div class="bg-white shadow rounded-lg p-6">

            <h3 class="text-2xl font-bold mb-6">Add Student</h3>

            <form action="{{ route('students.store') }}" method="POST">
                @csrf

                <div class="mb-4">
                    <label class="block font-medium mb-2">Name</label>
                    <input type="text" name="name" class="w-full border rounded p-2">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-2">Roll No</label>
                    <input type="text" name="roll_no" class="w-full border rounded p-2">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-2">Class</label>
                    <input type="text" name="class" class="w-full border rounded p-2">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-2">Phone</label>
                    <input type="text" name="phone" class="w-full border rounded p-2">
                </div>

                <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Save Student
                </button>

                <a href="{{ route('students.index') }}"
                    class="ml-2 bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Back
                </a>

            </form>

        </div>
    </div>
</x-app-layout>