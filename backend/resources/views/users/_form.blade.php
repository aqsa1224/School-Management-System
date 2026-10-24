@php
    $u = $user ?? null;
    $isSelf = $u && $u->id === auth()->id();
    $roleNow = old('role', $u->role ?? '');
    $activeNow = old('is_active', $u ? ($u->is_active ? 1 : 0) : 1);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $u->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required autofocus>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label fw-medium">Email (login ke liye) <span class="text-danger">*</span></label>
        <input type="email" id="email" name="email" value="{{ old('email', $u->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="role" class="form-label fw-medium">Role <span class="text-danger">*</span></label>
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required @disabled($isSelf)>
            <option value="">Select role...</option>
            @foreach (config('roles.labels') as $value => $label)
                <option value="{{ $value }}" @selected($roleNow === $value)>{{ $label }}</option>
            @endforeach
        </select>
        {{-- disabled select submit nahi hota, isliye apna role hidden se jata hai (server usay badalne nahi deta) --}}
        @if ($isSelf) <input type="hidden" name="role" value="{{ $u->role }}"> @endif
        @error('role') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">
            Admin: sab kuch | Teacher: apni classes ki attendance/marks | Accountant: fees | Receptionist: students add/edit
        </div>
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label fw-medium">
            Password @if ($u) <span class="text-muted fw-normal">(sirf badalna ho to likhein)</span> @else <span class="text-danger">*</span> @endif
        </label>
        <input type="password" id="password" name="password" minlength="8" autocomplete="new-password"
               class="form-control @error('password') is-invalid @enderror" @required(! $u)>
        <div class="form-text">Kam az kam 8 characters.</div>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked((int) $activeNow === 1) @disabled($isSelf)>
            <label class="form-check-label" for="is_active">Account chalu hai (band karne par user login nahi kar sakta)</label>
        </div>
        @if ($isSelf) <input type="hidden" name="is_active" value="1"> @endif
        @error('is_active') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
</div>

@if ($u?->teacher)
    <div class="alert alert-info mt-4 mb-0 py-2">
        <i class="bi bi-person-badge me-1"></i> Ye account Teacher record <strong>{{ $u->teacher->name }}</strong> se jura hua hai.
        Role "Teacher" hi rakhein, warna ye teacher apni classes nahi dekh payega.
    </div>
@endif
