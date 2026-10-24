{{-- Shared filter bar: date + class + section. $action = form ka GET URL --}}
<form method="GET" action="{{ $action }}" class="row g-2 align-items-end mb-3" id="att-filter">
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-1">Date</label>
        <input type="date" name="date" value="{{ $date }}" max="{{ $maxDate ?? '' }}" class="form-control" required>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-1">Class</label>
        <select name="school_class_id" id="att_class" class="form-select">
            <option value="">{{ $allClassesLabel ?? 'All classes' }}</option>
            @foreach ($classes as $c)
                <option value="{{ $c->id }}" @selected((string) $classId === (string) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-1">Section</label>
        <select name="section_id" id="att_section" class="form-select">
            <option value="">All sections</option>
            @foreach ($classes as $c)
                @foreach ($c->sections as $s)
                    <option value="{{ $s->id }}" data-class="{{ $c->id }}" @selected((string) $sectionId === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 d-flex gap-2">
        <button class="btn btn-dark flex-grow-1" type="submit">{{ $buttonLabel ?? 'Show' }}</button>
    </div>
</form>

@push('scripts')
    <script>
        // Class chunne par sirf us class ke sections dikhao
        (function () {
            var cls = document.getElementById('att_class');
            var sec = document.getElementById('att_section');
            function sync() {
                Array.prototype.forEach.call(sec.options, function (o) {
                    if (!o.value) { return; }
                    var hide = cls.value === '' || o.dataset.class !== cls.value;
                    o.hidden = hide; o.disabled = hide;
                });
                var cur = sec.options[sec.selectedIndex];
                if (cur && cur.value && cur.disabled) { sec.value = ''; }
            }
            cls.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
