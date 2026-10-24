<x-app-layout>
    <x-slot name="header"><h2>Mark Attendance</h2></x-slot>

    @if ($allowed !== null && $classes->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            Aap ka account "teacher" hai, lekin aap ko abhi koi class assign nahi hui (ya aap ka teacher record link nahi hai).
            Admin se kahein ke Teachers me ja kar aap ko class/subject assign kar de.
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div style="max-width:760px">
                @include('attendances._filters', [
                    'action' => route('attendances.mark'),
                    'buttonLabel' => 'Load students',
                    'allClassesLabel' => 'Select class...',
                    'maxDate' => today()->toDateString(),
                ])
            </div>

            @if ($students === null)
                <div class="text-muted"><i class="bi bi-arrow-up"></i> Pehle class (aur section) chun kar "Load students" dabayein.</div>
            @elseif ($future)
                <div class="alert alert-danger mb-0">Aane wali tareekh ki attendance nahi lagayi ja sakti. Aaj ya pichli tareekh chunein.</div>
            @endif
        </div>
    </div>

    @if ($students !== null && ! $future)
        <form method="POST" action="{{ route('attendances.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="school_class_id" value="{{ $classId }}">
            <input type="hidden" name="section_id" value="{{ $sectionId }}">

            <div class="card">
                <div class="card-body">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <strong>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</strong>
                            <span class="text-muted">| {{ $students->count() }} student(s)</span>
                            @if ($records->isNotEmpty())
                                <span class="badge text-bg-info ms-1">Pehle se {{ $records->count() }} ki attendance lagi hui hai</span>
                            @endif
                        </div>
                        @if ($students->isNotEmpty())
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-success" data-all="present">Sab Present</button>
                                <button type="button" class="btn btn-outline-danger" data-all="absent">Sab Absent</button>
                            </div>
                        @endif
                    </div>

                    @if ($students->isEmpty())
                        <div class="text-center text-muted py-5">Is class/section me koi active student nahi hai.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-dark">
                                    <tr><th style="width:40px">#</th><th>Student</th><th>Status</th><th style="min-width:180px">Remarks</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        @php
                                            $rec = $records->get($student->id);
                                            $cur = old("attendance.{$student->id}.status", $rec?->status ?? 'present');
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $student->full_name }}</div>
                                                <div class="text-muted small">{{ $student->admission_no }}{{ $student->section ? ' | ' . $student->section->name : '' }}</div>
                                            </td>
                                            <td class="text-nowrap">
                                                @foreach (['present' => ['P', 'success'], 'absent' => ['A', 'danger'], 'late' => ['L', 'warning'], 'leave' => ['Lv', 'info']] as $val => [$label, $color])
                                                    <input type="radio" class="btn-check att-radio" autocomplete="off"
                                                           name="attendance[{{ $student->id }}][status]" value="{{ $val }}"
                                                           id="att-{{ $student->id }}-{{ $val }}" @checked($cur === $val)>
                                                    <label class="btn btn-sm btn-outline-{{ $color }}" for="att-{{ $student->id }}-{{ $val }}" title="{{ ucfirst($val) }}">{{ $label }}</label>
                                                @endforeach
                                            </td>
                                            <td>
                                                <input type="text" name="attendance[{{ $student->id }}][remarks]" maxlength="255"
                                                       value="{{ old("attendance.{$student->id}.remarks", $rec?->remarks) }}"
                                                       class="form-control form-control-sm" placeholder="optional">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Attendance</button>
                            <a href="{{ route('attendances.index', array_filter(['date' => $date, 'school_class_id' => $classId, 'section_id' => $sectionId])) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                        <div class="form-text mt-2">P = Present, A = Absent, L = Late, Lv = Leave. Pehle se lagi hui attendance dobara save karne par update ho jati hai (duplicate nahi banti).</div>
                    @endif
                </div>
            </div>
        </form>

        @push('scripts')
            <script>
                // "Sab Present / Sab Absent" buttons
                document.querySelectorAll('[data-all]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var value = btn.getAttribute('data-all');
                        document.querySelectorAll('.att-radio[value="' + value + '"]').forEach(function (r) { r.checked = true; });
                    });
                });
            </script>
        @endpush
    @endif
</x-app-layout>
