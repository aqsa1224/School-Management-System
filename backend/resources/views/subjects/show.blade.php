<x-app-layout>
    <x-slot name="header"><h2>{{ $subject->name }}</h2></x-slot>

    <div class="mb-3"><a href="{{ route('subjects.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Subjects</a></div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Subject details</h5>
                <dl class="mb-0">
                    <dt>Name</dt><dd>{{ $subject->name }}</dd>
                    <dt>Code</dt><dd>{{ $subject->code ?: '-' }}</dd>
                    <dt>Classes</dt>
                    <dd class="mb-0">
                        @forelse ($subject->classes as $class)
                            <a href="{{ route('classes.show', $class) }}" class="badge text-bg-dark text-decoration-none">{{ $class->name }}</a>
                        @empty
                            <span class="text-muted">Kisi class se jora nahi hai</span>
                        @endforelse
                    </dd>
                </dl>
                <hr>
                <div class="d-flex gap-2">
                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST"
                          onsubmit="return confirm('Delete {{ addslashes($subject->name) }}?');">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                </div>
            </div></div>
        </div>

        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Teachers jo ye subject parhate hain</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-dark"><tr><th>Teacher</th><th>Class</th><th>Section</th></tr></thead>
                        <tbody>
                            @forelse ($subject->assignments as $a)
                                <tr>
                                    <td><a href="{{ route('teachers.show', $a->teacher) }}" class="text-decoration-none">{{ $a->teacher->name }}</a></td>
                                    <td>{{ $a->schoolClass->name }}</td>
                                    <td>{{ $a->section?->name ?? 'All sections' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Abhi kisi teacher ko ye subject assign nahi hua.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>
</x-app-layout>
