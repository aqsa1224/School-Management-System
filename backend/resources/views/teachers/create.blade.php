<x-app-layout>
    <x-slot name="header"><h2>Add Teacher</h2></x-slot>
    <div class="row"><div class="col-xl-9">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('teachers.store') }}" method="POST">
                @csrf
                @include('teachers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Save Teacher</button>
                    <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
