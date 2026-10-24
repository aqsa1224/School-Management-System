<x-app-layout>
    <x-slot name="header"><h2>Bulk Vouchers (Poori Class)</h2></x-slot>

    <div class="row"><div class="col-xl-9">
        <div class="card"><div class="card-body p-4">
            <p class="text-muted">Chuni hui class (ya section) ke <strong>sab active students</strong> ke liye ek saath voucher ban jate hain. Jin students ka is month ka yehi fee type pehle se bana ho unhein chhor diya jata hai (duplicate nahi banta).</p>

            <form action="{{ route('fees.bulk.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="school_class_id" class="form-label fw-medium">Class <span class="text-danger">*</span></label>
                        <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" required>
                            <option value="">Select class...</option>
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" @selected((string) old('school_class_id') === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('school_class_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="section_id" class="form-label fw-medium">Section</label>
                        <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
                            <option value="">All sections</option>
                            @foreach ($classes as $c)
                                @foreach ($c->sections as $s)
                                    <option value="{{ $s->id }}" data-class="{{ $c->id }}" @selected((string) old('section_id') === (string) $s->id)>{{ $s->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        @error('section_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="fee_type_id" class="form-label fw-medium">Fee type <span class="text-danger">*</span></label>
                        <select id="fee_type_id" name="fee_type_id" class="form-select @error('fee_type_id') is-invalid @enderror" required>
                            <option value="">Select fee type...</option>
                            @foreach ($feeTypes as $t)
                                <option value="{{ $t->id }}" data-amount="{{ (float) $t->default_amount }}" @selected((string) old('fee_type_id') === (string) $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('fee_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="for_month" class="form-label fw-medium">Month <span class="text-danger">*</span></label>
                        <input type="month" id="for_month" name="for_month" value="{{ old('for_month', now()->format('Y-m')) }}"
                               class="form-control @error('for_month') is-invalid @enderror" required>
                        @error('for_month') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="amount" class="form-label fw-medium">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}"
                               class="form-control @error('amount') is-invalid @enderror" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="discount" class="form-label fw-medium">Discount</label>
                        <input type="number" step="0.01" min="0" id="discount" name="discount" value="{{ old('discount', 0) }}"
                               class="form-control @error('discount') is-invalid @enderror">
                        @error('discount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="due_date" class="form-label fw-medium">Due date</label>
                        <input type="date" id="due_date" name="due_date" value="{{ old('due_date') }}"
                               class="form-control @error('due_date') is-invalid @enderror">
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4" onclick="return confirm('Chuni hui class ke sab students ke voucher ban jayenge. Jari rakhein?');">Generate Vouchers</button>
                    <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>

    @push('scripts')
        <script>
            (function () {
                var type = document.getElementById('fee_type_id'), amount = document.getElementById('amount'), lastAuto = null;
                type.addEventListener('change', function () {
                    var opt = type.options[type.selectedIndex], def = opt && opt.dataset.amount;
                    if (def !== undefined && def !== '' && (amount.value === '' || amount.value === lastAuto)) { amount.value = def; lastAuto = def; }
                });
                var cls = document.getElementById('school_class_id'), sec = document.getElementById('section_id');
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
</x-app-layout>
