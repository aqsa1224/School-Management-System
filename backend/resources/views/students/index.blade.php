<x-app-layout>
    <x-slot name="header"><h2>Students</h2></x-slot>

    <div class="card">
        <div class="card-body">

            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
                <form method="GET" action="{{ route('students.index') }}" class="row g-2 flex-grow-1" style="max-width:900px">
                    <div class="col-md-4">
                        <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Name, admission no, guardian, phone...">
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="class_id" id="f_class" class="form-select">
                            <option value="">All classes</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected((string) $classId === (string) $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="section_id" id="f_section" class="form-select">
                            <option value="">All sections</option>
                            @foreach ($classes as $class)
                                @foreach ($class->sections as $section)
                                    <option value="{{ $section->id }}" data-class="{{ $class->id }}" @selected((string) $secId === (string) $section->id)>{{ $section->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All status</option>
                            <option value="active" @selected($status === 'active')>Active</option>
                            <option value="left" @selected($status === 'left')>Left</option>
                            <option value="passed_out" @selected($status === 'passed_out')>Passed out</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="gender" class="form-select">
                            <option value="">Any gender</option>
                            <option value="male" @selected($gender === 'male')>Male</option>
                            <option value="female" @selected($gender === 'female')>Female</option>
                            <option value="other" @selected($gender === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-dark" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        @if ($search !== '' || $classId !== '' || $secId !== '' || $status !== '' || $gender !== '')
                            <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </form>

                @can('students.manage')
                    <a href="{{ route('students.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Student</a>
                @endcan
            </div>

            <div class="text-muted small mb-2">{{ $students->total() }} student(s) mile</div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Student</th><th>Admission no</th><th>Class</th><th>Guardian</th><th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($student->photo_url)
                                            <img src="{{ $student->photo_url }}" alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover">
                                        @else
                                            <span class="avatar" style="width:36px;height:36px">{{ $student->initials }}</span>
                                        @endif
                                        <a href="{{ route('students.show', $student) }}" class="fw-semibold text-decoration-none">{{ $student->full_name }}</a>
                                    </div>
                                </td>
                                <td>{{ $student->admission_no }}</td>
                                <td>{{ $student->schoolClass->name }}{{ $student->section ? ' - ' . $student->section->name : '' }}</td>
                                <td>{{ $student->guardian_name }}<div class="text-muted small">{{ $student->guardian_phone }}</div></td>
                                <td>
                                    @php $badge = ['active' => 'text-bg-success', 'left' => 'text-bg-secondary', 'passed_out' => 'text-bg-info'][$student->status] ?? 'text-bg-secondary'; @endphp
                                    <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $student->status)) }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('students.show', $student) }}" class="btn btn-sm btn-outline-dark" title="Profile"><i class="bi bi-eye"></i></a>
                                    @can('students.manage')
                                        <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                    @can('students.delete')
                                        <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete {{ addslashes($student->full_name) }}?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                @if ($search !== '' || $classId !== '' || $secId !== '' || $status !== '' || $gender !== '')
                                    Is search/filter se koi student nahi mila.
                                @else
                                    Abhi koi student nahi hai.@can('students.manage') "Add Student" par click karein.@endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $students->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Class filter chunne par sirf us class ke sections dikhao
            (function () {
                var cls = document.getElementById('f_class');
                var sec = document.getElementById('f_section');
                function sync() {
                    Array.prototype.forEach.call(sec.options, function (o) {
                        if (!o.value) { return; }
                        var hide = cls.value !== '' && o.dataset.class !== cls.value;
                        o.hidden = hide; o.disabled = hide;
                    });
                    var cur = sec.options[sec.selectedIndex];
                    if (cur && cur.value && cur.disabled) { sec.value = ''; }
                }
                cls.addEventListener('change', sync); sync();
            })();
        </script>
    @endpush
</x-app-layout>
