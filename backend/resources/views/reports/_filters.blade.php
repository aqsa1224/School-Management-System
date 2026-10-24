{{-- Report ke filters: $report['filters'] ki list se form banta hai. Class chunne par sirf us class ke sections dikhte hain. --}}
<form method="GET" action="{{ route($report['route']) }}" class="row g-2 align-items-end">
    @foreach ($report['filters'] as $f)
        <div class="{{ $f['col'] ?? 'col-6 col-md-2' }}">
            <label class="form-label small text-muted mb-1">{{ $f['label'] }}</label>

            @if ($f['type'] === 'class')
                <select name="school_class_id" id="rp_class" class="form-select" @required(! empty($f['required']))>
                    <option value="">{{ $f['all'] ?? 'All classes' }}</option>
                    @foreach ($report['classes'] as $c)
                        <option value="{{ $c->id }}" @selected((string) $f['value'] === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            @elseif ($f['type'] === 'section')
                <select name="section_id" id="rp_section" class="form-select">
                    <option value="">All sections</option>
                    @foreach ($report['classes'] as $c)
                        @foreach ($c->sections as $s)
                            <option value="{{ $s->id }}" data-class="{{ $c->id }}" @selected((string) $f['value'] === (string) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            @elseif ($f['type'] === 'select')
                <select name="{{ $f['name'] }}" class="form-select" @required(! empty($f['required']))>
                    @if (($f['all'] ?? null) !== null)
                        <option value="">{{ $f['all'] }}</option>
                    @endif
                    @foreach ($f['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) $f['value'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <input type="{{ $f['type'] }}" name="{{ $f['name'] }}" value="{{ $f['value'] }}" class="form-control"
                       placeholder="{{ $f['placeholder'] ?? '' }}" @required(! empty($f['required']))>
            @endif
        </div>
    @endforeach

    <div class="col-12 col-md-auto d-flex gap-2">
        <button class="btn btn-dark" type="submit"><i class="bi bi-funnel me-1"></i> Show</button>
        <a href="{{ route($report['route']) }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

@push('scripts')
    <script>
        // Class chunne par sirf us class ke sections dikhao
        (function () {
            var cls = document.getElementById('rp_class');
            var sec = document.getElementById('rp_section');
            if (!cls || !sec) { return; }
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
