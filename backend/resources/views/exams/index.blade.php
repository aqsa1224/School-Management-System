<x-app-layout>
    <x-slot name="header"><h2>Exams</h2></x-slot>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('exams.index') }}" class="d-flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search exam...">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search !== '')
                        <a href="{{ route('exams.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
                <a href="{{ route('exams.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Exam</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>#</th><th>Exam</th><th>Dates</th><th class="text-center">Marks entries</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($exams as $exam)
                            <tr>
                                <td>{{ $exams->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $exam->name }}</td>
                                <td>
                                    @if ($exam->start_date || $exam->end_date)
                                        {{ $exam->start_date?->format('d M Y') ?? '?' }} &ndash; {{ $exam->end_date?->format('d M Y') ?? '?' }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $exam->marks_count }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('marks.entry', ['exam_id' => $exam->id]) }}" class="btn btn-sm btn-outline-dark" title="Enter marks"><i class="bi bi-pencil-square"></i> Marks</a>
                                    <a href="{{ route('marks.index', ['exam_id' => $exam->id]) }}" class="btn btn-sm btn-outline-dark" title="Results"><i class="bi bi-list-check"></i> Results</a>
                                    <a href="{{ route('exams.edit', $exam) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('exams.destroy', $exam) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($exam->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-check fs-1 d-block mb-2"></i>
                                @if ($search !== '') "{{ $search }}" naam ka koi exam nahi mila.
                                @else Abhi koi exam nahi hai. "Add Exam" par click karein. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $exams->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
