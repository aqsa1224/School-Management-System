<x-app-layout>
    <x-slot name="header"><h2>Edit Exam</h2></x-slot>
    <div class="row"><div class="col-lg-6">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('exams.update', $exam) }}" method="POST">
                @csrf @method('PUT')
                @include('exams._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Update Exam</button>
                    <a href="{{ route('exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
