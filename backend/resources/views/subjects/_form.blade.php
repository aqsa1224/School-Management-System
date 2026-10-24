<div class="mb-3">
    <label for="name" class="form-label fw-medium">Subject name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $subject->name ?? '') }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Mathematics" required autofocus>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="code" class="form-label fw-medium">Code</label>
    <input type="text" id="code" name="code" value="{{ old('code', $subject->code ?? '') }}"
           class="form-control @error('code') is-invalid @enderror" placeholder="e.g. MATH" maxlength="20">
    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@php $checked = old('class_ids', $selected ?? []); @endphp
<div class="mb-4">
    <label class="form-label fw-medium">Kin classes me parhaya jata hai?</label>
    <div class="border rounded p-3 bg-white">
        @forelse ($classes as $class)
            <div class="form-check form-check-inline" style="min-width:130px">
                <input class="form-check-input" type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                       id="class-{{ $class->id }}" @checked(in_array($class->id, $checked))>
                <label class="form-check-label" for="class-{{ $class->id }}">{{ $class->name }}</label>
            </div>
        @empty
            <span class="text-muted">Pehle Classes me class add karein.</span>
        @endforelse
    </div>
    @error('class_ids.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>
