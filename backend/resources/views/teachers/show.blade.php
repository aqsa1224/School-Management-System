<x-app-layout>
    <x-slot name="header"><h2>{{ $teacher->name }}</h2></x-slot>

    <div class="mb-3"><a href="{{ route('teachers.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Teachers</a></div>

    <div class="row g-4">
        {{-- Details --}}
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Teacher details</h5>
                <dl class="mb-0">
                    <dt>Name</dt><dd>{{ $teacher->name }}</dd>
                    <dt>Email</dt><dd>{{ $teacher->email }}</dd>
                    <dt>Phone</dt><dd>{{ $teacher->phone ?: '-' }}</dd>
                    <dt>Gender</dt><dd>{{ $teacher->gender ? ucfirst($teacher->gender) : '-' }}</dd>
                    <dt>Qualification</dt><dd>{{ $teacher->qualification ?: '-' }}</dd>
                    <dt>Joining date</dt><dd>{{ $teacher->joining_date?->format('d M Y') ?? '-' }}</dd>
                    <dt>Address</dt><dd>{{ $teacher->address ?: '-' }}</dd>
                    <dt>Status</dt>
                    <dd><span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($teacher->status) }}</span></dd>
                    <dt>Login account</dt>
                    <dd class="mb-0">{!! $teacher->user_id ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' !!}</dd>
                </dl>
                <hr>
                <div class="d-flex gap-2">
                    <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <form action="{{ route('teachers.destroy', $teacher) }}" method="POST"
                          onsubmit="return confirm('Delete {{ addslashes($teacher->name) }}? Is ka login account aur assignments bhi hat jayenge.');">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                </div>
            </div></div>
        </div>

        {{-- Assignments --}}
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Class / Subject assignments</h5>

                <form action="{{ route('teachers.assignments.store', $teacher) }}" method="POST" class="row g-2 mb-4">
                    @csrf
                    <div class="col-md-4">
                        <select name="school_class_id" id="school_class_id" class="form-select" required>
                            <option value="">Class...</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="section_id" id="section_id" class="form-select">
                            <option value="">All sections</option>
                            @foreach ($classes as $class)
                                @foreach ($class->sections as $section)
                                    <option value="{{ $section->id }}" data-class="{{ $class->id }}" @selected(old('section_id') == $section->id)>{{ $section->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="subject_id" id="subject_id" class="form-select" required>
                            <option value="">Subject...</option>
                            @foreach ($classes as $class)
                                @foreach ($class->subjects as $subject)
                                    <option value="{{ $subject->id }}" data-class="{{ $class->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
                    </div>
                    <div class="form-text">Pehle class chunein, phir us class ke sections aur subjects dikhenge. Subject class se jora hona chahiye (Subjects me).</div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-dark"><tr><th>Class</th><th>Section</th><th>Subject</th><th class="text-end">Remove</th></tr></thead>
                        <tbody>
                            @forelse ($teacher->assignments as $a)
                                <tr>
                                    <td>{{ $a->schoolClass->name }}</td>
                                    <td>{{ $a->section?->name ?? 'All sections' }}</td>
                                    <td>{{ $a->subject->name }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('teachers.assignments.destroy', [$teacher, $a]) }}" method="POST"
                                              onsubmit="return confirm('Ye assignment hata dein?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Abhi koi class/subject assign nahi hua.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Class chunne par sirf us class ke sections aur subjects dikhao
            (function () {
                var cls = document.getElementById('school_class_id');
                var sec = document.getElementById('section_id');
                var sub = document.getElementById('subject_id');

                function filter(select) {
                    Array.prototype.forEach.call(select.options, function (o) {
                        if (!o.value) { return; }
                        var hide = o.dataset.class !== cls.value;
                        o.hidden = hide; o.disabled = hide;
                    });
                    var cur = select.options[select.selectedIndex];
                    if (cur && cur.value && cur.disabled) { select.value = ''; }
                }
                function sync() { filter(sec); filter(sub); }
                cls.addEventListener('change', sync); sync();
            })();
        </script>
    @endpush
</x-app-layout>
