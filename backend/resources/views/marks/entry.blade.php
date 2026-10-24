<x-app-layout>
    <x-slot name="header"><h2>Enter Marks</h2></x-slot>

    @if ($allowed !== null && $classes->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            Aap ka account "teacher" hai, lekin aap ko abhi koi class assign nahi hui (ya aap ka teacher record link nahi hai).
            Admin se kahein ke Teachers me ja kar aap ko class/subject assign kar de.
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div style="max-width:980px">
                @include('marks._filters', [
                    'action' => route('marks.entry'), 'buttonLabel' => 'Load students', 'withSubject' => true,
                ])
            </div>

            @if ($denied)
                <div class="alert alert-danger mb-0">{{ $denied }}</div>
            @elseif ($students === null)
                <div class="text-muted"><i class="bi bi-arrow-up"></i> Exam, class, section aur subject chun kar "Load students" dabayein.</div>
            @endif
        </div>
    </div>

    @if ($students !== null)
        <form method="POST" action="{{ route('marks.store') }}">
            @csrf
            <input type="hidden" name="exam_id" value="{{ $exam->id }}">
            <input type="hidden" name="school_class_id" value="{{ $class->id }}">
            <input type="hidden" name="section_id" value="{{ $sectionId }}">
            <input type="hidden" name="subject_id" value="{{ $subject->id }}">

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <strong>{{ $exam->name }}</strong> | {{ $class->name }} | {{ $subject->name }}
                            <span class="text-muted">| {{ $students->count() }} student(s)</span>
                            @if ($records->isNotEmpty())
                                <span class="badge text-bg-info ms-1">{{ $records->count() }} ke marks pehle se lage hain</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label for="total_marks" class="form-label mb-0 fw-medium">Total marks</label>
                            <input type="number" step="0.01" min="1" max="1000" id="total_marks" name="total_marks"
                                   value="{{ old('total_marks', (float) $totalMarks) }}"
                                   class="form-control @error('total_marks') is-invalid @enderror" style="width:110px" required>
                        </div>
                    </div>

                    @if ($students->isEmpty())
                        <div class="text-center text-muted py-5">Is class/section me koi active student nahi hai.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-dark">
                                    <tr><th style="width:40px">#</th><th>Student</th><th style="width:170px">Obtained marks</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        @php $rec = $records->get($student->id); @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $student->full_name }}</div>
                                                <div class="text-muted small">{{ $student->admission_no }}{{ $student->section ? ' | ' . $student->section->name : '' }}</div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="marks[{{ $student->id }}]"
                                                       value="{{ old("marks.{$student->id}", $rec ? (float) $rec->obtained_marks : '') }}"
                                                       class="form-control form-control-sm @error("marks.{$student->id}") is-invalid @enderror"
                                                       placeholder="-">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Marks</button>
                            <a href="{{ route('marks.index', array_filter(['exam_id' => $exam->id, 'school_class_id' => $class->id, 'section_id' => $sectionId])) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                        <div class="form-text mt-2">Jis student ka box khali chhorenge us ke marks nahi lagenge (pehle se lage hon to waise hi rahenge). Marks dobara save karne par update ho jate hain.</div>
                    @endif
                </div>
            </div>
        </form>
    @endif
</x-app-layout>
