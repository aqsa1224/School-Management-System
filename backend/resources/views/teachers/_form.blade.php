@php $hasLogin = isset($teacher) && $teacher->user_id; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $teacher->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required autofocus>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
        <input type="email" id="email" name="email" value="{{ old('email', $teacher->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label fw-medium">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $teacher->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror" maxlength="30">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="gender" class="form-label fw-medium">Gender</label>
        @php $g = old('gender', $teacher->gender ?? ''); @endphp
        <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
            <option value="">-</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l)
                <option value="{{ $v }}" @selected($g === $v)>{{ $l }}</option>
            @endforeach
        </select>
        @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="status" class="form-label fw-medium">Status <span class="text-danger">*</span></label>
        @php $st = old('status', $teacher->status ?? 'active'); @endphp
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" @selected($st === 'active')>Active</option>
            <option value="left" @selected($st === 'left')>Left</option>
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="qualification" class="form-label fw-medium">Qualification</label>
        <input type="text" id="qualification" name="qualification" value="{{ old('qualification', $teacher->qualification ?? '') }}"
               class="form-control @error('qualification') is-invalid @enderror" placeholder="e.g. M.Sc Mathematics">
        @error('qualification') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="joining_date" class="form-label fw-medium">Joining date</label>
        <input type="date" id="joining_date" name="joining_date"
               value="{{ old('joining_date', isset($teacher) ? $teacher->joining_date?->format('Y-m-d') : '') }}"
               class="form-control @error('joining_date') is-invalid @enderror">
        @error('joining_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label fw-medium">Address</label>
        <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $teacher->address ?? '') }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<hr class="my-4">

{{-- Login account --}}
<h6 class="mb-1">Login account</h6>
<p class="text-muted small mb-3">Teacher is email aur password se system me login kar sakega.</p>

@if ($hasLogin)
    <div class="alert alert-success py-2">Is teacher ka login account maujood hai ({{ $teacher->email }}).</div>
    <div class="mb-4" style="max-width:420px">
        <label for="password" class="form-label fw-medium">Naya password (sirf badalna ho to)</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
               autocomplete="new-password" minlength="8">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@else
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="create_login" value="1" id="create_login" @checked(old('create_login'))>
        <label class="form-check-label" for="create_login">Is teacher ke liye login account banayein</label>
    </div>
    <div class="mb-4" id="login-password-box" style="max-width:420px">
        <label for="password" class="form-label fw-medium">Password (kam az kam 8 characters)</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
               autocomplete="new-password" minlength="8">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @push('scripts')
        <script>
            (function () {
                var box = document.getElementById('create_login'), pw = document.getElementById('login-password-box');
                function sync() { pw.style.display = box.checked ? '' : 'none'; }
                box.addEventListener('change', sync); sync();
            })();
        </script>
    @endpush
@endif
