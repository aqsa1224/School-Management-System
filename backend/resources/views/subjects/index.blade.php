<x-app-layout>
    <x-slot name="header"><h2>Subjects</h2></x-slot>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('subjects.index') }}" class="d-flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search name or code...">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search !== '')
                        <a href="{{ route('subjects.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
                <a href="{{ route('subjects.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Subject</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Subject</th><th>Code</th>
                            <th class="text-center">Classes</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subjects as $subject)
                            <tr>
                                <td>{{ $subjects->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold"><a href="{{ route('subjects.show', $subject) }}" class="text-decoration-none">{{ $subject->name }}</a></td>
                                <td>{{ $subject->code ?: '-' }}</td>
                                <td class="text-center">{{ $subject->classes_count }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('subjects.show', $subject) }}" class="btn btn-sm btn-outline-dark" title="Details"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($subject->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-book fs-1 d-block mb-2"></i>
                                @if ($search !== '') "{{ $search }}" naam ka koi subject nahi mila.
                                @else Abhi koi subject nahi hai. "Add Subject" par click karein. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $subjects->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
