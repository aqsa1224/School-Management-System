<x-app-layout>
    <x-slot name="header"><h2>Add Student</h2></x-slot>
    <div class="row"><div class="col-xl-10">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('students.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('students._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4">Save Student</button>
                    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
