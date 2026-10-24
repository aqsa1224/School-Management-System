<x-app-layout>
    <x-slot name="header"><h2>Edit Student</h2></x-slot>
    <div class="row"><div class="col-xl-10">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('students.update', $student) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('students._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4">Update Student</button>
                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
