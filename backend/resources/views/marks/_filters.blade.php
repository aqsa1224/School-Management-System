{{-- Shared: exam + class + section (+ subject agar $withSubject) --}}
<form method="GET" action="{{ $action }}" class="row g-2 align-items-end mb-3">
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-1">Exam</label>
        <select name="exam_id" class="form-select" required>
            <option value="">Select exam...</option>
            @foreach ($exams as $e)
                <option value="{{ $e->id }}" @selected($exam?->id === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-1">Class</label>
        <select name="school_class_id" id="mk_class" class="form-select" required>
            <option value="">Select class...</option>
            @foreach ($classes as $c)
                <option value="{{ $c->id }}" @selected($class?->id === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small text-muted mb-1">Section</label>
        <select name="section_id" id="mk_section" class="form-select">
            <option value="">All sections</option>
            @foreach ($classes as $c)
                @foreach ($c->sections as $s)
                    <option value="{{ $s->id }}" data-class="{{ $c->id }}" @selected((string) $sectionId === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            @endforeach
        </select>
    </div>
    @if (! empty($withSubject))
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Subject</label>
            <select name="subject_id" id="mk_subject" class="form-select" required>
                <option value="">Select subject...</option>
                @foreach ($classSubjects as $classId => $list)
                    @foreach ($list as $sub)
                        <option value="{{ $sub->id }}" data-class="{{ $classId }}" @selected($subject?->id === $sub->id)>{{ $sub->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-dark" type="submit">{{ $buttonLabel }}</button>
    </div>
</form>

@push('scripts')
    <script>
        // Class chunne par sirf us class ke sections (aur subjects) dikhao
        (function () {
            var cls = document.getElementById('mk_class');
            var targets = ['mk_section', 'mk_subject'].map(function (id) { return document.getElementById(id); }).filter(Boolean);
            function sync() {
                targets.forEach(function (sel) {
                    Array.prototype.forEach.call(sel.options, function (o) {
                        if (!o.value) { return; }
                        var hide = cls.value === '' || o.dataset.class !== cls.value;
                        o.hidden = hide; o.disabled = hide;
                    });
                    var cur = sel.options[sel.selectedIndex];
                    if (cur && cur.value && cur.disabled) { sel.value = ''; }
                });
            }
            cls.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
