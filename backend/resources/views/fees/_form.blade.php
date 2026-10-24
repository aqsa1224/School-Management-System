@php $f = $fee ?? null; @endphp

@if ($f)
    <div class="alert alert-light border">
        <strong>{{ $f->student->full_name }}</strong>
        <span class="text-muted">| {{ $f->student->admission_no }} | {{ $f->student->schoolClass->name }}{{ $f->student->section ? ' - ' . $f->student->section->name : '' }}</span>
        <div class="small text-muted">Voucher {{ $f->voucher_no }}</div>
    </div>
@else
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label for="fee_class" class="form-label fw-medium">Class (student dhoondne ke liye)</label>
            <select id="fee_class" class="form-select">
                <option value="">All classes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8">
            <label for="student_id" class="form-label fw-medium">Student <span class="text-danger">*</span></label>
            <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                <option value="">Select student...</option>
                @foreach ($students as $s)
                    <option value="{{ $s->id }}" data-class="{{ $s->school_class_id }}"
                            @selected((string) old('student_id', $selectedStudent ?: '') === (string) $s->id)>{{ $s->full_name }} ({{ $s->admission_no }})</option>
                @endforeach
            </select>
            @error('student_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="fee_type_id" class="form-label fw-medium">Fee type <span class="text-danger">*</span></label>
        <select id="fee_type_id" name="fee_type_id" class="form-select @error('fee_type_id') is-invalid @enderror" required>
            <option value="">Select fee type...</option>
            @foreach ($feeTypes as $t)
                <option value="{{ $t->id }}" data-amount="{{ (float) $t->default_amount }}"
                        @selected((string) old('fee_type_id', $f->fee_type_id ?? '') === (string) $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        @error('fee_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="for_month" class="form-label fw-medium">Month</label>
        <input type="month" id="for_month" name="for_month"
               value="{{ old('for_month', $f?->for_month?->format('Y-m') ?? ($f ? '' : now()->format('Y-m'))) }}"
               class="form-control @error('for_month') is-invalid @enderror">
        <div class="form-text">Admission jaisi ek baar wali fee ke liye khali chhor sakte hain.</div>
        @error('for_month') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="amount" class="form-label fw-medium">Amount <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" id="amount" name="amount"
               value="{{ old('amount', $f ? (float) $f->amount : '') }}"
               class="form-control @error('amount') is-invalid @enderror" required>
        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="discount" class="form-label fw-medium">Discount</label>
        <input type="number" step="0.01" min="0" id="discount" name="discount"
               value="{{ old('discount', $f ? (float) $f->discount : 0) }}"
               class="form-control @error('discount') is-invalid @enderror">
        @error('discount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="due_date" class="form-label fw-medium">Due date</label>
        <input type="date" id="due_date" name="due_date"
               value="{{ old('due_date', $f?->due_date?->format('Y-m-d')) }}"
               class="form-control @error('due_date') is-invalid @enderror">
        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            // Fee type chunne par default amount khud bhar do (agar amount khali ya default hi ho)
            var type = document.getElementById('fee_type_id');
            var amount = document.getElementById('amount');
            var lastAuto = null;
            type.addEventListener('change', function () {
                var opt = type.options[type.selectedIndex];
                var def = opt && opt.dataset.amount;
                if (def !== undefined && def !== '' && (amount.value === '' || amount.value === lastAuto)) {
                    amount.value = def; lastAuto = def;
                }
            });

            // Class chunne par sirf us class ke students dikhao
            var cls = document.getElementById('fee_class');
            var stu = document.getElementById('student_id');
            if (cls && stu) {
                function sync() {
                    Array.prototype.forEach.call(stu.options, function (o) {
                        if (!o.value) { return; }
                        var hide = cls.value !== '' && o.dataset.class !== cls.value;
                        o.hidden = hide; o.disabled = hide;
                    });
                    var cur = stu.options[stu.selectedIndex];
                    if (cur && cur.value && cur.disabled) { stu.value = ''; }
                }
                cls.addEventListener('change', sync); sync();
            }
        })();
    </script>
@endpush
