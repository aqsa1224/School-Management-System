<div class="mb-3">
    <label for="name" class="form-label fw-medium">Fee type name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $feeType->name ?? '') }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Tuition Fee" required autofocus>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <label for="default_amount" class="form-label fw-medium">Default amount <span class="text-danger">*</span></label>
    <input type="number" step="0.01" min="0" id="default_amount" name="default_amount"
           value="{{ old('default_amount', isset($feeType) ? (float) $feeType->default_amount : '') }}"
           class="form-control @error('default_amount') is-invalid @enderror" required>
    <div class="form-text">Voucher banate waqt ye amount khud bhar jata hai (baad me badal sakte hain).</div>
    @error('default_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
