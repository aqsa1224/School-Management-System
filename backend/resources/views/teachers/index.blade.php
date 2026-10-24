<x-app-layout>
    <x-slot name="header"><h2>Teachers</h2></x-slot>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('teachers.index') }}" class="d-flex flex-wrap gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" style="width:230px" placeholder="Name, email or phone...">
                    <select name="status" class="form-select" style="width:150px">
                        <option value="">All status</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="left" @selected($status === 'left')>Left</option>
                    </select>
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search !== '' || $status !== '')
                        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
                <a href="{{ route('teachers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Teacher</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
                            <th class="text-center">Assignments</th><th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teachers as $teacher)
                            <tr>
                                <td>{{ $teachers->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold"><a href="{{ route('teachers.show', $teacher) }}" class="text-decoration-none">{{ $teacher->name }}</a></td>
                                <td>{{ $teacher->email }}</td>
                                <td>{{ $teacher->phone ?: '-' }}</td>
                                <td class="text-center">{{ $teacher->assignments_count }}</td>
                                <td>
                                    <span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($teacher->status) }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('teachers.show', $teacher) }}" class="btn btn-sm btn-outline-dark" title="Details"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('teachers.destroy', $teacher) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($teacher->name) }}? Is ka login account aur assignments bhi hat jayenge.');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
                                @if ($search !== '' || $status !== '') Is search/filter se koi teacher nahi mila.
                                @else Abhi koi teacher nahi hai. "Add Teacher" par click karein. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $teachers->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
