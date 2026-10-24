@php
    $s = $student ?? null;
    $selClass = old('school_class_id', $s->school_class_id ?? '');
    $selSec   = old('section_id', $s->section_id ?? '');
@endphp

<div class="row g-3">

    <div class="col-12"><h6 class="text-uppercase text-muted small mb-0">Student information</h6></div>

    <div class="col-md-4">
        <label for="admission_no" class="form-label fw-medium">Admission no <span class="text-danger">*</span></label>
        <input type="text" id="admission_no" name="admission_no" maxlength="50"
               value="{{ old('admission_no', $s->admission_no ?? $admissionNo ?? '') }}"
               class="form-control @error('admission_no') is-invalid @enderror" required>
        @error('admission_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="first_name" class="form-label fw-medium">First name <span class="text-danger">*</span></label>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $s->first_name ?? '') }}"
               class="form-control @error('first_name') is-invalid @enderror" required autofocus>
        @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="last_name" class="form-label fw-medium">Last name</label>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $s->last_name ?? '') }}"
               class="form-control @error('last_name') is-invalid @enderror">
        @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="gender" class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
        @php $g = old('gender', $s->gender ?? ''); @endphp
        <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
            <option value="">Select...</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l)
                <option value="{{ $v }}" @selected($g === $v)>{{ $l }}</option>
            @endforeach
        </select>
        @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="date_of_birth" class="form-label fw-medium">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth"
               value="{{ old('date_of_birth', isset($s) ? $s->date_of_birth?->format('Y-m-d') : '') }}"
               class="form-control @error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="admission_date" class="form-label fw-medium">Admission date</label>
        <input type="date" id="admission_date" name="admission_date"
               value="{{ old('admission_date', isset($s) ? $s->admission_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
               class="form-control @error('admission_date') is-invalid @enderror">
        @error('admission_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="school_class_id" class="form-label fw-medium">Class <span class="text-danger">*</span></label>
        <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" required>
            <option value="">Select class...</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected($selClass == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('school_class_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="section_id" class="form-label fw-medium">Section</label>
        <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
            <option value="">Select section...</option>
            @foreach ($classes as $class)
                @foreach ($class->sections as $section)
                    <option value="{{ $section->id }}" data-class="{{ $class->id }}" @selected($selSec == $section->id)>{{ $section->name }}</option>
                @endforeach
            @endforeach
        </select>
        @error('section_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label fw-medium">Status <span class="text-danger">*</span></label>
        @php $st = old('status', $s->status ?? 'active'); @endphp
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" @selected($st === 'active')>Active</option>
            <option value="left" @selected($st === 'left')>Left</option>
            <option value="passed_out" @selected($st === 'passed_out')>Passed out</option>
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="phone" class="form-label fw-medium">Student phone</label>
        <input type="text" id="phone" name="phone" maxlength="30" value="{{ old('phone', $s->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label for="email" class="form-label fw-medium">Student email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $s->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label fw-medium">Address</label>
        <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $s->address ?? '') }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-uppercase text-muted small mb-0">Guardian</h6></div>

    <div class="col-md-6">
        <label for="guardian_name" class="form-label fw-medium">Guardian name <span class="text-danger">*</span></label>
        <input type="text" id="guardian_name" name="guardian_name" value="{{ old('guardian_name', $s->guardian_name ?? '') }}"
               class="form-control @error('guardian_name') is-invalid @enderror" required>
        @error('guardian_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="guardian_phone" class="form-label fw-medium">Guardian phone <span class="text-danger">*</span></label>
        <input type="text" id="guardian_phone" name="guardian_phone" maxlength="30" value="{{ old('guardian_phone', $s->guardian_phone ?? '') }}"
               class="form-control @error('guardian_phone') is-invalid @enderror" required>
        @error('guardian_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-uppercase text-muted small mb-0">Photo</h6></div>

    <div class="col-md-6">
        <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp"
               class="form-control @error('photo') is-invalid @enderror">
        <div class="form-text">JPG, PNG ya WEBP, 2 MB tak.</div>
        @error('photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if (isset($s) && $s->photo_url)
        <div class="col-md-6 d-flex align-items-center gap-3">
            <img src="{{ $s->photo_url }}" alt="Photo" class="rounded" style="width:64px;height:64px;object-fit:cover">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                <label class="form-check-label" for="remove_photo">Maujooda photo hata dein</label>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    <script>
        // Class chunne par sirf us class ke sections dikhao
        (function () {
            var cls = document.getElementById('school_class_id');
            var sec = document.getElementById('section_id');
            function sync() {
                Array.prototype.forEach.call(sec.options, function (o) {
                    if (!o.value) { return; }
                    var hide = o.dataset.class !== cls.value;
                    o.hidden = hide; o.disabled = hide;
                });
                var cur = sec.options[sec.selectedIndex];
                if (cur && cur.value && cur.disabled) { sec.value = ''; }
            }
            cls.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
