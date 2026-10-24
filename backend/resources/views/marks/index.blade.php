<x-app-layout>
    <x-slot name="header"><h2>Marks / Results</h2></x-slot>

    @if ($allowed !== null && $classes->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            Aap ka account "teacher" hai, lekin aap ko abhi koi class assign nahi hui (ya aap ka teacher record link nahi hai).
            Admin se kahein ke Teachers me ja kar aap ko class/subject assign kar de.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="flex-grow-1" style="max-width:820px">
                    @include('marks._filters', ['action' => route('marks.index'), 'buttonLabel' => 'Show results'])
                </div>
                @if ($classes->isNotEmpty())
                    <a href="{{ route('marks.entry', array_filter(['exam_id' => $exam?->id, 'school_class_id' => $class?->id, 'section_id' => $sectionId])) }}"
                       class="btn btn-primary mt-4 mt-md-0"><i class="bi bi-pencil-square me-1"></i> Enter Marks</a>
                @endif
            </div>

            @if ($result === null)
                <div class="text-muted"><i class="bi bi-arrow-up"></i> Exam aur class chun kar "Show results" dabayein.</div>
            @else
                @php
                    $gradeColor = ['A+' => 'success', 'A' => 'success', 'B' => 'primary', 'C' => 'info', 'D' => 'warning', 'F' => 'danger'];
                @endphp
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <div>
                        <strong>{{ $exam->name }}</strong> | {{ $class->name }}
                        <span class="text-muted small">| Pass marks: {{ rtrim(rtrim(number_format($passMark, 2), '0'), '.') }}% (har subject me)</span>
                    </div>
                </div>

                @if ($result['subjects']->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                        Is exam ke is class me abhi koi marks enter nahi hue.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student</th>
                                    @foreach ($result['subjects'] as $sub)
                                        <th class="text-center">{{ $sub->name }}</th>
                                    @endforeach
                                    <th class="text-center">Total</th>
                                    <th class="text-center">%</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Result</th>
                                    <th class="text-center">Pos.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['rows'] as $row)
                                    @php $s = $row['student']; @endphp
                                    <tr>
                                        <td class="text-nowrap">
                                            <a href="{{ route('students.show', $s) }}" class="fw-semibold text-decoration-none">{{ $s->full_name }}</a>
                                            <div class="text-muted small">{{ $s->admission_no }}{{ $s->section ? ' | ' . $s->section->name : '' }}</div>
                                        </td>
                                        @foreach ($result['subjects'] as $sub)
                                            @php $m = $row['marks']->get($sub->id); @endphp
                                            <td class="text-center {{ $m && $m->percentage < $passMark ? 'text-danger fw-semibold' : '' }}">
                                                {{ $m ? (float) $m->obtained_marks . '/' . (float) $m->total_marks : '-' }}
                                            </td>
                                        @endforeach
                                        @if ($row['percentage'] !== null)
                                            <td class="text-center">{{ (float) $row['obtained'] }}/{{ (float) $row['total'] }}</td>
                                            <td class="text-center">{{ $row['percentage'] }}</td>
                                            <td class="text-center"><span class="badge text-bg-{{ $gradeColor[$row['grade']] }}">{{ $row['grade'] }}</span></td>
                                            <td class="text-center">
                                                <span class="badge {{ $row['result'] === 'Pass' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $row['result'] }}</span>
                                            </td>
                                            <td class="text-center fw-semibold">{{ $row['position'] }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('marks.card', ['student' => $s, 'exam_id' => $exam->id]) }}" class="btn btn-sm btn-outline-gold" title="Result card"><i class="bi bi-file-earmark-text"></i></a>
                                            </td>
                                        @else
                                            <td colspan="6" class="text-center text-muted small">Marks nahi lage</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
