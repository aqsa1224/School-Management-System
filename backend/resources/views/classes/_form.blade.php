{{-- Shared form: create aur edit dono me use hota hai --}}
<div class="mb-3">
    <label for="name" class="form-label fw-medium">Class name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $class->name ?? '') }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Class 5" required autofocus>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="sort_order" class="form-label fw-medium">Sort order</label>
    <input type="number" id="sort_order" name="sort_order" min="0"
           value="{{ old('sort_order', $class->sort_order ?? $nextOrder ?? '') }}"
           class="form-control @error('sort_order') is-invalid @enderror">
    <div class="form-text">List me class ki tarteeb (chhota number pehle aata hai).</div>
    @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <label for="description" class="form-label fw-medium">Description</label>
    <textarea id="description" name="description" rows="3"
              class="form-control @error('description') is-invalid @enderror">{{ old('description', $class->description ?? '') }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
