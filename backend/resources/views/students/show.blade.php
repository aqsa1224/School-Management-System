<x-app-layout>
    <x-slot name="header"><h2>{{ $student->full_name }}</h2></x-slot>

    <div class="mb-3"><a href="{{ route('students.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Students</a></div>

    <div class="row g-4">
        {{-- Profile card --}}
        <div class="col-lg-4">
            <div class="card"><div class="card-body text-center">
                @if ($student->photo_url)
                    <img src="{{ $student->photo_url }}" alt="" class="rounded-circle mb-3" style="width:110px;height:110px;object-fit:cover">
                @else
                    <span class="avatar mb-3" style="width:110px;height:110px;font-size:2.2rem">{{ $student->initials }}</span>
                @endif
                <h5 class="mb-0">{{ $student->full_name }}</h5>
                <div class="text-muted">{{ $student->admission_no }}</div>
                @php $badge = ['active' => 'text-bg-success', 'left' => 'text-bg-secondary', 'passed_out' => 'text-bg-info'][$student->status] ?? 'text-bg-secondary'; @endphp
                <span class="badge {{ $badge }} mt-2">{{ ucfirst(str_replace('_', ' ', $student->status)) }}</span>
                <hr>
                <div class="d-flex gap-2 justify-content-center">
                    @can('students.manage')
                        <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    @endcan
                    @can('students.delete')
                        <form action="{{ route('students.destroy', $student) }}" method="POST"
                              onsubmit="return confirm('Delete {{ addslashes($student->full_name) }}?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                        </form>
                    @endcan
                </div>
            </div></div>
        </div>

        <div class="col-lg-8">
            {{-- Summary --}}
            <div class="row g-3 mb-4">
                @can('attendance.manage')
                <div class="col-sm-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Attendance</div>
                        <div class="fs-5 fw-semibold">
                            {{ $attendance->get('present', 0) }} P /
                            {{ $attendance->get('absent', 0) }} A /
                            {{ $attendance->get('late', 0) }} L /
                            {{ $attendance->get('leave', 0) }} Lv
                        </div>
                    </div></div>
                </div>
                @endcan
                @can('fees.view')
                <div class="col-sm-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Fees balance</div>
                        <div class="fs-5 fw-semibold">{{ number_format($feeSummary['balance'], 2) }}</div>
                        <div class="text-muted small">Paid {{ number_format($feeSummary['paid'], 2) }} of {{ number_format($feeSummary['total'], 2) }}</div>
                    </div></div>
                </div>
                @endcan
                @can('marks.manage')
                <div class="col-sm-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small">Marks entries</div>
                        <div class="fs-5 fw-semibold">{{ $marksCount }}</div>
                    </div></div>
                </div>
                @endcan
            </div>

            {{-- Results --}}
            @if ($examsTaken->isNotEmpty() && auth()->user()->can('marks.manage'))
                <div class="card mb-4"><div class="card-body">
                    <h5 class="mb-3">Result cards</h5>
                    @foreach ($examsTaken as $exam)
                        <a href="{{ route('marks.card', ['student' => $student, 'exam_id' => $exam->id]) }}" class="btn btn-outline-gold btn-sm me-1 mb-1">
                            <i class="bi bi-file-earmark-text me-1"></i>{{ $exam->name }}
                        </a>
                    @endforeach
                </div></div>
            @endif

            {{-- Fees --}}
            @can('fees.view')
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Fee vouchers</h5>
                    @can('fees.manage')
                        <a href="{{ route('fees.create', ['student_id' => $student->id]) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>New voucher</a>
                    @endcan
                </div>
                @if ($studentFees->isEmpty())
                    <div class="text-muted">Abhi koi fee voucher nahi hai.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Voucher</th><th>Fee</th><th class="text-end">Net</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($studentFees as $f)
                                    @php $b = ['unpaid' => 'text-bg-danger', 'partial' => 'text-bg-warning', 'paid' => 'text-bg-success'][$f->status] ?? 'text-bg-secondary'; @endphp
                                    <tr>
                                        <td><a href="{{ route('fees.show', $f) }}" class="text-decoration-none">{{ $f->voucher_no }}</a></td>
                                        <td>{{ $f->feeType->name }}{{ $f->for_month ? ' - ' . $f->for_month->format('M Y') : '' }}</td>
                                        <td class="text-end">{{ number_format($f->net_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($f->balance, 2) }}</td>
                                        <td><span class="badge {{ $b }}">{{ ucfirst($f->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div></div>
            @endcan

            {{-- Details --}}
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Student details</h5>
                <div class="row">
                    <div class="col-md-6">
                        <dl>
                            <dt>Class</dt>
                            <dd>
                                @can('academics.manage')
                                    <a href="{{ route('classes.show', $student->schoolClass) }}" class="text-decoration-none">{{ $student->schoolClass->name }}</a>
                                @else
                                    {{ $student->schoolClass->name }}
                                @endcan
                                {{ $student->section ? ' - Section ' . $student->section->name : '' }}</dd>
                            <dt>Gender</dt><dd>{{ ucfirst($student->gender) }}</dd>
                            <dt>Date of birth</dt><dd>{{ $student->date_of_birth?->format('d M Y') ?? '-' }}</dd>
                            <dt>Admission date</dt><dd>{{ $student->admission_date?->format('d M Y') ?? '-' }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl>
                            <dt>Phone</dt><dd>{{ $student->phone ?: '-' }}</dd>
                            <dt>Email</dt><dd>{{ $student->email ?: '-' }}</dd>
                            <dt>Guardian</dt><dd>{{ $student->guardian_name }} ({{ $student->guardian_phone }})</dd>
                            <dt>Address</dt><dd>{{ $student->address ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div></div>
        </div>
    </div>
</x-app-layout>
