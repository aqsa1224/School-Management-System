<div class="mb-3">
    <label for="name" class="form-label fw-medium">Exam name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $exam->name ?? '') }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Mid Term 2026" required autofocus>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="start_date" class="form-label fw-medium">Start date</label>
        <input type="date" id="start_date" name="start_date"
               value="{{ old('start_date', isset($exam) ? $exam->start_date?->format('Y-m-d') : '') }}"
               class="form-control @error('start_date') is-invalid @enderror">
        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="end_date" class="form-label fw-medium">End date</label>
        <input type="date" id="end_date" name="end_date"
               value="{{ old('end_date', isset($exam) ? $exam->end_date?->format('Y-m-d') : '') }}"
               class="form-control @error('end_date') is-invalid @enderror">
        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
