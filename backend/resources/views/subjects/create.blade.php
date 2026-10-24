<x-app-layout>
    <x-slot name="header"><h2>Add Subject</h2></x-slot>
    <div class="row"><div class="col-lg-7">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('subjects.store') }}" method="POST">
                @csrf
                @include('subjects._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Save Subject</button>
                    <a href="{{ route('subjects.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
