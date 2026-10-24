<x-app-layout>
    <x-slot name="header"><h2>Edit Teacher</h2></x-slot>
    <div class="row"><div class="col-xl-9">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('teachers.update', $teacher) }}" method="POST">
                @csrf @method('PUT')
                @include('teachers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Update Teacher</button>
                    <a href="{{ route('teachers.show', $teacher) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
