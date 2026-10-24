<x-app-layout>
    <x-slot name="header"><h2>Attendance</h2></x-slot>

    @php
        $badges = ['present' => 'text-bg-success', 'absent' => 'text-bg-danger', 'late' => 'text-bg-warning', 'leave' => 'text-bg-info'];
        $markUrl = route('attendances.mark', array_filter(['date' => $date, 'school_class_id' => $classId, 'section_id' => $sectionId]));
    @endphp

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
                <div class="flex-grow-1" style="max-width:760px">
                    @include('attendances._filters', ['action' => route('attendances.index'), 'buttonLabel' => 'Show'])
                </div>
                @if ($classes->isNotEmpty())
                    <a href="{{ $markUrl }}" class="btn btn-primary mt-4 mt-md-0"><i class="bi bi-calendar-check me-1"></i> Mark Attendance</a>
                @endif
            </div>

            @if ($students !== null)
                {{-- ===== Student-wise (class chuni gayi) ===== --}}
                <div class="row g-2 mb-3 text-center">
                    @foreach (['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'leave' => 'info', 'not_marked' => 'secondary'] as $key => $color)
                        <div class="col">
                            <div class="border rounded py-2">
                                <div class="fs-4 fw-semibold text-{{ $color }}">{{ $summary[$key] }}</div>
                                <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', $key) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr><th>Student</th><th>Admission no</th><th>Section</th><th>Status</th><th>Remarks</th><th>Marked by</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $student)
                                @php $r = $records->get($student->id); @endphp
                                <tr>
                                    <td class="fw-semibold"><a href="{{ route('students.show', $student) }}" class="text-decoration-none">{{ $student->full_name }}</a></td>
                                    <td>{{ $student->admission_no }}</td>
                                    <td>{{ $student->section?->name ?? '-' }}</td>
                                    <td>
                                        @if ($r)
                                            <span class="badge {{ $badges[$r->status] }}">{{ ucfirst($r->status) }}</span>
                                        @else
                                            <span class="badge text-bg-light border">Not marked</span>
                                        @endif
                                    </td>
                                    <td>{{ $r?->remarks ?: '-' }}</td>
                                    <td class="text-muted small">{{ $r?->markedBy?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">Is class/section me koi active student nahi hai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif ($overview !== null)
                {{-- ===== Class-wise summary (class nahi chuni) ===== --}}
                <div class="text-muted small mb-2">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }} ki class-wise summary</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Class</th><th class="text-center">Students</th>
                                <th class="text-center">Present</th><th class="text-center">Absent</th>
                                <th class="text-center">Late</th><th class="text-center">Leave</th>
                                <th class="text-center">Not marked</th><th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overview as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['class']->name }}</td>
                                    <td class="text-center">{{ $row['students'] }}</td>
                                    <td class="text-center text-success">{{ $row['present'] }}</td>
                                    <td class="text-center text-danger">{{ $row['absent'] }}</td>
                                    <td class="text-center text-warning">{{ $row['late'] }}</td>
                                    <td class="text-center text-info">{{ $row['leave'] }}</td>
                                    <td class="text-center">
                                        @if ($row['students'] > 0 && $row['not_marked'] === $row['students'])
                                            <span class="badge text-bg-warning">Not taken</span>
                                        @else
                                            {{ $row['not_marked'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('attendances.index', ['date' => $date, 'school_class_id' => $row['class']->id]) }}">View</a>
                                        <a class="btn btn-sm btn-outline-gold" href="{{ route('attendances.mark', ['date' => $date, 'school_class_id' => $row['class']->id]) }}">Mark</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-5">Koi class nahi hai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
