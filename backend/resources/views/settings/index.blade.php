<x-app-layout>
    <x-slot name="header"><h2>Settings</h2></x-slot>

    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="row g-4">
            {{-- School profile --}}
            <div class="col-xl-8">
                <div class="card mb-4"><div class="card-body p-4">
                    <h5 class="mb-1">School profile</h5>
                    <p class="text-muted small mb-4">Ye maloomat sidebar, login page, fee voucher, receipt aur result card par khud lag jati hain.</p>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="school_name" class="form-label fw-medium">School name <span class="text-danger">*</span></label>
                            <input type="text" id="school_name" name="school_name" maxlength="100"
                                   value="{{ old('school_name', $school['name']) }}"
                                   class="form-control @error('school_name') is-invalid @enderror" required>
                            @error('school_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="school_address" class="form-label fw-medium">Address</label>
                            <input type="text" id="school_address" name="school_address" maxlength="255"
                                   value="{{ old('school_address', $school['address']) }}"
                                   class="form-control @error('school_address') is-invalid @enderror">
                            @error('school_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="school_phone" class="form-label fw-medium">Phone</label>
                            <input type="text" id="school_phone" name="school_phone" maxlength="30"
                                   value="{{ old('school_phone', $school['phone']) }}"
                                   class="form-control @error('school_phone') is-invalid @enderror">
                            @error('school_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="school_email" class="form-label fw-medium">Email</label>
                            <input type="email" id="school_email" name="school_email" maxlength="255"
                                   value="{{ old('school_email', $school['email']) }}"
                                   class="form-control @error('school_email') is-invalid @enderror">
                            @error('school_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div></div>

                <div class="card"><div class="card-body p-4">
                    <h5 class="mb-1">Fees &amp; Results</h5>
                    <p class="text-muted small mb-4">Paison ki nishani aur pass hone ka hisaab.</p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="currency" class="form-label fw-medium">Currency <span class="text-danger">*</span></label>
                            <input type="text" id="currency" name="currency" maxlength="10"
                                   value="{{ old('currency', $school['currency']) }}"
                                   class="form-control @error('currency') is-invalid @enderror" placeholder="Rs." required>
                            <div class="form-text">Voucher/receipt par amount se pehle likha jata hai (Rs., PKR, $ ...).</div>
                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="pass_percentage" class="form-label fw-medium">Pass percentage <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="1" max="100" id="pass_percentage" name="pass_percentage"
                                       value="{{ old('pass_percentage', (float) $passPercentage) }}"
                                       class="form-control @error('pass_percentage') is-invalid @enderror" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Har subject me itne % par Pass hota hai.</div>
                            @error('pass_percentage') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div></div>
            </div>

            {{-- Logo --}}
            <div class="col-xl-4">
                <div class="card"><div class="card-body p-4">
                    <h5 class="mb-1">School logo</h5>
                    <p class="text-muted small mb-3">PNG, JPG ya WEBP, 1 MB tak.</p>

                    <div class="border rounded p-3 text-center mb-3 bg-white">
                        @if ($school['logo_url'])
                            <img src="{{ $school['logo_url'] }}" alt="School logo" style="max-height:110px;max-width:100%;object-fit:contain">
                        @else
                            <x-application-logo class="text-gold" style="width:72px;height:72px" />
                            <div class="text-muted small mt-2">Abhi koi logo nahi (default nishani)</div>
                        @endif
                    </div>

                    <input type="file" id="school_logo" name="school_logo" accept="image/png,image/jpeg,image/webp"
                           class="form-control @error('school_logo') is-invalid @enderror">
                    @error('school_logo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    @if ($school['logo_url'])
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                            <label class="form-check-label" for="remove_logo">Maujooda logo hata dein</label>
                        </div>
                    @endif
                </div></div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
        </div>
    </form>
</x-app-layout>
