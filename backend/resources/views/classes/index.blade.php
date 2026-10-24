<x-app-layout>
    <x-slot name="header">
        <h2>Classes</h2>
    </x-slot>

    <div class="card">
        <div class="card-body">

            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('classes.index') }}" class="d-flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search class...">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search !== '')
                        <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>

                <a href="{{ route('classes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Class
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Class</th>
                            <th class="text-center">Sections</th>
                            <th class="text-center">Students</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($classes as $class)
                            <tr>
                                <td>{{ $classes->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">
                                    <a href="{{ route('classes.show', $class) }}" class="text-decoration-none">{{ $class->name }}</a>
                                </td>
                                <td class="text-center">{{ $class->sections_count }}</td>
                                <td class="text-center">{{ $class->students_count }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('classes.show', $class) }}" class="btn btn-sm btn-outline-dark" title="Sections / Details"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('classes.edit', $class) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('classes.destroy', $class) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($class->name) }}? Is ke sections bhi delete ho jayenge.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-building fs-1 d-block mb-2"></i>
                                    @if ($search !== '')
                                        "{{ $search }}" naam ki koi class nahi mili.
                                    @else
                                        Abhi koi class nahi hai. "Add Class" par click karein.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $classes->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</x-app-layout>
